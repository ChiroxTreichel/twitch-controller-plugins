<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Subathon
 * ===================================================================
 *
 * Ein Stream, der laenger wird: jedes Abo, jede Handvoll Bits und
 * jede Spende schiebt das Ende nach hinten, bis zu einer Obergrenze.
 *
 * Uebernommen aus dem Windows-Programm in legacy/subathon-tool, mit
 * denselben sieben Reitern. Was dort das Programm selbst tat - bei
 * Twitch anmelden, StreamElements abfragen, einen kleinen Webserver
 * fuer das Overlay betreiben -, macht hier das System:
 *
 *   Abos, Geschenke, Bits  ueber die EventSub des Kerns
 *   Spenden                ueber PayPal, StreamElements, StreamLabs
 *                          und Throne (Haken 'tips.donation')
 *   Overlay                als Platz in der Flaeche
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\App;
use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Core\Overlay\Bus;
use TwitchController\Plugin\Subathon\Booking;
use TwitchController\Plugin\Subathon\HappyHour;
use TwitchController\Plugin\Subathon\Log;
use TwitchController\Plugin\Subathon\Subathon;
use TwitchController\Plugin\Subathon\Texts;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Subathon'] = [
        'label'       => translate('subathon.name'),
        'permissions' => [
            'Subathon.Global.View' => translate('subathon.perm.view'),
            'Subathon.Global.Edit' => translate('subathon.perm.edit'),

            /*
             * Buchen ist ein eigenes Recht: wer im Stream von Hand
             * eine Spende nachtraegt, muss deshalb nicht auch die
             * Obergrenze verstellen duerfen.
             */
            'Subathon.Global.Book' => translate('subathon.perm.book'),
            'Subathon.Global.Toggle' => translate('subathon.perm.toggle'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Das Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Eine eigene Gruppe: ein Subathon ist kein Anzeige-Plugin, er
    // ist das, worum der ganze Stream herum gebaut ist.
    $nav['tools']['label'] = translate('subathon.nav.group');
    $nav['tools']['order'] = 30;
    $nav['tools']['items'][] = [
        'label'      => translate('subathon.nav.item'),
        'href'       => '/tools/subathon',
        'permission' => 'Subathon.Global.View',
        'toggle'     => [
            'on'         => Subathon::enabled($app),
            'action'     => '/tools/subathon/toggle',
            'value'      => 'toggle',
            'permission' => 'Subathon.Global.Toggle',
            'title'      => translate('subathon.toggle_hint'),
        ],
    ];

    return $nav;
});

/*
 * Die Einstellungen des PLUGINS - erreichbar aus der Plugin-Liste,
 * neben "Ausschalten" und "Entfernen".
 *
 * Hier steht, was das Plugin betrifft, und nicht, was den laufenden
 * Subathon betrifft: Startzeit und Obergrenze gehoeren in den Reiter
 * "Einstellungen", die Frage nach einem Reiter gehoert hierher.
 */
$hooks->on('plugin.settings', static function (array $links): array {
    $links[Subathon::SLUG] = [
        'label' => translate('subathon.settings'),
        'href'  => '/tools/subathon/settings',
    ];

    return $links;
});

/*
 * Das Skript der Verwaltung: es laesst die grosse Zahl auf der
 * Uebersicht herunterzaehlen. Ohne es steht dort die Zahl, die beim
 * Laden galt - nicht falsch, nur langweilig.
 */
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['js'][] = $app->asset('/plugin/subathon/assets/subathon-admin.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Das Overlay
// -------------------------------------------------------------------
$hooks->on('overlay.slots', static function (array $slots) use ($app): array {
    /*
     * Aus heisst auch: kein Platz im Overlay. Ihn anzumelden und
     * leer zu lassen waere ein Loch in der Anordnung, das niemand
     * erklaeren kann - und die Browserquelle nimmt die Aenderung
     * ohne Neuladen mit.
     */
    if (!Subathon::enabled($app)) {
        return $slots;
    }

    $slots[Subathon::SLUG] = [
        'label'  => translate('subathon.name'),

        /*
         * Oben ueber die ganze Breite - im Programm war das eine
         * eigene Browserquelle, die man selbst zurechtzog. Hier ist
         * es ein Platz wie jeder andere; wo er liegt und wie weit
         * vorne, entscheidet die Overlay-Seite.
         */
        'position' => 'top-left',
        'width'    => '100%',
        'height'   => '220px',
    ];

    return $slots;
});

$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    if (!Subathon::enabled($app)) {
        return $assets;
    }

    $assets['css'][] = $app->asset('/plugin/subathon/assets/subathon.css');

    /*
     * Reihenfolge zaehlt: state.js legt den Anfangszustand ab,
     * overlay.js zeigt ihn an. Ohne den Anfangszustand blieb die
     * Flaeche leer, bis das naechste Abo kommt - und das kann Stunden
     * dauern.
     */
    $assets['js'][] = $app->url('/display/subathon/state.js');
    $assets['js'][] = $app->asset('/plugin/subathon/assets/subathon.js');

    return $assets;
});

/**
 * Der Zustand, wie ihn das Overlay braucht.
 *
 * Er enthaelt KEINE laufende Sekunde: Start, Dauer, Pause und die
 * Uhrzeit des Servers reichen, den Rest rechnet der Browser. So
 * stimmt die Anzeige auch zwischen zwei Nachrichten - und es gibt
 * nichts, was im Sekundentakt ueber die Leitung muesste.
 *
 * @return array<string, mixed>
 */
$zustand = static function (App $app): array {
    $jetzt = time();
    $start = Subathon::start($app);
    $pause = Subathon::effectiveBreak(
        Subathon::breakTime($app),
        Subathon::isPaused($app),
        Subathon::pausedAt($app),
        $jetzt
    );

    $timer = Subathon::timer($app);
    $ende = Subathon::endAt($start, $timer, $pause);

    $nachrichten = [];

    foreach (Subathon::messages($app) as $zeile) {
        $zeile = trim(Texts::render($app, $zeile));

        if ($zeile !== '') {
            $nachrichten[] = $zeile;
        }
    }

    return [
        'now'      => $jetzt,
        'start'    => $start,
        'end'      => $ende,
        'timer'    => $timer,
        'max'      => Subathon::maxDuration($app),
        'break'    => $pause,
        'paused'   => Subathon::isPaused($app),
        'status'   => Subathon::statusOf($start, $ende, Subathon::isPaused($app), $jetzt),
        'colors'   => Subathon::colors($app),
        'messages' => $nachrichten,
        'labels'   => [
            'done'     => translate('subathon.overlay.done'),
            'todo'     => translate('subathon.overlay.todo'),
            'possible' => translate('subathon.overlay.possible'),
        ],
    ];
};

$router->get('/display/subathon/state.js', static function () use ($app, $zustand): Response {
    return Response::html(
        'window.SUBATHON_STATE = ' . json_encode(
            $zustand($app),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . ";\n",
        200,
        [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, must-revalidate',
        ]
    );
}, ['auth' => true, 'permission' => 'Account.Overlay.View']);

/**
 * Den Platz im Overlay auf den neuesten Stand bringen.
 *
 * Nach jeder Buchung und in jedem Takt - aber nur, wenn sich etwas
 * geaendert hat. Der Zustand enthaelt die Uhrzeit des Servers, und die
 * aendert sich immer; verglichen wird deshalb ohne sie.
 */
$melden = static function (App $app) use ($zustand): void {
    $daten = $zustand($app);

    $vergleich = $daten;
    unset($vergleich['now']);

    $merker = (string) json_encode($vergleich);

    if ($merker === $app->settings->string('overlay_state', '', Subathon::scope())) {
        return;
    }

    $app->settings->set('overlay_state', $merker, Subathon::scope());
    (new Bus($app))->send(Subathon::SLUG, $daten);
};

// -------------------------------------------------------------------
//  Was Zeit bringt
// -------------------------------------------------------------------
/*
 * Abos, Geschenke und Bits kommen aus der EventSub des Kerns. Im
 * Programm haengte dafuer eine eigene Websocket-Verbindung mit
 * eigenen Tokens daran - hier liegt das Ereignis ohnehin schon in der
 * Tabelle, wenn dieser Haken laeuft.
 */
$hooks->on('core.event.stored', static function (array $event) use ($app, $melden): void {
    // Aus ist aus: die Ereignisse kommen an, werden aber nicht
    // gutgeschrieben.
    if (!Subathon::enabled($app)) {
        return;
    }

    $typ = (string) ($event['event_type'] ?? '');
    $wer = trim((string) ($event['actor_name'] ?? ''));
    $nutzlast = is_array($event['payload'] ?? null) ? $event['payload'] : [];
    $stufe = (string) ($nutzlast['tier'] ?? '1000');

    switch ($typ) {
        case 'twitch.channel.subscribe':
            /*
             * Ein geschenktes Abo meldet Twitch zweimal: hier mit
             * is_gift und noch einmal als subscription.gift. Nur der
             * zweite Weg wird gebucht, sonst zaehlt es doppelt.
             */
            if (!empty($nutzlast['is_gift']) || !empty($nutzlast['isGift'])) {
                return;
            }

            Booking::subscription($app, $stufe, $wer, false);
            break;

        case 'twitch.channel.subscription.message':
            // Ein Resub - im Programm kam er ueber die
            // Chat-Benachrichtigung und zaehlte wie ein Abo.
            Booking::subscription($app, $stufe, $wer, false);
            break;

        case 'twitch.channel.subscription.gift':
            Booking::subscription(
                $app,
                $stufe,
                $wer !== '' ? $wer : translate('subathon.anonymous'),
                true,
                max(1, (int) round((float) ($event['amount'] ?? 1)))
            );
            break;

        case 'twitch.channel.cheer':
            Booking::bits(
                $app,
                (int) round((float) ($event['amount'] ?? 0)),
                $wer !== '' ? $wer : translate('subathon.anonymous')
            );
            break;

        default:
            /*
             * Throne meldet seine Spenden nicht ueber 'tips.donation',
             * sondern legt sie als Ereignis ab - mit Betrag in Euro.
             * Damit zaehlen alle vier Wege, die es hier gibt.
             */
            if (!str_starts_with($typ, 'throne.')) {
                return;
            }

            $betrag = (float) ($event['amount'] ?? 0);

            if ($betrag <= 0) {
                return;
            }

            Booking::donation(
                $app,
                (int) round($betrag * 100),
                $wer !== '' ? $wer : translate('subathon.anonymous')
            );
            break;
    }

    $melden($app);
});

/*
 * Spenden. PayPal, StreamElements und StreamLabs melden sie unter
 * 'tips.donation', Throne ebenso - vier Wege, eine Buchung.
 *
 * Das Programm fragte dafuer allein bei StreamElements nach, im
 * Sekundentakt, mit einem eigenen OAuth-Token in der config.json.
 */
$hooks->on('tips.donation', static function (array $spende) use ($app, $melden): void {
    if (!Subathon::enabled($app)) {
        return;
    }

    $betrag = (float) ($spende['amount'] ?? 0);

    if ($betrag <= 0) {
        return;
    }

    $wer = trim((string) ($spende['name'] ?? ''));

    Booking::donation(
        $app,
        (int) round($betrag * 100),
        $wer !== '' ? $wer : translate('subathon.anonymous')
    );

    $melden($app);
});

// -------------------------------------------------------------------
//  Der Takt
// -------------------------------------------------------------------
/*
 * Nur zum Melden. Gezaehlt wird hier nichts - der Zustand ergibt sich
 * aus Start, Dauer und Pause, und die aendern sich nur, wenn jemand
 * etwas tut.
 *
 * Gebraucht wird der Takt trotzdem: beim Start und beim Ende aendert
 * sich der Zustand, ohne dass jemand etwas getan hat.
 */
$hooks->on('cron.tick', static function () use ($app, $melden): void {
    if (!Subathon::enabled($app)) {
        return;
    }

    $melden($app);
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$zurueck = static function (App $app, ?string $notice = null, ?string $error = null, string $tab = ''): Response {
    $query = array_filter([
        'tab'    => $tab,
        'notice' => $notice,
        'error'  => $error,
    ], static fn (?string $w): bool => $w !== null && $w !== '');

    return Response::redirect($app->url('/tools/subathon') . ($query === [] ? '' : '?' . http_build_query($query)));
};

$router->get('/tools/subathon', static function (Request $request) use ($app, $plugin): Response {
    $reiter = (string) $request->get('tab');
    $reiter = in_array($reiter, ['overview', 'settings', 'overlay', 'messages', 'manual', 'happy', 'history'], true)
        ? $reiter
        : 'overview';

    /*
     * Ein Lesezeichen auf einen Reiter, den es gerade nicht gibt,
     * fuehrt auf die Uebersicht - nicht auf eine leere Seite.
     */
    if ($reiter === 'manual' && !Subathon::showManual($app)) {
        $reiter = 'overview';
    }

    $jetzt = time();
    $start = Subathon::start($app);
    $pause = Subathon::effectiveBreak(
        Subathon::breakTime($app),
        Subathon::isPaused($app),
        Subathon::pausedAt($app),
        $jetzt
    );

    $ende = Subathon::endAt($start, Subathon::timer($app), $pause);

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'    => translate('subathon.name'),
        'active'   => 'tools/subathon',
        'tab'      => $reiter,

        'status'   => Subathon::statusOf($start, $ende, Subathon::isPaused($app), $jetzt),
        'now'      => $jetzt,
        'start'    => $start,
        'end'      => $ende,
        'timer'    => Subathon::timer($app),
        'max'      => Subathon::maxDuration($app),
        'break'    => $pause,

        'owner'    => Subathon::owner($app),
        'perSub'   => Subathon::secondsPerSub($app),
        'perBits'  => Subathon::bitsPerSub($app),
        'perCent'  => Subathon::centPerSub($app),

        'colors'   => Subathon::colors($app),
        'messages' => Subathon::messages($app),
        'happy'    => HappyHour::all($app),
        'history'  => $reiter === 'history' ? Log::recent($app, 200) : [],
        'preview'  => Texts::values($app),

        'enabled'  => Subathon::enabled($app),
        'canToggle' => $app->auth->can('Subathon.Global.Toggle'),
        'canEdit'  => $app->auth->can('Subathon.Global.Edit'),
        'canBook'  => $app->auth->can('Subathon.Global.Book'),
        'locked'   => Subathon::isLocked(Subathon::statusOf($start, $ende, Subathon::isPaused($app), $jetzt)),
        'showManual' => Subathon::showManual($app),
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => $request->get('notice'),
        'error'    => $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Subathon.Global.View']);

/*
 * Der Hauptschalter.
 *
 * Bewusst NICHT durch isLocked() gesperrt: ihn umlegen zu koennen ist
 * gerade dann wichtig, wenn etwas laeuft. Die Sperre gilt den Zahlen,
 * nicht dem Schalter.
 */
$router->post('/tools/subathon/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($app, null, translate('common.error.form_expired'));
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck($app, null, translate('common.error.unknown_action'));
    }

    if (!permission('Subathon.Global.Toggle')) {
        return $zurueck($app, null, translate('common.error.no_permission'));
    }

    $an = !Subathon::enabled($app);
    Subathon::setEnabled($app, $an);

    return $zurueck($app, $an
        ? translate('subathon.turned_on')
        : translate('subathon.turned_off'));
}, ['auth' => true, 'permission' => 'Subathon.Global.View']);

$router->get('/tools/subathon/settings', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'      => translate('subathon.settings'),
        'active'     => 'tools/subathon',
        'showManual' => Subathon::showManual($app),
        'prices'     => Subathon::tierPrices($app),
        'canEdit'    => $app->auth->can('Subathon.Global.Edit'),
        'csrf'       => $app->auth->csrfToken(),
        'notice'     => $request->get('notice'),
        'error'      => $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Subathon.Global.View']);

$router->post('/tools/subathon/settings', static function (Request $request) use ($app): Response {
    $zurueckHierher = static function (?string $notice = null, ?string $error = null) use ($app): Response {
        $query = array_filter(['notice' => $notice, 'error' => $error], static fn (?string $w): bool => $w !== null && $w !== '');

        return Response::redirect($app->url('/tools/subathon/settings') . ($query === [] ? '' : '?' . http_build_query($query)));
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckHierher(null, translate('common.error.form_expired'));
    }

    if (!$app->auth->can('Subathon.Global.Edit')) {
        return $zurueckHierher(null, translate('common.error.no_permission'));
    }

    $app->settings->set('show_manual', $request->input('show_manual') !== '', Subathon::scope());

    /*
     * Die Preise als Zeichenkette: 4,99 ist keine ganze Zahl, und ein
     * Komma statt eines Punktes soll kein 4 werden.
     */
    foreach (Subathon::TIER_PRICE as $stufe => $vorgabe) {
        $eingabe = str_replace(',', '.', trim((string) $request->input('price_tier' . $stufe)));

        $app->settings->set(
            'price_tier' . $stufe,
            (string) Subathon::price((float) $eingabe, $vorgabe),
            Subathon::scope()
        );
    }

    return $zurueckHierher(translate('subathon.saved'));
}, ['auth' => true]);

$router->post('/tools/subathon', static function (Request $request) use ($app, $zurueck, $melden): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($app, null, translate('common.error.form_expired'));
    }

    $aktion = (string) $request->input('action');
    $reiter = (string) $request->input('tab');

    $darfBuchen = $app->auth->can('Subathon.Global.Book');
    $darfAendern = $app->auth->can('Subathon.Global.Edit');

    switch ($aktion) {
        // ----- Uebersicht: pausieren, weiter, zuruecksetzen ---------
        case 'pause':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            $app->settings->set('pause', true, Subathon::scope());
            $app->settings->set('paused_at', time(), Subathon::scope());
            $melden($app);

            return $zurueck($app, translate('subathon.paused'));

        case 'resume':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            /*
             * Die Pause wird beim Fortsetzen abgerechnet und nicht
             * mitgezaehlt: das Programm schrieb dafuer jede Sekunde
             * die config.json.
             */
            $app->settings->set(
                'break_time',
                Subathon::effectiveBreak(
                    Subathon::breakTime($app),
                    true,
                    Subathon::pausedAt($app),
                    time()
                ),
                Subathon::scope()
            );
            $app->settings->set('pause', false, Subathon::scope());
            $app->settings->set('paused_at', 0, Subathon::scope());
            $melden($app);

            return $zurueck($app, translate('subathon.resumed'));

        case 'reset':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            /*
             * Wie im Programm: zurueck auf acht Stunden, keine Pause,
             * Start auf morgen frueh. Der Verlauf bleibt stehen - er
             * gehoert zum vergangenen Subathon und nicht zum
             * naechsten.
             */
            $app->settings->setMany([
                'timer'      => Subathon::DEFAULT_TIMER,
                'break_time' => 0,
                'pause'      => false,
                'paused_at'  => 0,
                'start'      => strtotime('tomorrow 10:00'),
            ], Subathon::scope());
            $melden($app);

            return $zurueck($app, translate('subathon.reset_done'));

        // ----- Einstellungen ---------------------------------------
        case 'settings':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            /*
             * Zu ist zu - auch fuer ein Formular, das noch offen war,
             * als er startete. Die Sperre gehoert hierher und nicht
             * nur an die Felder: ein readonly im HTML ist eine Bitte.
             */
            $jetzt = time();
            $start = Subathon::start($app);
            $pause = Subathon::effectiveBreak(
                Subathon::breakTime($app),
                Subathon::isPaused($app),
                Subathon::pausedAt($app),
                $jetzt
            );

            if (Subathon::isLocked(Subathon::statusOf(
                $start,
                Subathon::endAt($start, Subathon::timer($app), $pause),
                Subathon::isPaused($app),
                $jetzt
            ))) {
                return $zurueck($app, null, translate('subathon.locked'), 'settings');
            }

            $startText = trim((string) $request->input('start'));
            $start = $startText === '' ? 0 : (int) strtotime($startText);

            $minuten = max(1, (int) $request->input('minutes_per_sub'));

            $app->settings->setMany([
                'start'           => max(0, $start),
                'max_duration'    => max(0, (int) $request->input('max_hours')) * 3600,
                'owner'           => trim((string) $request->input('owner')),
                'seconds_per_sub' => $minuten * 60,
                'bits_per_sub'    => max(1, (int) $request->input('bits_per_sub')),
                'cent_per_sub'    => max(1, (int) $request->input('cent_per_sub')),
            ], Subathon::scope());

            $melden($app);

            return $zurueck($app, translate('subathon.saved'), null, 'settings');

        // ----- Overlay: die sechs Farben ---------------------------
        case 'colors':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            foreach (Subathon::COLORS as $name => $vorgabe) {
                $app->settings->set(
                    'color_' . $name,
                    Subathon::normalizeColor((string) $request->input('color_' . $name), $vorgabe),
                    Subathon::scope()
                );
            }

            $melden($app);

            return $zurueck($app, translate('subathon.saved'), null, 'overlay');

        // ----- Nachrichten -----------------------------------------
        case 'messages':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            $zeilen = preg_split('/\r\n|\r|\n/', (string) $request->input('messages')) ?: [];

            /*
             * Leere Zeilen bleiben stehen: im Programm war eine leere
             * Nachricht eine Pause in der Laufschrift, und die
             * mitgelieferte Liste nutzt sie auch so.
             */
            Subathon::setMessages($app, array_map(
                static fn (string $zeile): string => rtrim($zeile),
                array_slice($zeilen, 0, 50)
            ));

            $melden($app);

            return $zurueck($app, translate('subathon.saved'), null, 'messages');

        // ----- Manuelles Buchen ------------------------------------
        case 'book':
            if (!$darfBuchen) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            /*
             * Ausgeblendet heisst aus. Ein Formular aus einem alten
             * Reiter soll nicht noch buchen koennen, nachdem jemand
             * ihn weggeschaltet hat.
             */
            if (!Subathon::showManual($app)) {
                return $zurueck($app, null, translate('subathon.manual_off'));
            }

            $wer = trim((string) $request->input('who'));
            $menge = max(1, (int) $request->input('amount'));
            $was = (string) $request->input('kind');

            // Minuten gehen ohne Namen - sie sind niemandes Verdienst,
            // sondern eine Entscheidung des Streamers.
            if ($wer === '' && $was !== 'minutes') {
                return $zurueck($app, null, translate('subathon.need_name'), 'manual');
            }

            switch ($was) {
                case 'sub':
                    $sekunden = Booking::subscription($app, '1000', $wer, false);
                    break;

                case 'gift':
                    $sekunden = Booking::subscription($app, '1000', $wer, true, $menge);
                    break;

                case 'bits':
                    $sekunden = Booking::bits($app, $menge, $wer);
                    break;

                case 'donation':
                    // Die Menge sind CENT - wie im Programm, wo die
                    // Spende ueber "Menge" in Cent gebucht wurde.
                    $sekunden = Booking::donation($app, $menge, $wer);
                    break;

                case 'minutes':
                    $sekunden = Booking::minutes($app, $menge, $wer !== '' ? $wer : translate('subathon.by_hand'));
                    break;

                default:
                    return $zurueck($app, null, translate('common.error.unknown_action'), 'manual');
            }

            $melden($app);

            return $zurueck(
                $app,
                $sekunden > 0
                    ? translate('subathon.booked', ['time' => Log::duration($sekunden)])
                    : translate('subathon.booked_closed'),
                null,
                'manual'
            );

        // ----- Happy Hour ------------------------------------------
        case 'happy_add':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            $eintraege = HappyHour::all($app);
            $eintraege[] = [
                'start_hour' => max(0, min(23, (int) $request->input('hour'))),
                'type'       => (int) $request->input('type'),
            ];

            HappyHour::save($app, $eintraege);
            $melden($app);

            return $zurueck($app, translate('subathon.saved'), null, 'happy');

        case 'happy_remove':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            $stunde = (int) $request->input('hour');

            HappyHour::save($app, array_values(array_filter(
                HappyHour::all($app),
                static fn (array $eintrag): bool => $eintrag['start_hour'] !== $stunde
            )));

            $melden($app);

            return $zurueck($app, translate('subathon.removed'), null, 'happy');

        // ----- Verlauf ---------------------------------------------
        case 'clear_log':
            if (!$darfAendern) {
                return $zurueck($app, null, translate('common.error.no_permission'));
            }

            Log::clear($app);

            return $zurueck($app, translate('subathon.log_cleared'), null, 'history');
    }

    return $zurueck($app, null, translate('common.error.unknown_action'), $reiter);
}, ['auth' => true]);
