<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Goals - der Rahmen
 * ===================================================================
 *
 * Zeigt selbst kein Ziel an. Es stellt die Flaeche im Overlay, die
 * Reiter fuer die Ziel-Plugins und den Weg, auf dem deren Geruest und
 * Werte ins Overlay kommen. Der Inhalt kommt von Twitch-Goals und
 * spaeter vom Spenden-Plugin.
 *
 * Der Kern wird dafuer nicht angefasst: overlay.assets nimmt jede
 * eigene Adresse, also auch eine Route. Stylesheet und Geruest stehen
 * in den Einstellungen und werden von zwei Routen erzeugt.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Goals\Goals;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Goals'] = [
        'label'       => translate('goals.name'),
        'permissions' => [
            'Goals.Global.View'   => translate('goals.perm.view'),
            'Goals.Global.Edit'   => translate('goals.perm.edit'),
            'Goals.Global.Toggle' => translate('goals.perm.toggle'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    $nav['display']['label'] = translate('goals.nav.display');
    $nav['display']['order'] = 10;
    $nav['display']['items'][] = [
        'label'      => translate('goals.name'),
        'href'       => '/display/goals',
        'permission' => 'Goals.Global.View',
        'toggle'     => [
            'on'         => Goals::enabled($app),
            'action'     => '/display/goals/toggle',
            'value'      => 'toggle',
            'permission' => 'Goals.Global.Toggle',
            'title'      => translate('goals.toggle_hint'),
        ],
    ];

    return $nav;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[Goals::SLUG] = [
        'label' => translate('goals.settings'),
        'href'  => '/display/goals/settings',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Platz im Overlay
// -------------------------------------------------------------------
$hooks->on('overlay.slots', static function (array $slots) use ($app): array {
    if (!Goals::enabled($app)) {
        return $slots;
    }

    $slots[Goals::SLOT] = [
        'label'    => translate('goals.name'),
        // Wie in der Legacy: waagerecht mittig, oben.
        'position' => 'top-center',
        'width'    => Goals::width($app) . 'px',
        // Unter den Alerts: ein Alert soll ueber den Zielen liegen,
        // nicht darunter verschwinden.
        'z'        => 20,
        'vars'     => [
            '--goals-offset-top' => Goals::offsetTop($app) . 'px',
        ],
    ];

    return $slots;
});

$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    if (!Goals::enabled($app)) {
        return $assets;
    }

    // Der Stempel steht in der Adresse, damit OBS nach einer Aenderung
    // nicht das alte Stylesheet behaelt. App::asset() kann das hier
    // nicht leisten - es rechnet mit dem Aenderungsdatum einer DATEI,
    // und beides kommt aus den Einstellungen.
    $stempel = (string) Goals::stamp($app);

    $assets['css'][] = $app->asset('/plugin/goals/assets/goals.css');
    $assets['css'][] = $app->url('/display/goals/style.css') . '?v=' . $stempel;

    // Reihenfolge zaehlt: markup.js legt das Geruest ab, goals.js
    // setzt es ein und bindet die Werte daran.
    $assets['js'][] = $app->url('/display/goals/markup.js') . '?v=' . $stempel;
    $assets['js'][] = $app->asset('/plugin/goals/assets/goals.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Was das Overlay laedt
// -------------------------------------------------------------------

// Dieselbe Absicherung wie die Overlay-Seite selbst: die Browserquelle
// in OBS traegt eine Sitzung, ein oeffentlicher Endpunkt waere also
// nicht noetig - und was das Overlay zeigt, geht niemanden sonst an.
$router->get('/display/goals/style.css', static function () use ($app): Response {
    // html() und nicht text(): text() setzt text/plain fest, und ein
    // Stylesheet mit falschem Typ wendet der Browser nicht an.
    return Response::html(Goals::markup($app)['css'], 200, [
        'Content-Type' => 'text/css; charset=utf-8',
    ]);
}, ['auth' => true, 'permission' => 'Account.Overlay.View']);

$router->get('/display/goals/markup.js', static function () use ($app): Response {
    // Nur eine Zuweisung. Das Einsetzen macht goals.js - so bleibt der
    // Code in einer Datei, die man lesen und pruefen kann, und hier
    // steht nur der Wert.
    $js = 'window.GOALS_HTML = '
        . json_encode(Goals::markup($app)['html'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . ";\n";

    return Response::html($js, 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
    ]);
}, ['auth' => true, 'permission' => 'Account.Overlay.View']);

// -------------------------------------------------------------------
//  Einstellungen der Flaeche
// -------------------------------------------------------------------
// VOR den Seitenrouten: der Router nimmt den ersten Treffer, und
// /display/goals/{tab} passt auch auf "settings". Stuende es danach,
// zeigte die Adresse den ersten Reiter statt der Einstellungen.
$router->get('/display/goals/settings', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'     => translate('goals.settings'),
        'active'    => 'display/goals',
        'width'     => Goals::width($app),
        'offsetTop' => Goals::offsetTop($app),
        'limits'    => [
            'min_width'  => Goals::MIN_WIDTH,
            'max_width'  => Goals::MAX_WIDTH,
            'max_offset' => Goals::MAX_OFFSET_TOP,
        ],
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Goals.Global.View']);

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$seite = static function (Request $request, array $params = []) use ($app, $plugin): Response {
    $reiter = Goals::tabs($app);

    $gewuenscht = strtolower(trim((string) ($params['tab'] ?? '')));
    $offen = isset($reiter[$gewuenscht]) ? $gewuenscht : (string) array_key_first($reiter);

    $inhalt = '';
    if ($offen !== '' && ($reiter[$offen]['render'] ?? null) !== null) {
        $inhalt = (string) ($reiter[$offen]['render'])();
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'   => translate('goals.name'),
        'active'  => 'display/goals',
        'enabled' => Goals::enabled($app),
        'tabs'    => $reiter,
        'open'    => $offen,
        'content' => $inhalt,
        'csrf'    => $app->auth->csrfToken(),
        'notice'  => (string) $request->get('notice'),
        'error'   => (string) $request->get('error'),
    ]));
};

// Ohne Reiter zuerst - sonst faengt {tab} den Aufruf.
$router->get('/display/goals', $seite, [
    'auth'       => true,
    'permission' => 'Goals.Global.View',
]);
$router->get('/display/goals/{tab}', $seite, [
    'auth'       => true,
    'permission' => 'Goals.Global.View',
]);

$zurueck = static function (string $pfad, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/display/goals/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck('/display/goals', ['error' => translate('common.error.form_expired')]);
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck('/display/goals', ['error' => translate('common.error.unknown_action')]);
    }

    if (!permission('Goals.Global.Toggle')) {
        return $zurueck('/display/goals', ['error' => translate('common.error.no_permission')]);
    }

    $an = !Goals::enabled($app);
    Goals::setEnabled($app, $an);

    return $zurueck('/display/goals', [
        'notice' => $an ? translate('goals.turned_on') : translate('goals.turned_off'),
    ]);
}, ['auth' => true, 'permission' => 'Goals.Global.View']);

$router->post('/display/goals/settings', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck('/display/goals/settings', ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('Goals.Global.Edit')) {
        return $zurueck('/display/goals/settings', ['error' => translate('common.error.no_permission')]);
    }

    $app->settings->setMany([
        'width'      => max(Goals::MIN_WIDTH, min(Goals::MAX_WIDTH, (int) $request->input('width'))),
        'offset_top' => max(0, min(Goals::MAX_OFFSET_TOP, (int) $request->input('offset_top'))),
    ], Goals::scope());

    return $zurueck('/display/goals/settings', ['notice' => translate('goals.saved')]);
}, ['auth' => true, 'permission' => 'Goals.Global.View']);
