<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Tip-Goals
 * ===================================================================
 *
 * Eine Spendenseite unter /tips und die Ziele, auf die gespendet wird.
 *
 * Wie das Geld fliesst, weiss dieses Plugin NICHT. Das bringt ein
 * Anbieter-Plugin mit - PayPal zum Beispiel - und meldet sich ueber
 * tips.providers an (siehe Providers). Ohne einen bleibt die Seite zu;
 * mit mehreren waehlt der Spender.
 *
 * Der Unterschied zu den Tip-Goals von StreamElements und Streamlabs:
 * dort kommt eine Spende aus einer Schnittstelle und weiss nichts von
 * Zielen, also landet sie auf dem obersten. Hier waehlt der SPENDER,
 * und das ist der Sinn der Seite. Darum vertragen sich die drei nicht -
 * nebeneinander zaehlte jede Spende doppelt.
 *
 * Die oeffentliche Seite legt KEIN Konto an: wer sich dort anmeldet,
 * bekommt ein signiertes Cookie mit seinem Twitch-Namen, und das Token
 * aus der Anmeldung wird weggeworfen. Wie bei /raidme.
 *
 * Ohne Impressum geht die Seite NICHT online. Eine oeffentliche Seite,
 * die Geld annimmt, braucht es - und ein Plugin aus dem Katalog darf
 * keines mitbringen, sonst betreibt der Naechste eine Spendenseite mit
 * fremden Angaben.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Alerts\Alerts;
use TwitchController\Plugin\Goals\Goals;
use TwitchController\Plugin\TipGoals\Donations;
use TwitchController\Plugin\TipGoals\Legal;
use TwitchController\Plugin\TipGoals\Providers;
use TwitchController\Plugin\TipGoals\PublicPage;
use TwitchController\Plugin\TipGoals\TipAlert;
use TwitchController\Plugin\TipGoals\TipGoals;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['TipGoals'] = [
        'label'       => translate('tips.name'),
        'permissions' => [
            'TipGoals.Global.View' => translate('tips.perm.view'),
            'TipGoals.Global.Edit' => translate('tips.perm.edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Dateien und Einstellungen
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/tip-goals/assets/tip-goals.css');

    return $assets;
});

/*
 * Im Overlay: durch die Ziele rotieren.
 *
 * Nur hier und nicht bei StreamElements und Streamlabs - auf
 * dieser Seite waehlt der Spender sein Ziel, also ist jedes in der
 * Liste eines, auf das gerade eingezahlt werden kann. Wo die Spende
 * aus einer Schnittstelle kommt und immer auf dem obersten landet,
 * zeigte ein rotierender Balken ein Ziel, auf das nichts einzahlen
 * kann.
 *
 * Nach Goals: das Skript braucht dessen GOALS.patch. Die Reihenfolge
 * ergibt sich von selbst, weil dieses Plugin Goals voraussetzt und
 * die Ladereihenfolge den Abhaengigkeiten folgt.
 */
$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    $assets['js'][] = $app->asset('/plugin/tip-goals/assets/tips-overlay.js');

    return $assets;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[TipGoals::SLUG] = [
        'label' => translate('tips.settings'),
        'href'  => '/display/goals/tips/settings',
    ];

    $links[TipGoals::SLUG . ':appearance'] = [
        'label' => translate('tips.appearance'),
        'href'  => '/display/goals/tips/appearance',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Reiter auf der Goals-Seite
// -------------------------------------------------------------------
$hooks->on('goals.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('TipGoals.Global.View')) {
        return $tabs;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    $tabs['tips'] = [
        'label' => translate('tips.tab'),
        'order' => 20,
        'render' => static fn (): string => $vorlagen->render('tab', [
            'goals'     => TipGoals::all($app),
            'maxGoals'  => TipGoals::MAX_GOALS,
            'ready'     => Legal::pageReady($app),
            'lastError' => TipGoals::lastError($app),
            'providers' => Providers::all($app),
            'publicUrl' => $app->url('/tips'),
            'recent'    => Donations::recent($app, 10),
            'canEdit'   => permission('TipGoals.Global.Edit'),
            'csrf'      => $app->auth->csrfToken(),
        ], null),
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Der Balken im Overlay
// -------------------------------------------------------------------
$hooks->on('goals.markup', static function (array $teile) use ($app): array {
    $teile[TipGoals::SLUG] = [
        'order' => 20,
        'html'  => TipGoals::html($app),
        'css'   => TipGoals::css($app),
    ];

    return $teile;
});

$hooks->on('goals.state', static function (array $zustand) use ($app): array {
    return array_merge($zustand, TipGoals::values($app));
});

$hooks->on('goals.stamp', static function (mixed $stempel) use ($app): int {
    return max((int) $stempel, TipGoals::stamp($app));
});

// -------------------------------------------------------------------
//  Aufraeumen
// -------------------------------------------------------------------
// Gedrosselt in Donations::cleanup(): hoechstens einmal am Tag.
$hooks->on('cron.tick', static function () use ($app): void {
    Donations::cleanup($app);
});

// -------------------------------------------------------------------
//  Die Zielliste
// -------------------------------------------------------------------
$zurueck = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/display/goals/tips', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('TipGoals.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'add') {
        if (!TipGoals::add($app)) {
            return $zurueck(['error' => translate('tips.too_many', [
                'max' => (string) TipGoals::MAX_GOALS,
            ])]);
        }

        return $zurueck();
    }

    if ($aktion === 'remove') {
        TipGoals::remove($app, (int) $request->input('id'));
        TipGoals::push($app);

        return $zurueck(['notice' => translate('tips.removed')]);
    }

    if ($aktion === 'up' || $aktion === 'down') {
        TipGoals::move($app, (int) $request->input('id'), $aktion === 'up' ? -1 : 1);
        TipGoals::push($app);

        return $zurueck();
    }

    $eingaben = $request->post['goals'] ?? [];
    TipGoals::save($app, is_array($eingaben) ? $eingaben : []);
    TipGoals::push($app);

    return $zurueck(['notice' => translate('tips.saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Einstellungen: die Seite, Impressum, Datenschutz, AGB
// -------------------------------------------------------------------
//
//  Vier Reiter auf EINER Seite und nicht vier Eintraege in der
//  Plugin-Liste: es ist eine Einrichtung mit vier Abschnitten - und wer
//  das Impressum schreibt, will danach die AGB schreiben, ohne dafuer
//  zwei Ebenen hoch zu navigieren.
//
//  Die Zahlungsanbieter haben ihre EIGENE Seite, in ihrem Plugin. Hier
//  steht nur, welche es gibt und ob sie bereit sind.
$zurueckEinst = static function (string $reiter, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/settings/' . rawurlencode($reiter))
        . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$einstellungen = static function (Request $request, array $params = []) use ($app, $plugin): Response {
    $reiter = strtolower(trim((string) ($params['tab'] ?? 'page')));

    // Ein unbekannter Reitername fuehrt auf den ersten und nicht auf
    // eine Fehlerseite - die Adresse kann aus einem Lesezeichen kommen,
    // etwa noch mit "paypal" aus der Zeit, als das hier stand.
    if ($reiter !== 'page' && !Legal::isPage($reiter)) {
        $reiter = 'page';
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'     => translate('tips.settings'),
        'active'    => 'display/goals',
        'tab'       => $reiter,
        'pages'     => Legal::PAGES,
        'text'      => $reiter === 'page' ? '' : Legal::text($app, $reiter),
        'maxLength' => Legal::MAX_LENGTH,

        'providers'     => Providers::all($app),
        'brand'         => $app->settings->string('brand', '', TipGoals::scope()),
        'presets'       => $app->settings->string('presets', '1, 2, 5, 10', TipGoals::scope()),
        'minAmount'     => $app->settings->string('min_amount', '1', TipGoals::scope()),
        'defaultAmount' => $app->settings->string('default_amount', '5', TipGoals::scope()),

        'pageReady' => Legal::pageReady($app),
        'publicUrl' => $app->url('/tips'),
        'canEdit'   => permission('TipGoals.Global.Edit'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
};

// Ohne Reiter zuerst - sonst faengt {tab} den Aufruf.
$router->get('/display/goals/tips/settings', $einstellungen, [
    'auth' => true, 'permission' => 'TipGoals.Global.View',
]);
$router->get('/display/goals/tips/settings/{tab}', $einstellungen, [
    'auth' => true, 'permission' => 'TipGoals.Global.View',
]);

$router->post('/display/goals/tips/settings', static function (Request $request) use ($app, $zurueckEinst): Response {
    $reiter = strtolower(trim($request->input('tab')));
    if ($reiter !== 'page' && !Legal::isPage($reiter)) {
        $reiter = 'page';
    }

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckEinst($reiter, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('TipGoals.Global.Edit')) {
        return $zurueckEinst($reiter, ['error' => translate('common.error.no_permission')]);
    }

    if ($reiter !== 'page') {
        Legal::save($app, $reiter, $request->input('text'));

        return $zurueckEinst($reiter, ['notice' => translate('tips.legal_saved')]);
    }

    $app->settings->setMany([
        'brand'          => trim($request->input('brand')),
        'presets'        => trim($request->input('presets')),
        'min_amount'     => TipGoals::money($request->input('min_amount')),
        'default_amount' => TipGoals::money($request->input('default_amount')),
    ], TipGoals::scope());

    return $zurueckEinst('page', ['notice' => translate('tips.settings_saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Das Aussehen des Balkens
// -------------------------------------------------------------------
$zurueckAussehen = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/appearance') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/tips/appearance', static function (Request $request) use ($app, $plugin): Response {
    $html = TipGoals::html($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('appearance', [
        'title'    => translate('tips.appearance'),
        'active'   => 'display/goals',
        'html'     => $html,
        'css'      => TipGoals::css($app),
        'custom'   => TipGoals::isCustom($app),
        'missing'  => Goals::missing($html, TipGoals::REQUIRED_BINDINGS, TipGoals::REQUIRED_FILLS),
        'required' => [
            'tip_title'   => translate('tips.bind.title'),
            'tip_current' => translate('tips.bind.current'),
            'tip_goal'    => translate('tips.bind.goal'),
        ],
        'fills'    => ['tip' => translate('tips.bind.fill')],
        'canEdit'  => permission('TipGoals.Global.Edit'),
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => (string) $request->get('notice'),
        'error'    => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'TipGoals.Global.View']);

$router->post('/display/goals/tips/appearance', static function (Request $request) use ($app, $zurueckAussehen): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckAussehen(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('TipGoals.Global.Edit')) {
        return $zurueckAussehen(['error' => translate('common.error.no_permission')]);
    }

    if ($request->input('action') === 'reset') {
        TipGoals::resetAppearance($app);

        return $zurueckAussehen(['notice' => translate('tips.reset_done')]);
    }

    $fehlend = TipGoals::saveAppearance($app, $request->input('html'), $request->input('css'));

    return $zurueckAussehen($fehlend === []
        ? ['notice' => translate('tips.appearance_saved')]
        : ['error' => translate('tips.missing_hint') . ' ' . implode(', ', $fehlend)]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die oeffentliche Seite: /tips
// -------------------------------------------------------------------
//
//  Ohne 'auth' - das ist der Sinn. Was hier passieren kann, ist genau
//  eines: sich selbst eine Spende vormerken und zum Anbieter geschickt
//  werden. Kein Recht, keine Einstellung, keine Auskunft ueber andere.
//
//  Zu bleibt die Seite ohne Impressum - eine Seite, die Geld annimmt,
//  muss sagen, wer es bekommt - und ohne einen einzigen Anbieter: dann
//  gibt es keinen Weg, auf dem Geld fliessen koennte.
//
//  Die Reihenfolge der Routen zaehlt. Der Router nimmt die ERSTE, die
//  passt, und /tips/{page} passt auf jede Adresse mit einem Abschnitt.
//  Darum steht sie ganz unten - in der ersten Fassung stand sie ueber
//  /tips/return, und die Rueckkehr von PayPal landete auf "Gerade
//  geschlossen". Die Anbieter bleiben mit /tips/<schluessel>/... ganz
//  aus ihrem Weg.

/** Kann die Seite ueberhaupt aufgehen? */
$offen = static fn (): bool => Legal::pageReady($app) && Providers::all($app) !== [];

$router->get('/tips', static function (Request $request) use ($app, $offen): Response {
    if (!$offen()) {
        // 503 und nicht 404: die Seite gibt es, sie ist nur nicht
        // fertig eingerichtet. Ein Suchdienst soll sie nicht als
        // dauerhaft weg vermerken.
        //
        // Und kein Wort darueber, WAS fehlt: das ist eine Auskunft fuer
        // den Betreiber, nicht fuer jeden im Netz.
        return PublicPage::closed($app);
    }

    $bereit = Providers::ready($app);

    // Die Werte kommen HIER aus den Einstellungen und nicht in der
    // Vorlage: eine Vorlage, die selbst in die Datenbank greift, laesst
    // sich nicht ohne eine pruefen - und genau daran ist der
    // Renderdurchlauf haengengeblieben.
    return PublicPage::render($app, 'landing', [
        'goals'     => TipGoals::all($app),
        // Ohne den Aufruf 'start' - der gehoert nicht in eine Vorlage.
        'providers' => array_map(
            static fn (array $eintrag): array => array_diff_key($eintrag, ['start' => true]),
            array_values($bereit)
        ),
        'minimum'   => TipGoals::minAmount($app),
        'maximum'   => TipGoals::MAX_AMOUNT,
        'vorgabe'   => TipGoals::defaultAmount($app),
        'presets'   => TipGoals::presets($app),
        'csrf'      => Donations::csrfToken($app),
        'notice'    => PublicPage::message($app, (string) $request->get('notice')),
        'error'     => PublicPage::message($app, (string) $request->get('error')),
    ]);
});

// Die Anmeldung. Ohne Freigaben: gebraucht wird nur die Auskunft, wer
// da ist - und je weniger man verlangt, desto eher drueckt jemand auf
// "erlauben".
$router->get('/tips/login', static function () use ($app): Response {
    return Response::redirect($app->twitch->oauth()->authorizeUrl(Donations::PURPOSE, [], false));
});

$router->get('/tips/logout', static function () use ($app): Response {
    Donations::forget($app);

    return Response::redirect($app->url('/tips'));
});

// -------------------------------------------------------------------
//  Spenden
// -------------------------------------------------------------------
$router->post('/tips', static function (Request $request) use ($app, $offen): Response {
    if (!$offen()) {
        return PublicPage::back($app);
    }

    if (!Donations::checkCsrf($app, $request->input('csrf'))) {
        return PublicPage::back($app, '', 'form_expired');
    }

    $wer = Donations::identity($app);
    if ($wer === null) {
        return PublicPage::back($app, '', 'not_signed_in');
    }

    // Der Anbieter. Ist nur einer bereit, muss niemand waehlen - und
    // das Formular zeigt dann auch keine Wahl. Sonst muss es einer der
    // bereiten sein: ein Formular kann alt sein, und in der
    // Zwischenzeit wurde vielleicht ein Zugang geloescht.
    $bereit = Providers::ready($app);
    $gewaehlt = strtolower(trim($request->input('provider')));

    if ($gewaehlt === '' && count($bereit) === 1) {
        $gewaehlt = (string) array_key_first($bereit);
    }

    $anbieter = $bereit[$gewaehlt] ?? null;
    if ($anbieter === null) {
        return PublicPage::back($app, '', 'no_provider');
    }

    // Das Haekchen steht immer da - es traegt auch die
    // Altersbestaetigung. Geprueft wird es hier und nicht nur im
    // Browser: required im Formular ist eine Bequemlichkeit, keine
    // Bedingung.
    if ($request->input('accept_terms') !== '1') {
        return PublicPage::back($app, '', 'terms');
    }

    // Das Ziel: leer oder "none" heisst "einfach so". Sonst muss es das
    // Ziel geben - ein Formular kann alt sein, und in der Zwischenzeit
    // wurde vielleicht geloescht.
    $zielRoh = trim($request->input('goal'));
    $zielId = null;

    if ($zielRoh !== '' && $zielRoh !== 'none') {
        if (!ctype_digit($zielRoh) || !TipGoals::exists($app, (int) $zielRoh)) {
            return PublicPage::back($app, '', 'goal_gone');
        }

        $zielId = (int) $zielRoh;
    }

    $betrag = (float) TipGoals::money($request->input('amount'));

    // Wer die Gebuehren uebernimmt, hat den Betrag gemeint, der ANKOMMT.
    // Gerechnet mit den Saetzen DIESES Anbieters.
    if ($request->input('cover_fees') === '1') {
        $betrag = TipGoals::gross($betrag, $anbieter['fee_percent'], $anbieter['fee_fixed']);
    }

    if ($betrag < TipGoals::minAmount($app)) {
        return PublicPage::back($app, '', 'too_small');
    }

    if ($betrag > TipGoals::MAX_AMOUNT) {
        return PublicPage::back($app, '', 'too_large');
    }

    $nachricht = trim($request->input('message'));
    if ($nachricht !== '' && preg_match('/^.{0,280}/us', $nachricht, $treffer) === 1) {
        $nachricht = $treffer[0];
    }

    $merker = Donations::create(
        $app,
        $anbieter['key'],
        $wer,
        $betrag,
        $nachricht !== '' ? $nachricht : null,
        $request->input('anonymous') === '1',
        $zielId
    );

    try {
        $antwort = ($anbieter['start'])([
            'token'        => $merker,
            'amount'       => $betrag,
            'login'        => $wer['login'],
            'display_name' => $wer['display_name'],
            'goal_id'      => $zielId,
            'description'  => translate('tips.order_description', ['name' => $wer['login']]),
        ]);

        if (!$antwort instanceof Response) {
            throw new RuntimeException('start() hat keine Antwort geliefert.');
        }
    } catch (Throwable $e) {
        Donations::markCancelled($app, $merker);

        // Der Text geht an den Betreiber und ins Log - der Spender
        // bekommt einen allgemeinen Satz. Was ein Anbieter im
        // Fehlerfall zurueckgibt, ist nicht fuer jeden im Netz.
        TipGoals::setLastError($app, $anbieter['label'] . ': ' . $e->getMessage());
        $app->log(TipGoals::SLUG . ': Zahlung ueber ' . $anbieter['key'] . ' fuer ' . $wer['login']
            . ' gescheitert: ' . $e->getMessage());

        return PublicPage::back($app, '', 'start_failed');
    }

    // Ab hier ist der Spender beim Anbieter.
    return $antwort;
});

/** Die Rechtstexte - jeder unter seinem eigenen Namen. Als LETZTE Route. */
$router->get('/tips/{page}', static function (Request $request, array $params) use ($app): Response {
    $schluessel = strtolower(trim((string) ($params['page'] ?? '')));

    if (!Legal::isPage($schluessel) || Legal::text($app, $schluessel) === '') {
        return PublicPage::closed($app, 404);
    }

    return PublicPage::render($app, 'legal', [
        'heading' => Legal::title($schluessel),
        'body'    => Legal::html($app, $schluessel),
    ]);
});

// -------------------------------------------------------------------
//  Der Rueckweg von Twitch
// -------------------------------------------------------------------
// Das Token wird NICHT gespeichert: gebraucht wird die Auskunft, wer da
// ist, und ein Token eines fremden Kanals waere ein Schluessel, fuer
// den es kein Schloss gibt.
$hooks->on('core.oauth.callback', static function (
    mixed $behandelt,
    string $zweck,
    array $token,
    array $twitchUser
) use ($app): mixed {
    if ($behandelt instanceof Response || $zweck !== Donations::PURPOSE) {
        return $behandelt;
    }

    $login = strtolower(trim((string) ($twitchUser['login'] ?? '')));

    if ($login === '') {
        return PublicPage::back($app, '', 'no_identity');
    }

    Donations::remember(
        $app,
        $login,
        (string) ($twitchUser['display_name'] ?? $login),
        (string) ($twitchUser['id'] ?? '')
    );

    return Response::redirect($app->url('/tips'));
});

// -------------------------------------------------------------------
//  Der Alert im Stream
// -------------------------------------------------------------------
//
//  Nur, wenn Alerts installiert ist - darum "optional" im Manifest und
//  eine Pruefung hier. Fehlt es, passiert nichts: die Spende ist
//  trotzdem gebucht, der Balken waechst, und im Stream bleibt es still.
//
//  Der eigene Hook tips.donation steht davor - ausgeloest von
//  Donations::complete(), egal ueber welchen Anbieter. So kann ein anderes
//  Plugin dasselbe Ereignis abgreifen - etwa fuer eine Chatnachricht -
//  ohne dass dieses hier davon wissen muss.
$hooks->on('tips.donation', static function (array $spende) use ($app): void {
    TipAlert::fire($app, $spende);
});

// -------------------------------------------------------------------
//  Der Reiter auf der Alerts-Seite
// -------------------------------------------------------------------
//
//  Er fehlte, und damit fehlte jede Einstellmoeglichkeit: der Alert
//  feuerte mit einem festen Vorgabetext, ohne Video, ohne Ton, ohne
//  Dauer. Im alten System hiess dieser Reiter "Spende".
$hooks->on('alerts.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('TipGoals.Global.View')) {
        return $tabs;
    }

    $tabs['tips'] = [
        'label' => translate('tips.alert.tab'),
        'order' => 60,
        // Wird nur fuer den offenen Reiter aufgerufen.
        'render' => static fn (): string => $app->view
            ->from($plugin->directory . '/views')
            ->render('alert_tab', [
                'config'          => TipAlert::config($app),
                'placeholders'    => TipAlert::PLACEHOLDERS,
                'preview'         => TipAlert::values([
                    'name'    => 'Bukanier',
                    'amount'  => 5.0,
                    'message' => translate('tips.alert.test_message'),
                ]),
                'target'          => $app->url('/display/alerts/tips'),
                'canEdit'         => permission('TipGoals.Global.Edit'),
                'canToggle'       => permission('TipGoals.Global.Edit'),
                'canTest'         => permission('TipGoals.Global.Edit'),
                'defaultDuration' => Alerts::DEFAULT_DURATION,
                'maxText'         => TipAlert::MAX_TEXT,
                'maxTiers'        => TipAlert::MAX_TIERS,
                'csrf'            => $app->auth->csrfToken(),
            ], null),
    ];

    return $tabs;
});

$router->post('/display/alerts/tips', static function (Request $request) use ($app): Response {
    $zurueck = static function (?string $notice, ?string $error = null) use ($app): Response {
        $query = array_filter([
            'notice' => $notice,
            'error'  => $error,
        ], static fn (?string $wert): bool => $wert !== null);

        return Response::redirect(
            $app->url('/display/alerts/tips')
            . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if (!permission('TipGoals.Global.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    switch ($request->input('action')) {
        case 'toggle':
            $an = !TipAlert::config($app)['enabled'];
            TipAlert::setEnabled($app, $an);

            return $zurueck($an
                ? translate('tips.alert.turned_on')
                : translate('tips.alert.turned_off'));

        case 'test':
            // Der Hauptschalter von Alerts steht darueber. Ohne diesen
            // Hinweis sucht man den Fehler beim Text.
            if (!Alerts::enabled($app)) {
                return $zurueck(null, translate('tips.alert.test_while_all_off'));
            }

            if (!TipAlert::config($app)['enabled']) {
                return $zurueck(null, translate('tips.alert.test_while_off'));
            }

            // Die Werte aus dem Formular gelten fuer diesen einen Test
            // und werden nicht gespeichert.
            $werte = $request->post['preview'] ?? [];
            $werte = is_array($werte) ? array_map('strval', $werte) : [];

            $config = TipAlert::config($app);

            /*
             * Die Stufe zum eingetippten Betrag - dieselbe, die eine
             * echte Spende dieser Hoehe ausloesen wuerde.
             *
             * Sonst zeigte der Test immer die unterste, und wer eine
             * Stufe fuer 50 Euro einrichtet, koennte sie nie ansehen,
             * ohne 50 Euro zu spenden.
             */
            $stufe = TipAlert::tierFor(
                $config['tiers'],
                TipAlert::amountFrom((string) ($werte['amount'] ?? ''))
            );

            $ok = Alerts::send($app, [
                'kind'     => 'tip',
                'text'     => $stufe['text'],
                'video'    => $stufe['video'],
                'audio'    => $stufe['audio'],
                'duration' => $stufe['duration'],
                'values'   => $werte,
            ]);

            return $ok
                ? $zurueck(translate('tips.alert.test_sent'))
                : $zurueck(null, translate('tips.alert.test_failed'));

        case 'save':
            /*
             * Die Stufen kommen als Feld an: tiers[0][min_amount],
             * tiers[0][text] und so fort. $request->input() liefert
             * nur Zeichenketten - ein verschachteltes Feld muss aus
             * post kommen.
             */
            $stufen = $request->post['tiers'] ?? [];
            $stufen = is_array($stufen) ? array_values($stufen) : [];

            /*
             * Hinzufuegen und Entfernen laufen ueber dasselbe
             * Formular: so bleibt stehen, was daneben schon eingetippt
             * ist. Gespeichert wird dabei mit - das ist der Preis
             * dafuer, dass es ohne JavaScript funktioniert.
             */
            $weg = $request->input('remove_tier');

            if ($weg !== '' && is_numeric($weg)) {
                $stufen = TipAlert::withoutTier($stufen, (int) $weg);
            } elseif ($request->input('add_tier') !== '') {
                $stufen = TipAlert::withNewTier($stufen);
            }

            TipAlert::save($app, ['tiers' => $stufen]);

            return $zurueck(translate('tips.alert.saved'));
    }

    return $zurueck(null, translate('common.error.unknown_action'));
}, ['auth' => true]);
