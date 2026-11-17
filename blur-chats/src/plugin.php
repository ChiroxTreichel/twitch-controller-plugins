<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Blur Chats
 * ===================================================================
 *
 * Deckt Stellen im Bild ab, je Twitch-Kategorie: im einen Spiel den
 * Ingame-Chat unten links, im anderen die Freundesliste oben rechts.
 * Wechselt die Kategorie, springen die Bereiche im Overlay um - der
 * Kern meldet den Wechsel (core.category.changed), und das Plugin
 * schickt die neue Liste ueber den Overlay-Bus.
 *
 * Abgedeckt wird mit Milchglas, nicht mit einem echten Weichzeichner:
 * eine Browserquelle sieht nicht, was in OBS unter ihr liegt, sie kann
 * es also auch nicht verwischen. Sie legt eine fast deckende Flaeche
 * mit weichem Rand und etwas Koernung darueber.
 *
 * Alle Zahlen sind Pixel der Buehne (Konto > Overlay), von oben links.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Core\Twitch\TokenStore;
use TwitchController\Plugin\BlurChats\Overlay;
use TwitchController\Plugin\BlurChats\Regions;
use TwitchController\Plugin\BlurChats\Screens;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['BlurChats'] = [
        'label'       => translate('blur_chats.name'),
        'permissions' => [
            'BlurChats.Global.View'   => translate('blur_chats.perm.view'),
            'BlurChats.Global.Edit'   => translate('blur_chats.perm.edit'),
            'BlurChats.Global.Toggle' => translate('blur_chats.perm.toggle'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Anhaengen, nicht die Gruppe setzen: Streaminfo und der Mod-Chat
    // haengen in dieselbe, und wer sie ueberschreibt, laesst je nach
    // Ladereihenfolge deren Menuepunkte verschwinden.
    $nav['stream']['label'] = translate('blur_chats.nav.stream');
    $nav['stream']['order'] = 15;
    $nav['stream']['items'][] = [
        'label'      => translate('blur_chats.name'),
        'href'       => '/stream/blur-chats',
        'permission' => 'BlurChats.Global.View',
        'toggle'     => [
            'on'         => Regions::enabled($app),
            'action'     => '/stream/blur-chats/toggle',
            'value'      => 'toggle',
            'permission' => 'BlurChats.Global.Toggle',
            'title'      => translate('blur_chats.toggle_hint'),
        ],
    ];

    return $nav;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    // region.css teilen sich Editor und Overlay: die Vorschau soll
    // genau so aussehen wie das, was im Stream ankommt.
    $assets['css'][] = $app->asset('/plugin/blur-chats/assets/region.css');
    $assets['css'][] = $app->asset('/plugin/blur-chats/assets/blur-chats.css');
    $assets['js'][] = $app->asset('/plugin/blur-chats/assets/blur-chats.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Overlay
// -------------------------------------------------------------------
// Der Platz steht immer da, auch wenn das Plugin aus ist - dann ist er
// leer. Ihn je nach Schalter an- und abzumelden, liesse die
// Browserquelle bei jedem Umschalten neu laden. "fill" deckt die ganze
// Buehne: die Bereiche rechnen von ihrer Ecke oben links.
$hooks->on('overlay.slots', static function (array $slots): array {
    $slots[Regions::SLOT] = [
        'label'    => translate('blur_chats.name'),
        'position' => 'fill',
        // Hinter den Avatar: die Abdeckung soll das Spiel treffen, nicht
        // die Person vor der Kamera. Unter Konto > Overlay umlegbar.
        'layer'    => 'background',
    ];

    return $slots;
});

$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/blur-chats/assets/region.css');
    $assets['css'][] = $app->asset('/plugin/blur-chats/assets/overlay.css');

    // Reihenfolge zaehlt: state.js legt den Anfangszustand ab,
    // overlay.js zeigt ihn an. Die Leitung spielt nichts nach.
    $assets['js'][] = $app->url('/stream/blur-chats/state.js');
    $assets['js'][] = $app->asset('/plugin/blur-chats/assets/overlay.js');

    return $assets;
});

$router->get('/stream/blur-chats/state.js', static function () use ($app): Response {
    return Response::html(
        'window.BLUR_CHATS_STATE = ' . json_encode(
            Overlay::state($app),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        ) . ";\n",
        200,
        [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, must-revalidate',
        ]
    );
}, ['auth' => true, 'permission' => 'Account.Overlay.View']);

// Die Kategorie hat gewechselt: die Bereiche der neuen ins Bild.
$hooks->on('core.category.changed', static function () use ($app): void {
    Overlay::push($app);
});

// -------------------------------------------------------------------
//  Hilfsmittel fuer die Routen
// -------------------------------------------------------------------
$zurueck = static function (string $kategorie = '', ?string $notice = null, ?string $error = null) use ($app): Response {
    $query = array_filter(
        ['category' => $kategorie, 'notice' => $notice, 'error' => $error],
        static fn (?string $w): bool => $w !== null && $w !== ''
    );

    return Response::redirect($app->url('/stream/blur-chats') . ($query === [] ? '' : '?' . http_build_query($query)));
};

// -------------------------------------------------------------------
//  Die Kategorie-Suche
// -------------------------------------------------------------------
// Eine eigene kleine Route und nicht die von Streaminfo: das Plugin
// soll ohne Streaminfo auskommen.
$router->get('/stream/blur-chats/categories', static function (Request $request) use ($app): Response {
    $frage = trim($request->get('q'));

    if ($frage === '') {
        return Response::json([]);
    }

    try {
        $antwort = $app->twitch->api()
            ->as(TokenStore::BROADCASTER)
            ->get('search/categories', ['query' => $frage, 'first' => 10]);
    } catch (Throwable $e) {
        $app->log('Blur Chats: Kategorie-Suche fehlgeschlagen: ' . $e->getMessage());

        return Response::json([]);
    }

    // Auch ein Fehler kommt als leere Liste: das Feld soll beim Tippen
    // nicht aufhoeren zu arbeiten, weil Twitch einmal gehustet hat.
    if (!$antwort->ok()) {
        $app->log('Blur Chats: Kategorie-Suche fehlgeschlagen: ' . $antwort->error());

        return Response::json([]);
    }

    $treffer = [];

    foreach (($antwort->json['data'] ?? []) as $zeile) {
        $id = is_array($zeile) ? (string) ($zeile['id'] ?? '') : '';
        $name = is_array($zeile) ? (string) ($zeile['name'] ?? '') : '';

        if ($id === '' || $name === '') {
            continue;
        }

        $treffer[] = [
            'id'          => $id,
            'name'        => $name,
            // Twitch liefert Platzhalter fuer die Groesse.
            'box_art_url' => str_replace(['{width}', '{height}'], ['56', '76'], (string) ($zeile['box_art_url'] ?? '')),
        ];
    }

    return Response::json($treffer);
}, ['auth' => true, 'permission' => 'BlurChats.Global.Edit']);

// -------------------------------------------------------------------
//  Der Screenshot
// -------------------------------------------------------------------
// Fuer das Skript der Seite: antwortet JSON und leitet nicht um.
$router->post('/stream/blur-chats/screen', static function (Request $request) use ($app): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return Response::json(['ok' => false, 'error' => translate('common.error.form_expired')], 400);
    }

    if (!$app->auth->can('BlurChats.Global.Edit')) {
        return Response::json(['ok' => false, 'error' => translate('common.error.no_permission')], 403);
    }

    $kategorie = $request->input('category');
    $fehler = Screens::store($app, $kategorie, $request->input('image'), $request->input('category_name'));

    if ($fehler !== '') {
        return Response::json(['ok' => false, 'error' => $fehler], 422);
    }

    return Response::json([
        'ok'  => true,
        'url' => $app->url('/stream/blur-chats/screen/' . rawurlencode($kategorie)) . '?v=' . Screens::stamp($app, $kategorie),
    ]);
}, ['auth' => true]);

$router->get('/stream/blur-chats/screen/{id}', static function (Request $request, array $params) use ($app): Response {
    $bild = Screens::get($app, (string) ($params['id'] ?? ''));

    if ($bild === null) {
        return Response::text('Not Found', 404);
    }

    return Response::html($bild, 200, [
        'Content-Type'   => 'image/jpeg',
        'Cache-Control'  => 'no-store',
        'Content-Length' => (string) strlen($bild),
    ]);
}, ['auth' => true, 'permission' => 'BlurChats.Global.View']);

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$router->get('/stream/blur-chats', static function (Request $request) use ($app, $plugin): Response {
    $kategorien = Regions::categories($app);
    $jetzt = Overlay::currentCategory($app);

    $bekannt = [];
    foreach ($kategorien as $k) {
        $bekannt[$k['id']] = $k;
    }

    // Gewaehlt ist, was in der Adresse steht - sonst die laufende
    // Kategorie, sonst die erste. Eine unbekannte ID aus einem alten
    // Lesezeichen fuehrt nicht auf eine Fehlerseite.
    $gewaehlt = $request->get('category');
    if (!isset($bekannt[$gewaehlt])) {
        $gewaehlt = isset($bekannt[$jetzt['id']]) ? $jetzt['id'] : (string) ($kategorien[0]['id'] ?? '');
    }

    $hatBild = $gewaehlt !== '' && Screens::has($app, $gewaehlt);

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'      => translate('blur_chats.name'),
        'active'     => 'stream/blur-chats',
        'enabled'    => Regions::enabled($app),
        'categories' => $kategorien,
        'current'    => $jetzt,
        'selected'   => $gewaehlt !== '' ? $bekannt[$gewaehlt] : null,
        'regions'    => Regions::forCategory($app, $gewaehlt),
        'stage'      => Regions::stage($app),
        'screenUrl'  => $hatBild
            ? $app->url('/stream/blur-chats/screen/' . rawurlencode($gewaehlt)) . '?v=' . Screens::stamp($app, $gewaehlt)
            : '',
        'canEdit'    => $app->auth->can('BlurChats.Global.Edit'),
        'csrf'       => $app->auth->csrfToken(),
        'notice'     => $request->get('notice'),
        'error'      => $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'BlurChats.Global.View']);

$router->post('/stream/blur-chats', static function (Request $request) use ($app, $zurueck): Response {
    $kategorie = $request->input('category');

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($kategorie, null, translate('common.error.form_expired'));
    }

    if (!$app->auth->can('BlurChats.Global.Edit')) {
        return $zurueck($kategorie, null, translate('common.error.no_permission'));
    }

    switch ($request->input('action')) {
        case 'add_category':
            $id = $request->input('category_id');
            $name = $request->input('category_name');

            // Nur, was aus der Vorschlagsliste gewaehlt wurde: Twitch
            // kennt Kategorien an der ID, ein getippter Name waere
            // geraten.
            if ($id === '') {
                return $zurueck($kategorie, null, translate('blur_chats.error.pick_category'));
            }

            Regions::addCategory($app, $id, $name !== '' ? $name : $id);
            Overlay::push($app);

            return $zurueck($id);

        case 'add_current':
            $jetzt = Overlay::currentCategory($app);

            if ($jetzt['id'] === '') {
                return $zurueck($kategorie, null, translate('blur_chats.error.no_current'));
            }

            Regions::addCategory($app, $jetzt['id'], $jetzt['name']);
            Overlay::push($app);

            return $zurueck($jetzt['id']);

        case 'save':
            if ($kategorie === '') {
                return $zurueck('', null, translate('blur_chats.error.no_category'));
            }

            // In der Reihenfolge der Nummern: das Skript zaehlt die
            // Zeilen so durch, wie sie auf der Seite stehen.
            $roh = $request->post['regions'] ?? [];
            $roh = is_array($roh) ? $roh : [];
            ksort($roh, SORT_NUMERIC);
            $zeilen = array_values(array_filter($roh, 'is_array'));

            if (count($zeilen) > Regions::MAX_REGIONS) {
                return $zurueck($kategorie, null, translate('blur_chats.error.too_many', ['max' => (string) Regions::MAX_REGIONS]));
            }

            Regions::save($app, $kategorie, $request->input('category_name'), $zeilen);
            Overlay::push($app);

            return $zurueck($kategorie, translate('blur_chats.saved'));

        case 'remove_category':
            Regions::removeCategory($app, $kategorie);
            Overlay::push($app);

            return $zurueck('', translate('blur_chats.removed'));

        case 'screen_remove':
            Screens::remove($app, $kategorie);

            return $zurueck($kategorie);
    }

    return $zurueck($kategorie, null, translate('common.error.unknown_action'));
}, ['auth' => true, 'permission' => 'BlurChats.Global.View']);

// -------------------------------------------------------------------
//  Der Schalter im Menue
// -------------------------------------------------------------------
$router->post('/stream/blur-chats/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck('', null, translate('common.error.form_expired'));
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck('', null, translate('common.error.unknown_action'));
    }

    if (!$app->auth->can('BlurChats.Global.Toggle')) {
        return $zurueck('', null, translate('common.error.no_permission'));
    }

    $an = !Regions::enabled($app);
    Regions::setEnabled($app, $an);
    Overlay::push($app);

    // Der Schalter steht auf jeder Seite. Ein fester Rueckweg wuerde
    // einen von dort wegwerfen, wo man gerade war.
    $woher = $request->header('Referer');
    if ($woher !== '' && str_starts_with($woher, $app->url(''))) {
        return Response::redirect($woher);
    }

    return $zurueck('', $an ? translate('blur_chats.turned_on') : translate('blur_chats.turned_off'));
}, ['auth' => true]);
