<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Moderatorenchat
 * ===================================================================
 *
 * Ein Chat nur fuer das Team: wer sich hier anmelden kann, schreibt
 * den anderen etwas zum Stream - "Raid kommt gleich", "XY ist wieder
 * da", "Ton ist weg". Ohne Discord daneben und ohne dass der Twitch-
 * Chat mitliest.
 *
 * Kein eigenes Recht. Angemeldet sein genuegt; siehe Messages.
 *
 * Live heisst hier: das Skript fragt alle zwei Sekunden nach, was seit
 * der letzten bekannten Nummer dazukam. Keine offene Leitung wie beim
 * Overlay - die belegt je offenem Reiter einen PHP-Prozess, und fuer
 * eine Handvoll Leute, die sich gelegentlich etwas schreiben, ist eine
 * kurze Abfrage je Takt das Billigere.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\ModChat\Messages;

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav): array {
    // Anhaengen, nicht die Gruppe setzen: Streaminfo haengt in
    // dieselbe, und wer sie ueberschreibt, laesst je nach
    // Ladereihenfolge dessen Menuepunkt verschwinden.
    $nav['stream']['label'] = translate('mod_chat.nav.stream');
    $nav['stream']['order'] = 15;
    $nav['stream']['items'][] = [
        'label' => translate('mod_chat.name'),
        'href'  => '/stream/chat',
        // Ohne 'permission': jeder Angemeldete sieht den Punkt.
    ];

    return $nav;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/mod-chat/assets/mod-chat.css');
    $assets['js'][] = $app->asset('/plugin/mod-chat/assets/mod-chat.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Abraeumen
// -------------------------------------------------------------------
// Jeden Takt, wie der Kern seinen Twitch-Chat: dank Index ist das eine
// Abfrage, die fast immer nichts findet - billiger als sich zu merken,
// wann zuletzt aufgeraeumt wurde.
$hooks->on('cron.tick', static function () use ($app): void {
    (new Messages($app))->prune();
});

// -------------------------------------------------------------------
//  Hilfsmittel fuer die Routen
// -------------------------------------------------------------------
$meineId = static function () use ($app): string {
    return (string) ($app->auth->user()['twitch_id'] ?? '');
};

/**
 * Was Seite und Dock dem Skript mitgeben.
 *
 * @param list<array<string, mixed>> $nachrichten
 * @return array<string, mixed>
 */
$einstellungen = static function (array $nachrichten) use ($app): array {
    $letzte = $nachrichten === [] ? 0 : (int) end($nachrichten)['id'];

    return [
        'last'  => $letzte,
        'urls'  => [
            'messages' => $app->url('/stream/chat/messages'),
        ],
        'texts' => [
            'empty'       => translate('mod_chat.empty'),
            'sessionLost' => translate('mod_chat.error.session_lost'),
            'sendFailed'  => translate('mod_chat.error.send_failed'),
        ],
    ];
};

// -------------------------------------------------------------------
//  Die Seite - und das Dock fuer OBS
// -------------------------------------------------------------------
$router->get('/stream/chat', static function (Request $request) use ($app, $plugin, $meineId, $einstellungen): Response {
    $ich = $meineId();
    $nachrichten = array_map(
        static fn (array $zeile): array => Messages::present($zeile, $ich),
        (new Messages($app))->latest()
    );

    $ansicht = $app->view->from($plugin->directory . '/views');

    // Das Dock ist eine eigene, nackte Seite: ohne Menue, ohne
    // Eingabefeld. Es haengt in OBS beim Streamer, der dort mitliest
    // und nicht schreibt - ein Feld, in das man beim Klicken in die
    // Vorschau versehentlich tippt, waere dort nur im Weg.
    if ($request->get('compact') !== '') {
        return Response::html($ansicht->render('dock', [
            'title'    => translate('mod_chat.name'),
            'messages' => $nachrichten,
            'config'   => $einstellungen($nachrichten),
        ], null));
    }

    return Response::html($ansicht->render('page', [
        'title'     => translate('mod_chat.name'),
        'active'    => 'stream/chat',
        'messages'  => $nachrichten,
        'config'    => $einstellungen($nachrichten),
        'csrf'      => $app->auth->csrfToken(),
        'maxLength' => Messages::MAX_LENGTH,
        'dockUrl'   => $app->url('/stream/chat?compact=1'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true]);

// Ohne Skript: ein gewoehnliches Formular, danach zurueck auf die Seite.
$router->post('/stream/chat', static function (Request $request) use ($app): Response {
    $fehler = $app->auth->checkCsrf($request->input('csrf'))
        ? (new Messages($app))->add((array) $app->auth->user(), $request->input('text'))
        : translate('common.error.form_expired');

    return Response::redirect(
        $app->url('/stream/chat') . ($fehler === '' ? '' : '?' . http_build_query(['error' => $fehler]))
    );
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Fuer das Skript: nachladen und senden
// -------------------------------------------------------------------
// Ohne 'auth' im Router, und das mit Absicht: der leitet ein
// abgelaufenes Login auf die Anmeldeseite um. fetch() folgt dem, und im
// Skript kaeme statt JSON eine HTML-Seite an - es koennte nicht
// unterscheiden zwischen "abgemeldet" und "Server hat gehustet". Ein
// 401 kann es.
$router->get('/stream/chat/messages', static function (Request $request) use ($app, $meineId): Response {
    if (!$app->auth->isLoggedIn()) {
        return Response::json(['error' => translate('mod_chat.error.session_lost')], 401);
    }

    $ich = $meineId();
    $neu = (new Messages($app))->after(max(0, (int) $request->get('after')));

    return Response::json([
        'messages' => array_map(
            static fn (array $zeile): array => Messages::present($zeile, $ich),
            $neu
        ),
    ]);
});

$router->post('/stream/chat/messages', static function (Request $request) use ($app): Response {
    if (!$app->auth->isLoggedIn()) {
        return Response::json(['error' => translate('mod_chat.error.session_lost')], 401);
    }

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return Response::json(['error' => translate('common.error.form_expired')], 400);
    }

    $fehler = (new Messages($app))->add((array) $app->auth->user(), $request->input('text'));

    // Die neue Nachricht kommt NICHT mit zurueck. Das Skript holt sie
    // mit dem naechsten Nachladen, zusammen mit allem, was andere
    // inzwischen geschrieben haben - so steht sie an ihrem Platz in der
    // Reihenfolge und nie doppelt.
    return $fehler === ''
        ? Response::json(['ok' => true])
        : Response::json(['error' => $fehler], 422);
});
