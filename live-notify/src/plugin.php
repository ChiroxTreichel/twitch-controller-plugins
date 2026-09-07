<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Live-Benachrichtigung - Discord
 * ===================================================================
 *
 * Beobachtet fremde Twitch-Kanaele und meldet, wenn einer davon live
 * geht. Dieses Plugin bringt das Ziel Discord mit; Chat und Shoutout
 * kommen als eigene Plugins dazu.
 *
 * Gemeldet wird der UEBERGANG offline -> live und nicht "ist live" -
 * sonst kaeme alle 15 Sekunden eine neue Nachricht. Siehe LiveNotify.
 *
 * Zwei Einhaengepunkte fuer weitere Ziele:
 *
 *   live_notify.targets   ein Ziel anmelden - Name, Reihenfolge, und ob
 *                         es gerade kann
 *   live_notify.live      ein Kanal ist live geworden
 *
 * Das Ziel Discord haengt sich in dieselben Punkte ein wie jedes
 * andere. Das ist Absicht: was fuer den Hausgebrauch nicht reicht,
 * reicht auch fuer fremde Plugins nicht.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\LiveNotify\Discord;
use TwitchController\Plugin\LiveNotify\LiveNotify;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
// Getrennt wie im alten System: wer Kanaele aufnehmen darf, muss nicht
// die Webhook-Adresse aendern duerfen - und wer die Liste pflegt, soll
// nicht zwangslaeufig Kanaele entfernen koennen.
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['LiveNotify'] = [
        'label'       => translate('live_notify.name'),
        'permissions' => [
            'LiveNotify.Global.View'   => translate('live_notify.perm.view'),
            'LiveNotify.Global.Edit'   => translate('live_notify.perm.edit'),
            'LiveNotify.Channels.Add'  => translate('live_notify.perm.add'),
            'LiveNotify.Channels.Delete' => translate('live_notify.perm.delete'),
            'LiveNotify.Global.Test'   => translate('live_notify.perm.test'),
            'LiveNotify.Global.Toggle' => translate('live_notify.perm.toggle'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue und Dateien
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Anhaengen und nicht setzen: in der Gruppe "Networking" haengen
    // auch die Raids, und wer sie setzt, laesst je nach Ladereihenfolge
    // den anderen Menuepunkt verschwinden.
    $nav['networking']['label'] = translate('live_notify.nav.networking');
    $nav['networking']['order'] = 25;
    $nav['networking']['items'][] = [
        'label'      => translate('live_notify.nav.item'),
        'href'       => '/networking/live',
        'permission' => 'LiveNotify.Global.View',
        // Menuepunkt mit Schnellschalter: damit laesst sich die
        // Beobachtung von jeder Seite aus stumm stellen. Genau das
        // will man, wenn ein beobachteter Kanal gerade dauernd an- und
        // ausgeht - und nicht erst, nachdem man hierher navigiert ist.
        'toggle'     => [
            'on'         => LiveNotify::enabled($app),
            'action'     => '/networking/live/toggle',
            'value'      => 'toggle',
            'permission' => 'LiveNotify.Global.Toggle',
            'title'      => translate('live_notify.toggle_hint'),
        ],
    ];

    return $nav;
});

// Die Einstellungen stehen unter Plugins > Einstellungen und nicht auf
// der Kanalseite: dort arbeitet man, und eine Webhook-Adresse, die man
// einmal eintraegt, hat dort nichts zu suchen.
$hooks->on('plugin.settings', static function (array $links): array {
    $links[LiveNotify::SLUG] = [
        'label' => translate('live_notify.settings'),
        'href'  => '/networking/live/settings',
    ];

    return $links;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/live-notify/assets/live-notify.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Die Runde laeuft im Hintergrund
// -------------------------------------------------------------------
$hooks->on('cron.tick', static function () use ($app): void {
    // Gedrosselt in LiveNotify: eine Abfrage fuer alle beobachteten
    // Kanaele, hoechstens alle POLL_SECONDS. Billig - und eine Meldung,
    // die eine Minute zu spaet kommt, ist die halbe Meldung.
    LiveNotify::tick($app);
});

// -------------------------------------------------------------------
//  Das Ziel Discord
// -------------------------------------------------------------------
$hooks->on('live_notify.targets', static function (array $ziele) use ($app): array {
    $hat = Discord::hasWebhook($app);

    $ziele['discord'] = [
        'label' => translate('live_notify.target.discord'),
        'order' => 10,
        // Ohne Adresse kann dieses Ziel nicht, und dann steht der Grund
        // neben dem Haken - statt dass der Haken stillschweigend nichts
        // tut.
        'ready' => $hat,
        'hint'  => $hat ? '' : translate('live_notify.discord.no_webhook_hint'),
    ];

    return $ziele;
});

$hooks->on('live_notify.live', static function (array $info, array $ziele) use ($app): void {
    if (!in_array('discord', $ziele, true)) {
        return;
    }

    $text = LiveNotify::render(Discord::message($app), LiveNotify::values($info));
    $ergebnis = Discord::send($app, $text);

    if ($ergebnis['ok']) {
        $app->log('LiveNotify: Discord-Meldung fuer ' . $info['login'] . ' gesendet.');

        return;
    }

    $app->log('LiveNotify: Discord-Meldung fuer ' . $info['login'] . ' gescheitert: ' . $ergebnis['error']);
});

// -------------------------------------------------------------------
//  Hilfsmittel
// -------------------------------------------------------------------
$zurueck = static function (string $pfad, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

// -------------------------------------------------------------------
//  Der Hauptschalter
// -------------------------------------------------------------------
$router->post('/networking/live/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck('/networking/live', ['error' => translate('common.error.form_expired')]);
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck('/networking/live', ['error' => translate('common.error.unknown_action')]);
    }

    if (!permission('LiveNotify.Global.Toggle')) {
        return $zurueck('/networking/live', ['error' => translate('common.error.no_permission')]);
    }

    $an = !LiveNotify::enabled($app);
    LiveNotify::setEnabled($app, $an);

    // Der Schalter steht in der Seitenleiste, also auf jeder Seite. Ein
    // fester Rueckweg wuerde einen von dort wegwerfen, wo man gerade
    // war.
    $woher = $request->header('Referer');
    $eigene = $app->url('');

    if ($woher !== '' && str_starts_with($woher, $eigene)) {
        return Response::redirect($woher);
    }

    return $zurueck('/networking/live', [
        'notice' => $an ? translate('live_notify.turned_on') : translate('live_notify.turned_off'),
    ]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Kanaele pflegen
// -------------------------------------------------------------------
// Die Formularziele. POST und GET liegen auf denselben Adressen und
// stossen nicht zusammen - der Router unterscheidet die Methode.
$router->post('/networking/live/channels', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/networking/live';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'add') {
        if (!permission('LiveNotify.Channels.Add')) {
            return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
        }

        $login = LiveNotify::add($app, $request->input('login'));

        if ($login === '') {
            return $zurueck($ziel, ['error' => LiveNotify::error()]);
        }

        return $zurueck($ziel, ['notice' => translate('live_notify.added', ['login' => $login])]);
    }

    if ($aktion === 'remove') {
        if (!permission('LiveNotify.Channels.Delete')) {
            return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
        }

        $login = LiveNotify::loginFrom($request);
        LiveNotify::remove($app, $login);

        return $zurueck($ziel, ['notice' => translate('live_notify.removed', ['login' => $login])]);
    }

    return $zurueck($ziel, ['error' => translate('common.error.unknown_action')]);
}, ['auth' => true]);

$router->post('/networking/live/target', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/networking/live';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('LiveNotify.Global.Edit')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    $ok = LiveNotify::setTarget(
        $app,
        $request->input('login'),
        $request->input('target'),
        $request->input('value') === '1'
    );

    if (!$ok) {
        return $zurueck($ziel, ['error' => translate('live_notify.error.unknown_target')]);
    }

    // Ohne Meldung: man sieht den Haken umspringen.
    return $zurueck($ziel);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Discord einstellen und ausprobieren
// -------------------------------------------------------------------
$router->post('/networking/live/settings', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/networking/live/settings';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'test') {
        if (!permission('LiveNotify.Global.Test')) {
            return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
        }

        // Ausprobiert wird mit derselben Vorlage und demselben Weg wie
        // im Betrieb, nur mit erfundenen Werten. Ein Test, der einen
        // anderen Weg nimmt, ist schlimmer als keiner.
        $text = LiveNotify::render(Discord::message($app), LiveNotify::values([
            'login'        => 'twitchdev',
            'display_name' => 'TwitchDev',
            'title'        => translate('live_notify.test_title'),
            'game_name'    => 'Just Chatting',
        ]));

        $ergebnis = Discord::send($app, $text);

        return $ergebnis['ok']
            ? $zurueck($ziel, ['notice' => translate('live_notify.test_sent')])
            : $zurueck($ziel, ['error' => $ergebnis['error']]);
    }

    if (!permission('LiveNotify.Global.Edit')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    // Die Adresse nur anfassen, wenn etwas eingetippt wurde: das Feld
    // steht leer da, weil ein Geheimnis nicht zurueckgeschrieben wird.
    // Ein leeres Feld heisst also "unveraendert" - zum Loeschen gibt es
    // den eigenen Knopf.
    $adresse = trim($request->input('webhook_url'));

    if ($aktion === 'forget') {
        Discord::setWebhook($app, '');

        return $zurueck($ziel, ['notice' => translate('live_notify.webhook_forgotten')]);
    }

    if ($adresse !== '') {
        if (!Discord::looksValid($adresse)) {
            return $zurueck($ziel, ['error' => translate('live_notify.error.bad_webhook')]);
        }

        Discord::setWebhook($app, $adresse);
    }

    Discord::setMessage($app, $request->input('message'));

    return $zurueck($ziel, ['notice' => translate('live_notify.saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Seiten
// -------------------------------------------------------------------
// Zwei Seiten und keine Reiter: die Kanaele sind die Arbeit, die
// Einstellungen stehen unter Plugins > Einstellungen. Ein Reiter
// "Einstellungen" neben "Kanaele" waere derselbe Weg zweimal.
$router->get('/networking/live/settings', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'        => translate('live_notify.settings'),
        'active'       => 'networking/live',
        'hasWebhook'   => Discord::hasWebhook($app),
        'message'      => Discord::message($app),
        'defaultText'  => Discord::DEFAULT_MESSAGE,
        'maxMessage'   => Discord::MAX_MESSAGE,
        'placeholders' => LiveNotify::PLACEHOLDERS,
        'canEdit'      => permission('LiveNotify.Global.Edit'),
        'canTest'      => permission('LiveNotify.Global.Test'),
        'csrf'         => $app->auth->csrfToken(),
        'notice'       => (string) $request->get('notice'),
        'error'        => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'LiveNotify.Global.View']);

$router->get('/networking/live', static function (Request $request) use ($app, $plugin): Response {
    $kanaele = LiveNotify::channels($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'     => translate('live_notify.nav.item'),
        'active'    => 'networking/live',
        'enabled'   => LiveNotify::enabled($app),
        'channels'  => $kanaele,
        // Frisch von Twitch und nicht aus der Tabelle: ein
        // gespeichertes Bild veraltet, sobald jemand sein Bild
        // wechselt. Ein Aufruf fuer alle Kanaele - siehe
        // LiveNotify::profiles().
        'images'    => LiveNotify::profiles(
            $app,
            array_map(static fn (array $k): string => $k['login'], $kanaele)
        ),
        'targets'   => LiveNotify::targets($app),
        'canEdit'   => permission('LiveNotify.Global.Edit'),
        'canAdd'    => permission('LiveNotify.Channels.Add'),
        'canDelete' => permission('LiveNotify.Channels.Delete'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'LiveNotify.Global.View']);
