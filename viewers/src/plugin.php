<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Stream - Zuschauer
 * ===================================================================
 *
 * Zwei Listen mit Suche: wer folgt seit wann, und wer hat ein Abo -
 * welche Stufe, bis wann.
 *
 * Dazu, nur wenn ausdruecklich freigeschaltet: wer KEIN Abo mehr hat
 * und seit wann. Das sind zwei Schalter hintereinander - erst in den
 * Einstellungen freischalten (dann wird erfasst), dann auf dem Reiter
 * einschalten (dann wird es gezeigt). Siehe Viewers::historyAllowed().
 *
 * Dasselbe fuer Follower: wer nicht mehr folgt und seit wann, mit
 * eigenem Schalterpaar.
 *
 * Die Follower-Liste fuehrt der Kern (twitch_followers) - er erkennt
 * daran die Unfollows, die Twitch nicht meldet. Die Abos gleicht dieses
 * Plugin im Worker ab; Ereignisse (Abo, Verlaengerung, Abo-Ende,
 * Follow, Unfollow) schreiben beides zwischendurch fort.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Viewers\Store;
use TwitchController\Plugin\Viewers\Sync;
use TwitchController\Plugin\Viewers\Viewers;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Viewers'] = [
        'label'       => translate('viewers.name'),
        'permissions' => [
            'Viewers.Global.View'   => translate('viewers.perm.view'),
            // Eigenes Recht: wer die Abonnenten sehen darf, muss nicht
            // auch sehen, wer gegangen ist.
            'Viewers.Former.View'   => translate('viewers.perm.former'),
            'Viewers.Global.Edit'   => translate('viewers.perm.edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav): array {
    // Anhaengen und nicht setzen - in "Stream" haengen mehrere Plugins.
    $nav['stream']['label'] = translate('viewers.nav.stream');
    $nav['stream']['order'] = 15;
    $nav['stream']['items'][] = [
        'label'      => translate('viewers.nav.item'),
        'href'       => '/stream/viewers',
        'permission' => 'Viewers.Global.View',
    ];

    return $nav;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[Viewers::SLUG] = [
        'label' => translate('viewers.settings'),
        'href'  => '/stream/viewers/settings',
    ];

    return $links;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/viewers/assets/viewers.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Ereignisse und Abgleich
// -------------------------------------------------------------------
$hooks->on('core.event.stored', static function (array $event) use ($app): void {
    Store::onEvent($app, $event);
});

$hooks->on('cron.tick', static function () use ($app): void {
    Sync::due($app);
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$router->get('/stream/viewers', static function (Request $request) use ($app, $plugin): Response {
    $reiter = $request->get('tab') === 'subs' ? 'subs' : 'followers';
    $suche = mb_substr(trim((string) $request->get('q')), 0, 64);
    $seite = max(1, (int) $request->get('p'));

    // Beide Schalter muessen an sein - und das Recht muss da sein.
    $darfEhemalige = permission('Viewers.Former.View') && ($reiter === 'subs'
        ? Viewers::historyAllowed($app)
        : Viewers::unfollowsAllowed($app));
    $ehemalige = $darfEhemalige && $request->get('former') === '1';

    $liste = $reiter === 'subs'
        ? Store::subs($app, $suche, $seite, $ehemalige)
        : Store::followers($app, $suche, $seite, $ehemalige);

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'       => translate('viewers.name'),
        'active'      => 'stream/viewers',
        'tab'         => $reiter,
        'search'      => $suche,
        'page'        => $seite,
        'rows'        => $liste['rows'],
        'total'       => $liste['total'],
        'counts'      => Store::counts($app),
        'former'      => $ehemalige,
        'formerShown' => $darfEhemalige,
        'sync'        => Viewers::syncState($app, $reiter),
        'csrf'        => $app->auth->csrfToken(),
        'notice'      => (string) $request->get('notice'),
        'error'       => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Viewers.Global.View']);

/*
 * Jetzt abgleichen - fuer den, der nicht auf den Worker warten will.
 * Nur die Liste des offenen Reiters. Die Follower gleicht ohnehin der
 * Kern alle zehn Sekunden ab; der Knopf fragt ihn nur sofort.
 */
$router->post('/stream/viewers/sync', static function (Request $request) use ($app): Response {
    $reiter = $request->input('tab') === 'subs' ? 'subs' : 'followers';
    $zurueck = static function (array $query) use ($app, $reiter): Response {
        return Response::redirect($app->url('/stream/viewers') . '?' . http_build_query(['tab' => $reiter] + $query));
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    @set_time_limit(180);

    if ($reiter === 'subs') {
        $ok = Sync::subs($app);
    } else {
        try {
            $app->twitch->followers()->check();
            $app->settings->set('followers_error', '');
            $ok = true;
        } catch (Throwable $fehler) {
            $app->settings->set('followers_error', mb_substr($fehler->getMessage(), 0, 300));
            $ok = false;
        }
    }

    return $zurueck($ok
        ? ['notice' => translate('viewers.synced')]
        : ['error' => Viewers::syncState($app, $reiter)['error']]);
}, ['auth' => true, 'permission' => 'Viewers.Global.View']);

// -------------------------------------------------------------------
//  Einstellungen
// -------------------------------------------------------------------
$router->get('/stream/viewers/settings', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'   => translate('viewers.settings'),
        'active'  => 'stream/viewers',
        'allowed'          => Viewers::historyAllowed($app),
        'stored'           => Store::counts($app)['former'],
        'unfollowsAllowed' => Viewers::unfollowsAllowed($app),
        'unfollowsStored'  => Store::counts($app)['unfollows'],
        'canEdit' => permission('Viewers.Global.Edit'),
        'csrf'    => $app->auth->csrfToken(),
        'notice'  => (string) $request->get('notice'),
        'error'   => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Viewers.Global.View']);

$router->post('/stream/viewers/settings', static function (Request $request) use ($app): Response {
    $hierher = static function (array $query) use ($app): Response {
        return Response::redirect($app->url('/stream/viewers/settings') . '?' . http_build_query($query));
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $hierher(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('Viewers.Global.Edit')) {
        return $hierher(['error' => translate('common.error.no_permission')]);
    }

    $aboVorher = Viewers::historyAllowed($app);
    $aboNachher = $request->input('history_allowed') !== '';
    $followVorher = Viewers::unfollowsAllowed($app);
    $followNachher = $request->input('unfollows_allowed') !== '';

    Viewers::setHistoryAllowed($app, $aboNachher);
    Viewers::setUnfollowsAllowed($app, $followNachher);

    // Beim Freischalten gleich die Geschichte aus den gespeicherten
    // Ereignissen holen - sonst stuende die Liste wochenlang leer.
    if ($aboNachher && !$aboVorher) {
        Store::importFormer($app);
    }

    if ($followNachher && !$followVorher) {
        Store::importUnfollows($app);
    }

    return $hierher(['notice' => translate('viewers.settings_saved')]);
}, ['auth' => true, 'permission' => 'Viewers.Global.View']);
