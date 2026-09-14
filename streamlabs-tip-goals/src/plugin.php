<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Streamlabs-Tip-Goals
 * ===================================================================
 *
 * Spendenziele als Balken im Overlay, gespeist aus Streamlabs.
 *
 * Braucht Goals - dort haengt der Reiter und der Platz im Overlay.
 * Und es vertraegt sich NICHT mit Streamlabs-Tip-Goals: beide buchen
 * auf dasselbe Ziel, nebeneinander zaehlte jede Spende doppelt. Die
 * Sperre dafuer steht als "conflicts" in plugin.json; der Kern laesst
 * das zweite gar nicht erst installieren.
 *
 * Spenden gehen IMMER auf das erste Ziel der Liste. Siehe TipGoals.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\StreamlabsTipGoals\Source;
use TwitchController\Plugin\StreamlabsTipGoals\TipGoals;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['SlTipGoals'] = [
        'label'       => translate('sl_tip.name'),
        'permissions' => [
            'SlTipGoals.Global.View' => translate('sl_tip.perm.view'),
            'SlTipGoals.Global.Edit' => translate('sl_tip.perm.edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Dateien und Einstellungen
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/streamlabs-tip-goals/assets/tip-goals.css');

    return $assets;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[TipGoals::SLUG] = [
        'label' => translate('sl_tip.settings'),
        'href'  => '/display/goals/tips/settings',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Reiter auf der Goals-Seite
// -------------------------------------------------------------------
$hooks->on('goals.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('SlTipGoals.Global.View')) {
        return $tabs;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    $tabs['tips'] = [
        'label' => translate('sl_tip.tab'),
        // Hinter den Twitch-Zielen: im alten System standen Follower
        // und Subs oben, das Spendenziel darunter.
        'order' => 20,
        'render' => static fn (): string => $vorlagen->render('tab', [
            'goals'   => TipGoals::all($app),
            'maxGoals' => TipGoals::MAX_GOALS,
            'ready'   => Source::ready($app),
            'lastError' => $app->settings->string('last_error', '', TipGoals::scope()),
            'canEdit' => permission('SlTipGoals.Global.Edit'),
            'csrf'    => $app->auth->csrfToken(),
        ], null),
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Der Balken im Overlay
// -------------------------------------------------------------------
$hooks->on('goals.markup', static function (array $teile): array {
    $teile['streamlabs-tip-goals'] = [
        'order' => 20,
        'html'  => TipGoals::html(),
        'css'   => TipGoals::css(),
    ];

    return $teile;
});

// Der letzte bekannte Stand, damit im Overlay schon etwas steht, bevor
// die erste Spende kommt. Ohne das stuende der Balken nach jedem
// Neuladen auf null - und genau dieser Fehler ist bei den Twitch-Zielen
// schon einmal passiert.
$hooks->on('goals.state', static function (array $zustand) use ($app): array {
    return array_merge($zustand, TipGoals::values($app));
});

// Aendert sich das Geruest, muss OBS nachladen.
$hooks->on('goals.stamp', static function (mixed $stempel): int {
    return max((int) $stempel, (int) strtotime('2026-09-14'));
});

// -------------------------------------------------------------------
//  Nachfragen
// -------------------------------------------------------------------
$hooks->on('cron.tick', static function () use ($app): void {
    $letzte = $app->settings->int('checked_at', 0, TipGoals::scope());
    if (time() - $letzte < TipGoals::POLL_SECONDS) {
        return;
    }

    $app->settings->set('checked_at', time(), TipGoals::scope());

    $ergebnis = Source::collect($app);

    // Der Fehler wird GEMERKT und nicht nur geloggt: eine Abfrage, die
    // still scheitert, sieht auf der Seite aus wie eine, bei der nichts
    // passiert ist. Das hat hier schon einmal eine Woche gekostet.
    $app->settings->set('last_error', $ergebnis['error'], TipGoals::scope());

    if ($ergebnis['error'] !== '') {
        $app->log(TipGoals::SLUG . ': Abfrage gescheitert: ' . $ergebnis['error']);

        return;
    }

    if ($ergebnis['amount'] > 0) {
        TipGoals::applyDonation($app, $ergebnis['amount']);
    }
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

    if (!permission('SlTipGoals.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'add') {
        if (!TipGoals::add($app)) {
            return $zurueck(['error' => translate('sl_tip.too_many', [
                'max' => (string) TipGoals::MAX_GOALS,
            ])]);
        }

        return $zurueck();
    }

    if ($aktion === 'remove') {
        TipGoals::remove($app, (int) $request->input('id'));
        TipGoals::push($app);

        return $zurueck(['notice' => translate('sl_tip.removed')]);
    }

    if ($aktion === 'up' || $aktion === 'down') {
        TipGoals::move($app, (int) $request->input('id'), $aktion === 'up' ? -1 : 1);

        // Verschieben aendert, welches Ziel das laufende ist - also
        // auch, was im Overlay steht.
        TipGoals::push($app);

        return $zurueck();
    }

    // Speichern. Die Felder kommen als goals[<id>][titel] herein, also
    // ueber $request->post - input() gibt immer eine Zeichenkette.
    $eingaben = $request->post['goals'] ?? [];
    TipGoals::save($app, is_array($eingaben) ? $eingaben : []);
    TipGoals::push($app);

    return $zurueck(['notice' => translate('sl_tip.saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Zugangsdaten
// -------------------------------------------------------------------
$zurueckEinst = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/settings') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/tips/settings', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'     => translate('sl_tip.name'),
        'active'    => 'display/goals',
        'hasToken'  => Source::hasToken($app),
        'ready'     => Source::ready($app),
        'lastError' => $app->settings->string('last_error', '', TipGoals::scope()),
        'pollSeconds' => TipGoals::POLL_SECONDS,
        'canEdit'   => permission('SlTipGoals.Global.Edit'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'SlTipGoals.Global.View']);

$router->post('/display/goals/tips/settings', static function (Request $request) use ($app, $zurueckEinst): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckEinst(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('SlTipGoals.Global.Edit')) {
        return $zurueckEinst(['error' => translate('common.error.no_permission')]);
    }

    // Ein leeres Feld loescht das Token NICHT: es wird nie wieder
    // angezeigt, und ein Formular, das man zum Aendern der Kanal-ID
    // abschickt, darf nicht nebenbei den Zugang wegwerfen. Zum
    // Loeschen gibt es einen eigenen Knopf.
    $token = $request->input('token');
    if ($token !== '') {
        Source::setToken($app, $token);
    }

    if ($request->input('action') === 'forget') {
        Source::setToken($app, '');
    }

    // Was zuletzt schiefging, gilt nach neuen Zugangsdaten nicht mehr.
    $app->settings->set('last_error', '', TipGoals::scope());

    return $zurueckEinst(['notice' => translate('sl_tip.settings_saved')]);
}, ['auth' => true]);
