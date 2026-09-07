<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Live-Benachrichtigung - Chat
 * ===================================================================
 *
 * Meldet im eigenen Twitch-Chat, wenn ein beobachteter Kanal live
 * geht. Ein Ziel wie Discord, nur anderswohin.
 *
 * Die eine Regel, die dieses Ziel von den anderen unterscheidet: es
 * schreibt NUR, waehrend der eigene Stream laeuft. Ein Post in einen
 * Chat, in dem niemand ist, geht nicht verloren - er steht dort, wenn
 * der naechste Stream anfaengt, und wirkt dann wie eine Meldung von
 * gerade. Das alte System hat das ebenso gemacht, aus demselben Grund.
 *
 * Gesendet wird ueber die Kernfaehigkeit Chat und nicht ueber einen
 * eigenen Weg zu Helix. Sie kennt den richtigen Absender - Bot-Konto,
 * wenn eines verbunden ist, sonst den Kanalinhaber -, kuerzt auf 500
 * Zeichen und merkt sich die eigene Nachricht, damit kein Chatbefehl
 * auf sie anspringt.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Chat\Chat;
use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\LiveNotify\LiveNotify;
use TwitchController\Plugin\LiveNotifyChat\ChatTarget;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
// Nur eines: die Vorlage aendern. Wer den HAKEN je Kanal setzen darf,
// entscheidet das Basis-Plugin mit LiveNotify.Global.Edit - eine eigene
// Erlaubnis dafuer waere ein zweites Schloss an derselben Tuer.
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['LiveNotifyChat'] = [
        'label'       => translate('ln_chat.name'),
        'permissions' => [
            'LiveNotifyChat.Global.Edit' => translate('ln_chat.perm.edit'),
        ],
    ];

    return $catalog;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[ChatTarget::SLUG] = [
        'label' => translate('ln_chat.name'),
        'href'  => '/networking/live/chat',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Das Ziel
// -------------------------------------------------------------------
$hooks->on('live_notify.targets', static function (array $ziele) use ($app): array {
    $kann = ChatTarget::canSend($app);

    $ziele['chat'] = [
        'label' => translate('ln_chat.target'),
        'order' => 20,
        // Ohne Absender kann dieses Ziel nicht. Dann steht der Grund
        // oben auf der Seite, statt dass der Haken stillschweigend
        // nichts tut.
        'ready' => $kann,
        'hint'  => $kann ? '' : translate('ln_chat.no_sender_hint'),
    ];

    return $ziele;
});

$hooks->on('live_notify.live', static function (array $info, array $ziele) use ($app): void {
    if (!in_array('chat', $ziele, true)) {
        return;
    }

    $login = (string) ($info['login'] ?? '');

    // Die Regel dieses Ziels: nur waehrend der eigene Stream laeuft.
    if (!LiveNotify::broadcasterIsLive($app)) {
        $app->log('LiveNotifyChat: Meldung fuer ' . $login . ' uebersprungen, eigener Kanal ist nicht live.');

        return;
    }

    $text = LiveNotify::render(ChatTarget::message($app), LiveNotify::values($info));
    $ergebnis = (new Chat($app))->send($text);

    if (($ergebnis['ok'] ?? false) === true) {
        $app->log('LiveNotifyChat: Meldung fuer ' . $login . ' gesendet.');

        return;
    }

    $app->log('LiveNotifyChat: Meldung fuer ' . $login . ' gescheitert: ' . (string) ($ergebnis['error'] ?? ''));
});

// -------------------------------------------------------------------
//  Die Einstellungen
// -------------------------------------------------------------------
$zurueck = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/networking/live/chat') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/networking/live/chat', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'        => translate('ln_chat.name'),
        'active'       => 'networking/live',
        'message'      => ChatTarget::message($app),
        'defaultText'  => ChatTarget::DEFAULT_MESSAGE,
        'maxMessage'   => ChatTarget::MAX_MESSAGE,
        'placeholders' => LiveNotify::PLACEHOLDERS,
        'canSend'      => ChatTarget::canSend($app),
        'canEdit'      => permission('LiveNotifyChat.Global.Edit'),
        'csrf'         => $app->auth->csrfToken(),
        'notice'       => (string) $request->get('notice'),
        'error'        => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'LiveNotify.Global.View']);

$router->post('/networking/live/chat', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('LiveNotifyChat.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    ChatTarget::setMessage($app, $request->input('message'));

    return $zurueck(['notice' => translate('ln_chat.saved')]);
}, ['auth' => true]);
