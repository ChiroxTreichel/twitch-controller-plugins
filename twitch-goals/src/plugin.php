<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Twitch-Goals
 * ===================================================================
 *
 * Follower- und Sub-Ziel, die Zahlen direkt von Twitch. Der Rahmen
 * kommt vom Plugin Goals: dort haengt der Reiter, und dorthin gehen
 * Geruest und Werte.
 *
 * Zwei Stellen, absichtlich getrennt:
 *
 *   Reiter in Goals   die Titel, und was Twitch gerade meldet
 *   Plugin-Liste      das Aussehen, also HTML und CSS
 *
 * Das Aussehen gehoert nicht zwischen Follower- und Sub-Ziel - es ist
 * eine Einstellung dieses Plugins, wie bei den Alerts die Groesse der
 * Flaeche.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Goals\Goals;
use TwitchController\Plugin\TwitchGoals\Config;
use TwitchController\Plugin\TwitchGoals\Fetcher;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['TwitchGoals'] = [
        'label'       => translate('twitch_goals.name'),
        'permissions' => [
            'TwitchGoals.Global.View' => translate('twitch_goals.perm.view'),
            'TwitchGoals.Global.Edit' => translate('twitch_goals.perm.edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Eigenes CSS fuer die zwei Code-Felder
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/twitch-goals/assets/twitch-goals.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Was Twitch dafuer verlangt
// -------------------------------------------------------------------
$hooks->on('core.twitch.broadcaster_scopes', static function (array $scopes) use ($app): array {
    // Nur wenn die Ziele eingeschaltet sind. Sonst mahnt die
    // Oberflaeche eine Freigabe fuer etwas an, das bewusst aus ist -
    // und wer sie erteilt, merkt keinen Unterschied.
    if (!Goals::enabled($app)) {
        return $scopes;
    }

    // Ohne channel:read:goals liefert helix/goals nichts und die Abos
    // channel.goal.* lehnt Twitch ab.
    $scopes[] = 'channel:read:goals';

    return $scopes;
});

$hooks->on('core.eventsub.subscriptions', static function (array $subs, string $broadcasterId) use ($app): array {
    if (!Goals::enabled($app)) {
        return $subs;
    }

    $kanal = ['broadcaster_user_id' => $broadcasterId];

    // begin und end braucht es genauso wie progress: wird ein Ziel neu
    // angelegt oder beendet, aendert sich der Zielwert, nicht nur der
    // Stand.
    foreach (['channel.goal.begin', 'channel.goal.progress', 'channel.goal.end'] as $typ) {
        $subs[] = ['type' => $typ, 'version' => '1', 'condition' => $kanal];
    }

    return $subs;
});

// -------------------------------------------------------------------
//  Aktuell bleiben
// -------------------------------------------------------------------
$hooks->on('core.event.stored', static function (array $event) use ($app): void {
    $typ = (string) ($event['event_type'] ?? '');
    if (!str_starts_with($typ, 'twitch.channel.goal.')) {
        return;
    }

    $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

    (new Fetcher($app))->onEvent($typ, $payload);
});

$hooks->on('cron.tick', static function () use ($app): void {
    // Gedrosselt - siehe Fetcher::POLL_SECONDS. Das Nachfragen ist das
    // Netz unter den Abos, nicht der Hauptweg.
    (new Fetcher($app))->refresh();
});

// -------------------------------------------------------------------
//  Das Geruest im Overlay
// -------------------------------------------------------------------
$hooks->on('goals.markup', static function (array $teile) use ($app): array {
    $teile['twitch-goals'] = [
        // Vor dem Tip-Ziel, das spaeter dazukommt - im alten System
        // standen Follower und Subs oben, das Tip-Ziel darunter.
        'order' => 10,
        'html'  => Config::html($app),
        'css'   => Config::css($app),
    ];

    return $teile;
});

$hooks->on('goals.state', static function (array $zustand) use ($app): array {
    // Der letzte bekannte Stand, damit das Overlay beim Laden schon
    // etwas anzeigt.
    //
    // Ohne das stand nach jedem Laden 0 von 0 in beiden Balken, und die
    // Titel waren leer - bis zufaellig eine Aenderung durch die Leitung
    // kam. Die Leitung selbst spielt nichts nach, siehe Goals::state().
    //
    // Gelesen und nicht abgefragt: das ist der Weg, den jeder
    // Seitenaufruf des Overlays nimmt, und Twitch soll dabei nicht
    // befragt werden. Das Nachfragen erledigt cron.tick, gedrosselt.
    $abrufer = new Fetcher($app);

    return $zustand + $abrufer->payload($abrufer->state());
});

$hooks->on('goals.stamp', static function (mixed $stempel) use ($app): int {
    // Der spaeteste gewinnt: aendert ein Ziel-Plugin sein Aussehen,
    // soll OBS das Stylesheet neu holen - egal welches es war.
    return max(is_numeric($stempel) ? (int) $stempel : 0, Config::stamp($app));
});

// -------------------------------------------------------------------
//  Der Reiter in Goals
// -------------------------------------------------------------------
$hooks->on('goals.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('TwitchGoals.Global.View')) {
        return $tabs;
    }

    $tabs['twitch'] = [
        'label' => translate('twitch_goals.name'),
        'order' => 10,
        // Wird nur fuer den offenen Reiter aufgerufen.
        'render' => static function () use ($app, $plugin): string {
            $stand = (new Fetcher($app))->state();

            return $app->view->from($plugin->directory . '/views')->render('tab', [
                'titles'   => Config::titles($app),
                'state'    => $stand,
                'switches' => Config::switches($app),
                // Welche Schalter greifen ins Leere - siehe
                // Config::deadSwitches().
                'deadSwitches' => Config::deadSwitches($app),
                'custom'   => Config::isCustom($app),
                'maxTitle' => Config::MAX_TITLE,
                'csrf'     => $app->auth->csrfToken(),
            ], null);
        },
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Einstellungen: das Aussehen
// -------------------------------------------------------------------
$hooks->on('plugin.settings', static function (array $links): array {
    $links[Config::SLUG] = [
        'label' => translate('twitch_goals.settings'),
        'href'  => '/display/goals/twitch/appearance',
    ];

    return $links;
});

$zurueck = static function (string $pfad, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/twitch/appearance', static function (Request $request) use ($app, $plugin): Response {
    $html = Config::html($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('appearance', [
        'title'    => translate('twitch_goals.settings_title'),
        'active'   => 'display/goals',
        'html'     => $html,
        'css'      => Config::css($app),
        'custom'   => Config::isCustom($app),
        'missing'  => Goals::missing(
            $html,
            Config::REQUIRED_BINDINGS,
            Config::REQUIRED_FILLS,
            Config::REQUIRED_GOALS
        ),
        'required' => Config::bindingLabels(),
        'fills'    => Config::fillLabels(),
        'goals'    => Config::goalLabels(),
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => (string) $request->get('notice'),
        'error'    => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'TwitchGoals.Global.View']);

$router->post('/display/goals/twitch/appearance', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/display/goals/twitch/appearance';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('TwitchGoals.Global.Edit')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    // Zuruecksetzen auf das Aussehen des alten Systems: leere Felder
    // speichern heisst "nimm die Vorgabe" - siehe Config::html().
    if ($request->input('action') === 'reset') {
        Config::save($app, '', '');

        return $zurueck($ziel, ['notice' => translate('twitch_goals.reset')]);
    }

    $fehlend = Config::save(
        $app,
        (string) $request->input('html'),
        (string) $request->input('css')
    );

    if ($fehlend !== []) {
        return $zurueck($ziel, [
            'notice' => translate('twitch_goals.saved'),
            'error'  => translate('twitch_goals.error.missing', [
                'elements' => implode(', ', $fehlend),
            ]),
        ]);
    }

    return $zurueck($ziel, ['notice' => translate('twitch_goals.saved')]);
}, ['auth' => true, 'permission' => 'TwitchGoals.Global.View']);

// -------------------------------------------------------------------
//  Titel speichern und jetzt abrufen
// -------------------------------------------------------------------
$router->post('/display/goals/twitch', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/display/goals/twitch';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('TwitchGoals.Global.Edit')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    if ($request->input('action') === 'fetch') {
        (new Fetcher($app))->refresh(true);

        return $zurueck($ziel, ['notice' => translate('twitch_goals.fetched')]);
    }

    $app->settings->setMany([
        'follower_title' => trim((string) $request->input('follower_title')),
        'sub_title'      => trim((string) $request->input('sub_title')),
    ], Config::scope());

    // Ein nicht angehaktes Kaestchen schickt der Browser gar nicht
    // mit - ein fehlender Wert heisst also "aus" und nicht
    // "unveraendert".
    foreach (Config::KINDS as $art) {
        Config::setGoalEnabled($app, $art, $request->input($art . '_enabled') !== '');
    }

    // Die Titel stehen im Overlay - also gleich hinschicken, sonst
    // steht dort der alte, bis sich eine Zahl aendert.
    $holer = new Fetcher($app);
    $holer->push($holer->state());

    return $zurueck($ziel, ['notice' => translate('twitch_goals.titles_saved')]);
}, ['auth' => true, 'permission' => 'TwitchGoals.Global.View']);
