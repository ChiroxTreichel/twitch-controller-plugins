<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Giveaway
 * ===================================================================
 *
 * Zuschauer holen sich mit einem Chatbefehl Tickets - eines je
 * Intervall, Abonnenten auf Wunsch eines mehr. Auf Knopfdruck
 * erscheint im Overlay ein Gluecksrad mit allen, die ein Ticket haben,
 * jeder so gross wie seine Tickets. Dann wird Preis fuer Preis
 * gezogen, von unten nach oben, und der Gewinner im Chat angesagt.
 *
 * Der Befehl laeuft ueber das Plugin Chatbefehle (Haken
 * chat_commands.answer): dort steht der Hauptschalter fuer alle
 * Befehle, dort steht er in !befehle, und dort sorgt das unsichtbare
 * Anhaengsel dafuer, dass Twitch zwei gleiche Antworten hintereinander
 * nicht verschluckt. Die Werbung laeuft ueber das Timer-Plugin, wenn
 * es da ist (Haken timers.external) - zaehlen, warten und nur waehrend
 * des Streams posten kann es schon.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\App;
use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Giveaway\Draw;
use TwitchController\Plugin\Giveaway\Giveaway;
use TwitchController\Plugin\Giveaway\Tickets;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Giveaway'] = [
        'label'       => translate('giveaway.name'),
        'permissions' => [
            'Giveaway.Global.View'   => translate('giveaway.perm.view'),
            'Giveaway.Global.Edit'   => translate('giveaway.perm.edit'),
            'Giveaway.Global.Toggle' => translate('giveaway.perm.toggle'),

            // Ziehen ist ein eigenes Recht: wer die Texte pflegt, muss
            // deshalb nicht im Stream den Knopf druecken duerfen - und
            // umgekehrt.
            'Giveaway.Global.Manage' => translate('giveaway.perm.manage'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Anhaengen, nicht die Gruppe setzen: der Subathon haengt in
    // dieselbe.
    $nav['tools']['label'] = translate('giveaway.nav.group');
    $nav['tools']['order'] = 30;
    $nav['tools']['items'][] = [
        'label'      => translate('giveaway.name'),
        'href'       => '/tools/giveaway',
        'permission' => 'Giveaway.Global.View',
        'toggle'     => [
            'on'         => Giveaway::enabled($app),
            'action'     => '/tools/giveaway/toggle',
            'value'      => 'toggle',
            'permission' => 'Giveaway.Global.Toggle',
            'title'      => translate('giveaway.toggle_hint'),
        ],
    ];

    return $nav;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/giveaway/assets/giveaway.css');
    $assets['js'][] = $app->asset('/plugin/giveaway/assets/giveaway.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Overlay
// -------------------------------------------------------------------
// Der Platz steht immer da, auch wenn das Giveaway aus ist - dann ist
// er leer und durchsichtig. Ihn je nach Schalter an- und abzumelden,
// liesse die Browserquelle bei jedem Umschalten neu laden.
$hooks->on('overlay.slots', static function (array $slots): array {
    $slots[Giveaway::SLOT] = [
        'label'    => translate('giveaway.name'),
        'position' => 'center',
        'width'    => '760px',
    ];

    return $slots;
});

$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/giveaway/assets/overlay.css');

    // Reihenfolge zaehlt: state.js legt den Anfangszustand ab, overlay.js
    // zeigt ihn an. Die Leitung spielt nichts nach - ohne state.js
    // stuende nach dem Neuladen der Quelle mitten in der Ziehung kein
    // Rad mehr im Bild.
    $assets['js'][] = $app->url('/tools/giveaway/state.js');
    $assets['js'][] = $app->asset('/plugin/giveaway/assets/overlay.js');

    return $assets;
});

$router->get('/tools/giveaway/state.js', static function () use ($app): Response {
    return Response::html(
        'window.GIVEAWAY_STATE = ' . json_encode(
            (new Draw($app))->overlayState(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        ) . ";\n",
        200,
        [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, must-revalidate',
        ]
    );
}, ['auth' => true, 'permission' => 'Account.Overlay.View']);

// -------------------------------------------------------------------
//  Der Befehl
// -------------------------------------------------------------------
$hooks->on('chat_commands.names', static function (array $namen) use ($app): array {
    if (Giveaway::enabled($app)) {
        $namen[] = Giveaway::config($app)['command'];
    }

    return $namen;
});

$hooks->on('chat_commands.answer', static function (string $antwort, string $name, array $message = []) use ($app): string {
    // Nur einspringen, wenn noch niemand geantwortet hat. Und aus ist
    // aus: dann antwortet der Befehl gar nicht, als gaebe es ihn nicht.
    if ($antwort !== '' || !Giveaway::enabled($app) || $name !== Giveaway::config($app)['command']) {
        return $antwort;
    }

    return (new Tickets($app))->claim($message);
});

// -------------------------------------------------------------------
//  Werbung im Chat
// -------------------------------------------------------------------
// Laeuft nur, wenn das Timer-Plugin da ist - sonst ruft diesen Haken
// niemand. Die Antwort muss billig sein: gefragt wird bei jeder
// Chatzeile. Was nachzuschlagen ist, steht in 'resolve'.
$hooks->on('timers.external', static function (array $timer) use ($app): array {
    $config = Giveaway::config($app);

    if (!$config['timer_enabled'] || !Giveaway::enabled($app)) {
        return $timer;
    }

    $timer[] = [
        'id'               => Giveaway::TIMER_ID,
        'title'            => Giveaway::TIMER_ID,
        'interval_minutes' => $config['timer_interval'],
        'min_lines'        => $config['timer_lines'],
        'enabled'          => true,
        'resolve'          => static fn (App $app): string => Giveaway::timerMessage($app),
    ];

    return $timer;
});

// -------------------------------------------------------------------
//  Die Ansage, falls die Seite nicht offen ist
// -------------------------------------------------------------------
// Normalerweise sagt das Skript der Verwaltungsseite an, sobald das Rad
// steht. Ist sie zu, holt der Worker es nach - mit fuenf Sekunden
// Nachsicht, damit eine offene Seite zuerst darf.
$hooks->on('cron.tick', static function () use ($app): void {
    (new Draw($app))->announce(5.0);
});

// -------------------------------------------------------------------
//  Hilfsmittel fuer die Routen
// -------------------------------------------------------------------
$zurueck = static function (?string $notice = null, ?string $error = null) use ($app): Response {
    $query = array_filter(['notice' => $notice, 'error' => $error], static fn (?string $w): bool => $w !== null && $w !== '');

    return Response::redirect($app->url('/tools/giveaway') . ($query === [] ? '' : '?' . http_build_query($query)));
};

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$router->get('/tools/giveaway', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'     => translate('giveaway.name'),
        'active'    => 'tools/giveaway',
        'enabled'   => Giveaway::enabled($app),
        'config'    => Giveaway::config($app),
        'prizes'    => Giveaway::prizes($app),
        'draw'      => (new Draw($app))->view(),
        'hasTimers' => $app->plugins->isEnabled('timers'),
        'canEdit'   => $app->auth->can('Giveaway.Global.Edit'),
        'canManage' => $app->auth->can('Giveaway.Global.Manage'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Giveaway.Global.View']);

$router->post('/tools/giveaway', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    $aktion = $request->input('action');
    $draw = new Draw($app);

    // Ziehen und Beenden brauchen ein eigenes Recht, alles andere ist
    // Einstellen.
    $recht = in_array($aktion, ['start', 'next', 'end'], true) ? 'Giveaway.Global.Manage' : 'Giveaway.Global.Edit';
    if (!$app->auth->can($recht)) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    // Die Preise sind waehrend der Ziehung festgehalten. Sie hier
    // trotzdem aendern zu lassen, saehe nach Wirkung aus und haette
    // keine.
    if (str_starts_with($aktion, 'prize_') && $draw->phase() === Draw::DRAW) {
        return $zurueck(null, translate('giveaway.error.prizes_locked'));
    }

    switch ($aktion) {
        case 'start':
            $fehler = $draw->start();

            return $fehler === '' ? $zurueck() : $zurueck(null, $fehler);

        case 'next':
            $fehler = $draw->next();

            return $fehler === '' ? $zurueck() : $zurueck(null, $fehler);

        case 'end':
            $draw->end();

            return $zurueck(translate('giveaway.ended'));

        case 'prize_add':
            $fehler = Giveaway::addPrize($app, $request->input('prize'));

            return $fehler === '' ? $zurueck() : $zurueck(null, $fehler);

        case 'prize_remove':
            Giveaway::removePrize($app, (int) $request->input('index'));

            return $zurueck();

        case 'prize_move':
            Giveaway::movePrize($app, (int) $request->input('index'), $request->input('direction'));

            return $zurueck();

        case 'command':
            $fehler = Giveaway::saveCommand($app, [
                'command'        => $request->input('command'),
                'interval'       => $request->input('interval'),
                'sub_bonus'      => $request->input('sub_bonus'),
                'multiple_wins'  => $request->input('multiple_wins'),
                'message_ok'     => $request->input('message_ok'),
                'message_wait'   => $request->input('message_wait'),
                'message_closed' => $request->input('message_closed'),
                'announce'       => $request->input('announce'),
            ]);

            return $fehler === '' ? $zurueck(translate('giveaway.saved')) : $zurueck(null, $fehler);

        case 'timer':
            Giveaway::saveTimer($app, [
                'timer_enabled'  => $request->input('timer_enabled'),
                'timer_interval' => $request->input('timer_interval'),
                'timer_lines'    => $request->input('timer_lines'),
                'timer_message'  => $request->input('timer_message'),
            ]);

            return $zurueck(translate('giveaway.saved'));
    }

    return $zurueck(null, translate('common.error.unknown_action'));
}, ['auth' => true, 'permission' => 'Giveaway.Global.View']);

// Fuer das Skript der Seite, wenn das Rad steht. Antwortet JSON und
// leitet nicht um - das Skript laedt danach selbst neu.
$router->post('/tools/giveaway/announce', static function (Request $request) use ($app): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return Response::json(['ok' => false], 400);
    }

    if (!$app->auth->can('Giveaway.Global.Manage')) {
        return Response::json(['ok' => false], 403);
    }

    return Response::json(['ok' => (new Draw($app))->announce()]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Der Schalter im Menue
// -------------------------------------------------------------------
$router->post('/tools/giveaway/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    if (!$app->auth->can('Giveaway.Global.Toggle')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    $an = !Giveaway::enabled($app);
    Giveaway::setEnabled($app, $an);
    (new Draw($app))->refreshOverlay();

    // Der Schalter steht auf jeder Seite. Ein fester Rueckweg wuerde
    // einen von dort wegwerfen, wo man gerade war.
    $woher = $request->header('Referer');
    if ($woher !== '' && str_starts_with($woher, $app->url(''))) {
        return Response::redirect($woher);
    }

    return $zurueck($an ? translate('giveaway.turned_on') : translate('giveaway.turned_off'));
}, ['auth' => true]);
