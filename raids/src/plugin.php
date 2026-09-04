<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Raids
 * ===================================================================
 *
 * Zwei Fragen, die man vor einem Raid hat:
 *
 *   Wem folge ich, und wer davon streamt ueberhaupt noch?
 *   Wer von meinen Favoriten ist JETZT live?
 *
 * Die erste ist die Arbeit. Twitch verraet nicht, wann jemand zuletzt
 * live war, also wird es am letzten Archiv-Video gemessen - eine
 * Naeherung, die das alte System schon benutzt hat, und gut genug fuer
 * die Frage "wen kann ich raiden". Siehe Channels.
 *
 * Was NICHT hier ist: das Raiden selbst, das Roulette und die
 * Raid-Anfragen. Jedes davon wird ein eigenes Plugin und haengt sich
 * ueber raids.tabs ein.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Raids\Channels;
use TwitchController\Plugin\Raids\Raids;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Raids'] = [
        'label'       => translate('raids.name'),
        'permissions' => [
            'Raids.Global.View'     => translate('raids.perm.view'),
            'Raids.Favorites.Edit'  => translate('raids.perm.favorites'),
            'Raids.Global.Sync'     => translate('raids.perm.sync'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Die Twitch-Freigabe
// -------------------------------------------------------------------
// Ohne user:read:follows gibt es keine Follow-Liste. Alles andere
// arbeitet mit dem, was schon in der Tabelle steht - die Liste ist also
// der einzige Teil, der ohne die Freigabe leer bleibt.
$hooks->on('core.twitch.broadcaster_scopes', static function (array $scopes): array {
    $scopes[] = Channels::SCOPE;

    return $scopes;
});

// Der Kern kennt diese Freigabe noch nicht mit Namen. Ohne den Eintrag
// stuende auf der Einstellungsseite nur der technische Name - und der
// erklaert nicht, wofuer man sie erteilt.
$hooks->on('core.twitch.scope_labels', static function (array $labels): array {
    $labels[Channels::SCOPE] = [
        'label'  => translate('raids.scope'),
        'reason' => translate('raids.scope.why'),
    ];

    return $labels;
});

// -------------------------------------------------------------------
//  Menue und Dateien
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav): array {
    // Anhaengen und nicht setzen: in der Gruppe "Stream" haengt auch
    // die Streaminfo, und wer sie setzt, laesst je nach
    // Ladereihenfolge den anderen Menuepunkt verschwinden.
    $nav['stream']['label'] = translate('raids.nav.stream');
    $nav['stream']['order'] = 15;
    $nav['stream']['items'][] = [
        'label'      => translate('raids.name'),
        'href'       => '/stream/raids',
        'permission' => 'Raids.Global.View',
    ];

    return $nav;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/raids/assets/raids.css');
    $assets['js'][] = $app->asset('/plugin/raids/assets/raids.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Der Abgleich laeuft im Hintergrund
// -------------------------------------------------------------------
$hooks->on('cron.tick', static function () use ($app): void {
    // Gedrosselt in Channels: die Follow-Liste einmal am Tag, und je
    // Tick eine Handvoll Kanaele auf ihren letzten Stream. Das Pruefen
    // ist ein Aufruf JE KANAL - bei zweihundert Follows waere alles auf
    // einmal ein Schwung von zweihundert Abfragen.
    (new Channels($app))->tick();
});

// -------------------------------------------------------------------
//  Die eigenen Reiter
// -------------------------------------------------------------------
$hooks->on('raids.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('Raids.Global.View')) {
        return $tabs;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    // Wer gerade live ist - das ist die Frage, fuer die man die Seite
    // aufruft, also order 0 und damit vorn und offen.
    $tabs['live'] = [
        'label' => translate('raids.tab.live'),
        'order' => 0,
        'render' => static function () use ($app, $vorlagen): string {
            $kanaele = new Channels($app);

            return $vorlagen->render('tab_live', [
                'live'      => $kanaele->live(),
                'favorites' => count($kanaele->favorites()),
                'loadError' => $kanaele->error(),
            ], null);
        },
    ];

    $tabs['follows'] = [
        'label' => translate('raids.tab.follows'),
        'order' => 10,
        'render' => static function () use ($app, $vorlagen): string {
            $kanaele = new Channels($app);

            return $vorlagen->render('tab_follows', [
                'channels'   => $kanaele->active(),
                'total'      => $kanaele->count(),
                'pending'    => $kanaele->pending(),
                'syncedAt'   => $kanaele->syncedAt(),
                'activeDays' => Channels::ACTIVE_DAYS,
                'canRead'    => $kanaele->canRead(),
                'canEdit'    => permission('Raids.Favorites.Edit'),
                'canSync'    => permission('Raids.Global.Sync'),
                'csrf'       => $app->auth->csrfToken(),
            ], null);
        },
    ];

    return $tabs;
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
//  Favorit merken und Abgleich anstossen
// -------------------------------------------------------------------
// VOR den Seitenrouten: der Router nimmt den ersten Treffer, und {tab}
// wuerde "favorite" sonst als Reitername verstehen. Bei POST gaebe es
// heute keinen Zusammenstoss - die Reiter sind GET - aber die
// Reihenfolge soll auch dann stimmen, wenn hier einmal ein GET
// dazukommt.
$router->post('/stream/raids/favorite', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/stream/raids/follows';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('Raids.Favorites.Edit')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    $login = $request->input('login');
    $an = $request->input('value') === '1';

    if (!(new Channels($app))->setFavorite($login, $an)) {
        return $zurueck($ziel, ['error' => translate('raids.error.unknown_channel')]);
    }

    // Ohne Meldung: man sieht den Stern umspringen, und ein
    // Hinweiskasten je Klick waere eine Zeile, die man wegliest.
    return $zurueck($ziel);
}, ['auth' => true]);

$router->post('/stream/raids/sync', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/stream/raids/follows';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('Raids.Global.Sync')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    $kanaele = new Channels($app);
    $anzahl = $kanaele->sync();

    if ($anzahl < 0) {
        return $zurueck($ziel, ['error' => $kanaele->error()]);
    }

    // Gleich eine erste Handvoll pruefen, damit nach dem Abgleich nicht
    // eine leere Liste dasteht und man auf den Worker wartet.
    $kanaele->refreshActivity();

    return $zurueck($ziel, [
        'notice' => translate('raids.synced', ['count' => (string) $anzahl]),
    ]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$seite = static function (Request $request, array $params = []) use ($app, $plugin): Response {
    $reiter = Raids::tabs($app);

    // Ein unbekannter Reitername fuehrt auf den ersten und nicht auf
    // eine Fehlerseite: die Adresse kann aus einem Lesezeichen kommen,
    // dessen Plugin inzwischen entfernt wurde.
    $gewuenscht = strtolower(trim((string) ($params['tab'] ?? '')));
    $offen = isset($reiter[$gewuenscht]) ? $gewuenscht : (string) array_key_first($reiter);

    $inhalt = '';
    if ($offen !== '' && ($reiter[$offen]['render'] ?? null) !== null) {
        $inhalt = (string) ($reiter[$offen]['render'])();
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'   => translate('raids.name'),
        'active'  => 'stream/raids',
        'tabs'    => $reiter,
        'open'    => $offen,
        'content' => $inhalt,
        'notice'  => (string) $request->get('notice'),
        'error'   => (string) $request->get('error'),
    ]));
};

// Ohne Reiter zuerst - sonst faengt {tab} den Aufruf.
$router->get('/stream/raids', $seite, [
    'auth'       => true,
    'permission' => 'Raids.Global.View',
]);
$router->get('/stream/raids/{tab}', $seite, [
    'auth'       => true,
    'permission' => 'Raids.Global.View',
]);
