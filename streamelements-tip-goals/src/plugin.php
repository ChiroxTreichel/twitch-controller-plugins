<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  StreamElements-Tip-Goals
 * ===================================================================
 *
 * Spendenziele als Balken im Overlay, gespeist aus StreamElements.
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
use TwitchController\Plugin\Goals\Goals;
use TwitchController\Plugin\StreamelementsTipGoals\Source;
use TwitchController\Plugin\StreamelementsTipGoals\TipGoals;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['SeTipGoals'] = [
        'label'       => translate('se_tip.name'),
        'permissions' => [
            'SeTipGoals.Global.View' => translate('se_tip.perm.view'),
            'SeTipGoals.Global.Edit' => translate('se_tip.perm.edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Dateien und Einstellungen
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/streamelements-tip-goals/assets/tip-goals.css');

    return $assets;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[TipGoals::SLUG] = [
        'label' => translate('se_tip.settings'),
        'href'  => '/display/goals/tips/settings',
    ];

    // Das Aussehen als zweiter Eintrag - wie bei den Twitch-Zielen.
    // Zugang und Aussehen sind zwei verschiedene Fragen, und wer den
    // Balken umbaut, sucht nicht bei den Zugangsdaten.
    $links[TipGoals::SLUG . ':appearance'] = [
        'label' => translate('se_tip.appearance'),
        'href'  => '/display/goals/tips/appearance',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Reiter auf der Goals-Seite
// -------------------------------------------------------------------
$hooks->on('goals.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('SeTipGoals.Global.View')) {
        return $tabs;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    $tabs['tips'] = [
        'label' => translate('se_tip.tab'),
        // Hinter den Twitch-Zielen: im alten System standen Follower
        // und Subs oben, das Spendenziel darunter.
        'order' => 20,
        'render' => static fn (): string => $vorlagen->render('tab', [
            'goals'   => TipGoals::all($app),
            'maxGoals' => TipGoals::MAX_GOALS,
            'ready'   => Source::ready($app),
            'lastError' => $app->settings->string('last_error', '', TipGoals::scope()),
            'canEdit' => permission('SeTipGoals.Global.Edit'),
            'csrf'    => $app->auth->csrfToken(),
        ], null),
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Der Balken im Overlay
// -------------------------------------------------------------------
$hooks->on('goals.markup', static function (array $teile) use ($app): array {
    $teile['streamelements-tip-goals'] = [
        'order' => 20,
        'html'  => TipGoals::html($app),
        'css'   => TipGoals::css($app),
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
$hooks->on('goals.stamp', static function (mixed $stempel) use ($app): int {
    return max((int) $stempel, TipGoals::stamp($app));
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

    if (!permission('SeTipGoals.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'add') {
        if (!TipGoals::add($app)) {
            return $zurueck(['error' => translate('se_tip.too_many', [
                'max' => (string) TipGoals::MAX_GOALS,
            ])]);
        }

        return $zurueck();
    }

    if ($aktion === 'remove') {
        TipGoals::remove($app, (int) $request->input('id'));
        TipGoals::push($app);

        return $zurueck(['notice' => translate('se_tip.removed')]);
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

    return $zurueck(['notice' => translate('se_tip.saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Das Aussehen
// -------------------------------------------------------------------
$zurueckAussehen = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/appearance') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/tips/appearance', static function (Request $request) use ($app, $plugin): Response {
    $html = TipGoals::html($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('appearance', [
        'title'    => translate('se_tip.appearance'),
        'active'   => 'display/goals',
        'html'     => $html,
        'css'      => TipGoals::css($app),
        'custom'   => TipGoals::isCustom($app),
        // Beim OEFFNEN schon melden, was fehlt - nicht erst beim
        // Speichern. Wer eine kaputte Fassung stehen hat, soll sie
        // sehen, ohne sie vorher noch einmal abschicken zu muessen.
        'missing'  => Goals::missing($html, TipGoals::REQUIRED_BINDINGS, TipGoals::REQUIRED_FILLS),
        'required' => [
            'tip_title'   => translate('se_tip.bind.title'),
            'tip_current' => translate('se_tip.bind.current'),
            'tip_goal'    => translate('se_tip.bind.goal'),
        ],
        'fills'    => ['tip' => translate('se_tip.bind.fill')],
        'canEdit'  => permission('SeTipGoals.Global.Edit'),
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => (string) $request->get('notice'),
        'error'    => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'SeTipGoals.Global.View']);

$router->post('/display/goals/tips/appearance', static function (Request $request) use ($app, $zurueckAussehen): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckAussehen(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('SeTipGoals.Global.Edit')) {
        return $zurueckAussehen(['error' => translate('common.error.no_permission')]);
    }

    if ($request->input('action') === 'reset') {
        TipGoals::resetAppearance($app);

        return $zurueckAussehen(['notice' => translate('se_tip.reset_done')]);
    }

    $fehlend = TipGoals::saveAppearance($app, $request->input('html'), $request->input('css'));

    // Gespeichert ist es in jedem Fall - gemeldet wird trotzdem, was
    // fehlt. Ein halb fertiges Geruest soll man weiterschreiben
    // koennen.
    return $zurueckAussehen($fehlend === []
        ? ['notice' => translate('se_tip.appearance_saved')]
        : ['error' => translate('se_tip.missing_hint') . ' ' . implode(', ', $fehlend)]);
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
        'title'     => translate('se_tip.name'),
        'active'    => 'display/goals',
        'hasToken'  => Source::hasToken($app),
        'channelId' => Source::channelId($app),
        'ready'     => Source::ready($app),
        'lastError' => $app->settings->string('last_error', '', TipGoals::scope()),
        'pollSeconds' => TipGoals::POLL_SECONDS,
        'canEdit'   => permission('SeTipGoals.Global.Edit'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'SeTipGoals.Global.View']);

$router->post('/display/goals/tips/settings', static function (Request $request) use ($app, $zurueckEinst): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckEinst(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('SeTipGoals.Global.Edit')) {
        return $zurueckEinst(['error' => translate('common.error.no_permission')]);
    }

    Source::setChannelId($app, $request->input('channel_id'));

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

    return $zurueckEinst(['notice' => translate('se_tip.settings_saved')]);
}, ['auth' => true]);
