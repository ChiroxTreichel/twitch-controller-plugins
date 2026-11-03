<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Chat - Auto-Shoutout
 * ===================================================================
 *
 * Eine Liste von Kanaelen wie bei der Live-Benachrichtigung. Schreibt
 * einer davon in diesem Stream zum ersten Mal in den Chat, geht ein
 * Twitch-Shoutout auf ihn hinaus. Siehe AutoShoutout.
 *
 * Nur waehrend der eigene Kanal live ist. Offline wird still
 * abgebrochen - ohne Meldung und ohne die Nachricht zu zaehlen.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\AutoShoutout\AutoShoutout;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['AutoShoutout'] = [
        'label'       => translate('auto_shoutout.name'),
        'permissions' => [
            'AutoShoutout.Global.View'     => translate('auto_shoutout.perm.view'),
            'AutoShoutout.Global.Toggle'   => translate('auto_shoutout.perm.toggle'),
            'AutoShoutout.Global.Reset'    => translate('auto_shoutout.perm.reset'),
            'AutoShoutout.Channels.Add'    => translate('auto_shoutout.perm.add'),
            'AutoShoutout.Channels.Delete' => translate('auto_shoutout.perm.delete'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Twitch-Freigabe
// -------------------------------------------------------------------
$hooks->on('core.twitch.broadcaster_scopes', static function (array $scopes): array {
    $scopes[] = AutoShoutout::SCOPE;

    return $scopes;
});

// -------------------------------------------------------------------
//  Menue und Dateien
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Anhaengen, nicht setzen: in der Gruppe "Chat" haengen mehrere
    // Plugins.
    $nav['chat']['label'] = translate('auto_shoutout.nav.chat');
    $nav['chat']['order'] = 20;
    $nav['chat']['items'][] = [
        'label'      => translate('auto_shoutout.name'),
        'href'       => '/chat/auto-shoutout',
        'permission' => 'AutoShoutout.Global.View',
        'toggle'     => [
            'on'         => AutoShoutout::enabled($app),
            'action'     => '/chat/auto-shoutout/toggle',
            'value'      => 'toggle',
            'permission' => 'AutoShoutout.Global.Toggle',
            'title'      => translate('auto_shoutout.toggle_hint'),
        ],
    ];

    return $nav;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/auto-shoutout/assets/auto-shoutout.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Betrieb
// -------------------------------------------------------------------
$hooks->on('core.chat.message', static function (array $message) use ($app): void {
    AutoShoutout::onChat($app, $message);
});

$hooks->on('cron.tick', static function () use ($app): void {
    AutoShoutout::tick($app);
});

// Streamstart = neue Runde, Streamende = Ruhe. Beides abonniert der
// Kern schon.
$hooks->on('core.event.stored', static function (array $event) use ($app): void {
    $typ = (string) ($event['event_type'] ?? '');

    if ($typ === 'twitch.stream.offline') {
        AutoShoutout::setLive($app, false);

        return;
    }

    if ($typ !== 'twitch.stream.online') {
        return;
    }

    AutoShoutout::setLive($app, true);

    $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
    $seit = strtotime((string) ($payload['started_at'] ?? ''));

    AutoShoutout::newRound($app, 'stream', $seit === false ? null : $seit);
    $app->log('AutoShoutout: Stream gestartet, neue Runde.');
});

// -------------------------------------------------------------------
//  Hilfsmittel
// -------------------------------------------------------------------
$zurueck = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/chat/auto-shoutout') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

// -------------------------------------------------------------------
//  Hauptschalter
// -------------------------------------------------------------------
$router->post('/chat/auto-shoutout/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck(['error' => translate('common.error.unknown_action')]);
    }

    if (!permission('AutoShoutout.Global.Toggle')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $an = !AutoShoutout::enabled($app);
    AutoShoutout::setEnabled($app, $an);

    // Der Schalter steht in der Seitenleiste - zurueck dorthin, wo man
    // gerade war.
    $woher = $request->header('Referer');

    if ($woher !== '' && str_starts_with($woher, $app->url(''))) {
        return Response::redirect($woher);
    }

    return $zurueck([
        'notice' => $an ? translate('auto_shoutout.turned_on') : translate('auto_shoutout.turned_off'),
    ]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Kanaele und Runde
// -------------------------------------------------------------------
$router->post('/chat/auto-shoutout', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'add') {
        if (!permission('AutoShoutout.Channels.Add')) {
            return $zurueck(['error' => translate('common.error.no_permission')]);
        }

        $login = AutoShoutout::add($app, $request->input('login'));

        return $login === ''
            ? $zurueck(['error' => AutoShoutout::error()])
            : $zurueck(['notice' => translate('auto_shoutout.added', ['login' => $login])]);
    }

    if ($aktion === 'remove') {
        if (!permission('AutoShoutout.Channels.Delete')) {
            return $zurueck(['error' => translate('common.error.no_permission')]);
        }

        $login = AutoShoutout::loginFrom($request);
        AutoShoutout::remove($app, $login);

        return $zurueck(['notice' => translate('auto_shoutout.removed', ['login' => $login])]);
    }

    if ($aktion === 'reset') {
        if (!permission('AutoShoutout.Global.Reset')) {
            return $zurueck(['error' => translate('common.error.no_permission')]);
        }

        AutoShoutout::newRound($app, 'reset');

        return $zurueck(['notice' => translate('auto_shoutout.round_reset')]);
    }

    return $zurueck(['error' => translate('common.error.unknown_action')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$router->get('/chat/auto-shoutout', static function (Request $request) use ($app, $plugin): Response {
    $kanaele = AutoShoutout::channels($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'       => translate('auto_shoutout.name'),
        'active'      => 'chat/auto-shoutout',
        'enabled'     => AutoShoutout::enabled($app),
        'ready'       => AutoShoutout::ready($app),
        'channels'    => $kanaele,
        'images'      => AutoShoutout::profiles(
            $app,
            array_map(static fn (array $k): string => $k['login'], $kanaele)
        ),
        'roundAt'     => AutoShoutout::roundStartedAt($app),
        'roundReason' => AutoShoutout::roundReason($app),
        'canAdd'      => permission('AutoShoutout.Channels.Add'),
        'canDelete'   => permission('AutoShoutout.Channels.Delete'),
        'canReset'    => permission('AutoShoutout.Global.Reset'),
        'csrf'        => $app->auth->csrfToken(),
        'notice'      => (string) $request->get('notice'),
        'error'       => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'AutoShoutout.Global.View']);
