<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Streaminfo
 * ===================================================================
 *
 * Titel und Kategorie des laufenden Streams aendern, ohne Twitch zu
 * oeffnen. Im alten System war das die haeufigst benutzte Seite: eine
 * Stream-Hilfe darf damit den Titel umstellen, ohne Zugriff auf das
 * Twitch-Konto zu haben.
 *
 * Der Titel ist ein Textfeld. Die vordefinierten Titel des alten
 * Systems kommen als eigenes Plugin dazu - eine Liste von Vorlagen ist
 * eine Sache fuer sich, und wer sie nicht braucht, soll sie nicht
 * mitgeliefert bekommen.
 *
 * Die Kategorie kommt aus Twitchs Suche, mit Bild und Namen. Sie bleibt
 * hier, weil sie nicht wegzulassen ist: Twitch kennt Kategorien nur an
 * ihrer ID, und wer die von Hand eintippt, hat etwas falsch gemacht.
 *
 * Das Plugin haelt keinen Zustand: was es zeigt, steht bei Twitch.
 * Siehe install.php, warum nichts davon zwischengespeichert wird.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Streaminfo\Channel;
use TwitchController\Plugin\Streaminfo\Config;
use TwitchController\Plugin\Streaminfo\Streaminfo;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
// Getrennt fuer Titel und Kategorie, wie im alten System: eine
// Stream-Hilfe darf oft den Titel anpassen, aber nicht das Spiel
// umstellen - das aendert, wer den Kanal in den Verzeichnissen findet.
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Streaminfo'] = [
        'label'       => translate('streaminfo.name'),
        'permissions' => [
            'Streaminfo.Global.View'   => translate('streaminfo.perm.view'),
            'Streaminfo.Title.Edit'    => translate('streaminfo.perm.title'),
            'Streaminfo.Category.Edit' => translate('streaminfo.perm.category'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Die Twitch-Freigabe
// -------------------------------------------------------------------
// Nur wenn das Plugin installiert und eingeschaltet ist - dieser Hook
// laeuft ohnehin nur fuer geladene Plugins. Ohne die Freigabe zeigt die
// Seite weiter den aktuellen Stand, das Speichern ist dann gesperrt.
$hooks->on('core.twitch.broadcaster_scopes', static function (array $scopes): array {
    $scopes[] = Channel::SCOPE;

    return $scopes;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav): array {
    // Anhaengen und nicht setzen: in die Gruppe "Stream" kommen
    // weitere Werkzeuge, und wer sie setzt, laesst je nach
    // Ladereihenfolge die anderen Menuepunkte verschwinden.
    $nav['stream']['label'] = translate('streaminfo.nav.stream');
    $nav['stream']['order'] = 15;
    $nav['stream']['items'][] = [
        'label'      => translate('streaminfo.name'),
        'href'       => '/stream/info',
        'permission' => 'Streaminfo.Global.View',
    ];

    return $nav;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/streaminfo/assets/streaminfo.css');
    $assets['js'][] = $app->asset('/plugin/streaminfo/assets/streaminfo.js');
    // Zeilen anhaengen, fuer die Reiter der Erweiterungen. Hier und
    // nicht dort: zwei Kopien desselben Zuhoerers haengten je Klick
    // zwei Zeilen an.
    $assets['js'][] = $app->asset('/plugin/streaminfo/assets/rows.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Hilfsmittel fuer die Routen
// -------------------------------------------------------------------
$zurueck = static function (string $pfad, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

// -------------------------------------------------------------------
//  Die Kategorie-Suche
// -------------------------------------------------------------------
// VOR den Seitenrouten, damit die Reihenfolge auch dann stimmt, wenn
// hier spaeter ein Platzhalter dazukommt - der Router nimmt den ersten
// Treffer.
$router->get('/stream/info/categories', static function (Request $request) use ($app): Response {
    // Wer die Kategorie nicht aendern darf, braucht die Suche nicht -
    // und sie kostet bei jedem Tastendruck einen Aufruf bei Twitch.
    if (!permission('Streaminfo.Category.Edit')) {
        return Response::json(['items' => []], 403);
    }

    $kanal = new Channel($app);
    $treffer = $kanal->search((string) $request->get('q'));

    // Auch ein Fehler kommt als 200 mit leerer Liste zurueck: das Feld
    // soll beim Tippen nicht aufhoeren zu arbeiten, weil Twitch einmal
    // gehustet hat. Der Grund steht im Log.
    if ($treffer === [] && $kanal->error() !== '') {
        $app->log('Streaminfo: Kategorie-Suche fehlgeschlagen: ' . $kanal->error());
    }

    return Response::json(['items' => $treffer]);
}, ['auth' => true, 'permission' => 'Streaminfo.Global.View']);

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
// -------------------------------------------------------------------
//  Der eigene Reiter: Titel und Kategorie
// -------------------------------------------------------------------
// Streaminfo ist selbst ein Reiter unter seinen Reitern, mit order 0 -
// es ist die Arbeit, fuer die man die Seite aufruft, und steht damit
// vorn und offen.
$hooks->on('streaminfo.tabs', static function (array $tabs) use ($app, $plugin): array {
    $tabs['info'] = [
        'label' => translate('streaminfo.tab'),
        'order' => 0,
        'render' => static function () use ($app, $plugin): string {
            $kanal = new Channel($app);
            $stand = $kanal->info()
                ?? ['title' => '', 'game_id' => '', 'game_name' => '', 'language' => ''];

            // Im Textfeld steht nur der Teil, den man dort bearbeitet.
            // Was Erweiterungen vorangestellt haben - Tags zum Beispiel -
            // nehmen sie hier wieder ab; sonst editiert man ihre
            // Vorsaetze von Hand, und das naechste Speichern setzt sie
            // ein zweites Mal davor.
            $blank = Streaminfo::bare($app, $stand['title']);

            return $app->view->from($plugin->directory . '/views')->render('tab', [
                'current'      => $stand,
                'bare'         => $blank,
                'fields'       => Streaminfo::fields($app, [
                    'title'   => $stand['title'],
                    'bare'    => $blank,
                    'canEdit' => permission('Streaminfo.Title.Edit') && $kanal->canManage(),
                ]),
                'loadError'    => $kanal->error(),
                'canManage'    => $kanal->canManage(),
                'canEditTitle' => permission('Streaminfo.Title.Edit'),
                'canEditGame'  => permission('Streaminfo.Category.Edit'),
                'maxTitle'     => Config::MAX_TITLE,
                'searchUrl'    => $app->url('/stream/info/categories'),
                'csrf'         => $app->auth->csrfToken(),
            ], null);
        },
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$seite = static function (Request $request, array $params = []) use ($app, $plugin): Response {
    $reiter = Streaminfo::tabs($app);

    // Ein unbekannter Reitername fuehrt auf den ersten und nicht auf
    // eine Fehlerseite: die Adresse kann aus einem Lesezeichen kommen,
    // dessen Erweiterung inzwischen entfernt wurde.
    $gewuenscht = strtolower(trim((string) ($params['tab'] ?? '')));
    $offen = isset($reiter[$gewuenscht]) ? $gewuenscht : (string) array_key_first($reiter);

    $inhalt = '';
    if ($offen !== '' && ($reiter[$offen]['render'] ?? null) !== null) {
        $inhalt = (string) ($reiter[$offen]['render'])();
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'   => translate('streaminfo.name'),
        'active'  => 'stream/info',
        'tabs'    => $reiter,
        'open'    => $offen,
        'content' => $inhalt,
        'notice'  => (string) $request->get('notice'),
        'error'   => (string) $request->get('error'),
    ]));
};

// Ohne Reiter zuerst - sonst faengt {tab} den Aufruf.
$router->get('/stream/info', $seite, [
    'auth'       => true,
    'permission' => 'Streaminfo.Global.View',
]);
$router->get('/stream/info/{tab}', $seite, [
    'auth'       => true,
    'permission' => 'Streaminfo.Global.View',
]);

$router->post('/stream/info', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/stream/info';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    $aenderungen = [];
    $gemacht = [];

    // Nur was sich geaendert hat, und nur was der Benutzer aendern
    // darf. Ein Feld, das im Aufruf steht, nimmt Twitch als gewollt -
    // also darf hier nichts mitfahren, was niemand angefasst hat.
    $kanal = new Channel($app);
    $stand = $kanal->info();

    if ($stand === null) {
        return $zurueck($ziel, ['error' => $kanal->error()]);
    }

    if (permission('Streaminfo.Title.Edit')) {
        // Erst zusammensetzen, dann putzen: ein Plugin darf einen
        // Vorsatz anbauen, aber keinen Titel entstehen lassen, der
        // laenger ist als Twitch erlaubt.
        $titel = Config::normalizeTitle(
            Streaminfo::compose($app, (string) $request->input('title'), $request)
        );

        // Leer heisst "nicht anfassen" und nicht "Titel loeschen":
        // Twitch lehnt einen leeren Titel ohnehin ab, und ein Formular,
        // das beim Speichern den Titel wegnimmt, waere im Stream ein
        // teurer Fehlgriff.
        if ($titel !== '' && $titel !== $stand['title']) {
            $aenderungen['title'] = $titel;
            $gemacht[] = translate('streaminfo.what.title');
        }
    }

    if (permission('Streaminfo.Category.Edit')) {
        $spielId = trim((string) $request->input('game_id'));

        if ($spielId !== '' && $spielId !== $stand['game_id']) {
            $aenderungen['game_id'] = $spielId;
            $gemacht[] = translate('streaminfo.what.category');
        }
    }

    if ($aenderungen === []) {
        // Kein Fehler: wer zweimal auf Speichern drueckt, hat nichts
        // falsch gemacht. Frueher stand hier "Keine Aenderungen oder
        // keine Berechtigung." - zwei sehr verschiedene Faelle in einem
        // Satz, und man wusste hinterher nicht, welcher zutraf.
        return $zurueck($ziel, ['notice' => translate('streaminfo.unchanged')]);
    }

    if (!$kanal->canManage()) {
        return $zurueck($ziel, ['error' => translate('streaminfo.error.scope_missing')]);
    }

    if (!$kanal->update($aenderungen)) {
        return $zurueck($ziel, ['error' => $kanal->error()]);
    }

    return $zurueck($ziel, [
        'notice' => translate('streaminfo.saved', ['what' => implode(' & ', $gemacht)]),
    ]);
}, ['auth' => true]);
