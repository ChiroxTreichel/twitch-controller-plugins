<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Musik - Spotify
 * ===================================================================
 *
 * Songwuensche, wie im alten musik.talutah.de: eine oeffentliche Seite
 * unter /music, auf der sich Zuschauer nach Twitch-Anmeldung einen
 * Titel wuenschen, die Warteschlange im Overlay, und eine Bannliste
 * fuer Titel, Interpreten, Genres und Zuschauer.
 *
 * Was hier NICHT liegt: der Spotify-Player. Gespielt wird auf dem
 * Rechner des Streamers, in seinem Spotify - wir legen nur in die
 * Warteschlange und lesen, was laeuft.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\App;
use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Core\Overlay\Bus;
use TwitchController\Plugin\Music\Bans;
use TwitchController\Plugin\Music\Music;
use TwitchController\Plugin\Music\Privilege;
use TwitchController\Plugin\Music\Spotify;
use TwitchController\Plugin\Music\Visitor;
use TwitchController\Plugin\Music\Wishes;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Music'] = [
        'label'       => translate('music.name'),
        'permissions' => [
            'Music.Global.View'   => translate('music.perm.view'),
            'Music.Global.Edit'   => translate('music.perm.edit'),
            'Music.Bans.Manage'   => translate('music.perm.bans'),
            // Gilt fuer die OEFFENTLICHE Seite, nicht fuer die
            // Verwaltung: wer es hat, darf sich auch dann etwas
            // wuenschen, wenn gerade niemand darf.
            Privilege::BYPASS     => translate('music.perm.bypass'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue und Einstellungen
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Anhaengen, nicht die Gruppe setzen: Goals und Alerts haengen in
    // dieselbe, und wer sie ueberschreibt, laesst je nach
    // Ladereihenfolge deren Menuepunkt verschwinden.
    $nav['display']['label'] = translate('music.nav.display');
    $nav['display']['items'][] = [
        'label'      => translate('music.nav.item'),
        'href'       => '/display/music',
        'permission' => 'Music.Global.View',
        // Schnellschalter: die Wuensche mitten im Stream zumachen,
        // ohne erst hierher zu navigieren. Genau dafuer gab es im
        // alten Admin das Auswahlfeld ganz oben.
        'toggle'     => [
            'on'         => Music::enabled($app),
            'action'     => '/display/music/toggle',
            'value'      => 'toggle',
            'permission' => 'Music.Global.Edit',
            'title'      => translate('music.toggle_hint'),
        ],
    ];

    return $nav;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[Music::SLUG] = [
        'label' => translate('music.settings'),
        'href'  => '/display/music/settings',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Platz im Overlay
// -------------------------------------------------------------------
$hooks->on('overlay.slots', static function (array $slots) use ($app): array {
    $slots[Music::SLUG] = [
        'label'    => translate('music.name'),
        // Wie im alten obs.php: eine Leiste, und die stand unten.
        'position' => 'bottom-left',
        'width'    => Music::width($app) . 'px',
        'height'   => Music::height($app) . 'px',
        // Unter den Alerts, ueber den Zielen: ein Alert soll den
        // laufenden Titel verdecken duerfen, ein Zielbalken nicht.
        'z'        => 25,
    ];

    return $slots;
});

$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/music/assets/overlay.css');
    $assets['js'][] = $app->asset('/plugin/music/assets/overlay.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Der Takt: was laeuft gerade?
// -------------------------------------------------------------------
/*
 * Im alten System war das eine Endlosschleife (src/Cronjob.php) mit
 * sleep(5), die jemand von Hand starten musste - und die nach einem
 * Neustart des Servers stand, bis es jemandem auffiel.
 *
 * Hier haengt es am Takt des Workers. Der laeuft ohnehin, und er
 * laeuft wieder an, wenn Docker ihn neu startet.
 *
 * Geschickt wird NUR bei Aenderung. Die Leitung ins Overlay hebt jede
 * Nachricht auf, damit eine neu verbundene Quelle den Stand bekommt -
 * eine Nachricht alle fuenfzehn Sekunden, in der dasselbe steht, waere
 * ein Tagebuch ohne Inhalt.
 */
$hooks->on('cron.tick', static function () use ($app): void {
    if (!Music::isConnected($app)) {
        return;
    }

    $spotify = new Spotify($app);
    $laeuft = $spotify->currentlyPlaying();

    $titel = is_array($laeuft) ? ($laeuft['item'] ?? null) : null;
    $uri = is_array($titel) ? (string) ($titel['uri'] ?? '') : '';

    $zustand = [
        'playing'  => is_array($laeuft) ? (bool) ($laeuft['is_playing'] ?? false) : false,
        'uri'      => $uri,
        'name'     => is_array($titel) ? (string) ($titel['name'] ?? '') : '',
        'artists'  => is_array($titel) ? implode(', ', array_filter(array_map(
            static fn (array $a): string => (string) ($a['name'] ?? ''),
            (array) ($titel['artists'] ?? [])
        ))) : '',
        'image'    => is_array($titel) ? (string) ($titel['album']['images'][0]['url'] ?? '') : '',
        'duration' => is_array($titel) ? (int) ($titel['duration_ms'] ?? 0) : 0,
        'progress' => is_array($laeuft) ? (int) ($laeuft['progress_ms'] ?? 0) : 0,
        'wishedBy' => $uri !== '' ? Wishes::wishedBy($app, $uri) : '',
    ];

    /*
     * Der Fortschritt bleibt beim Vergleich aussen vor: er aendert
     * sich bei jedem Takt, und dann waere jede Nachricht neu. Das
     * Overlay zaehlt selbst weiter - es bekommt Dauer und Stand und
     * rechnet dazwischen.
     */
    $vergleich = $zustand;
    unset($vergleich['progress']);

    $merker = (string) json_encode($vergleich);

    if ($merker !== $app->settings->string('overlay_state', '', Music::scope())) {
        $app->settings->set('overlay_state', $merker, Music::scope());
        (new Bus($app))->send(Music::SLUG, $zustand);
    }

    Wishes::cleanup($app);
});

// -------------------------------------------------------------------
//  Die Rueckkehr von Spotify
// -------------------------------------------------------------------
// Spotify schickt den Betreiber hierher zurueck. Eine eigene Route und
// kein Hook am Twitch-Rueckweg: das ist eine andere Anmeldung bei
// einem anderen Dienst, und sie hat mit Twitch nichts zu tun.
$router->get('/account/music/callback', static function (Request $request) use ($app): Response {
    $zurueck = $app->url('/display/music/settings');

    $fehler = trim($request->get('error'));

    if ($fehler !== '') {
        return Response::redirect($zurueck . '?error=' . rawurlencode($fehler));
    }

    /*
     * Der Zustandswert ist das Formularmerkmal dieser Anmeldung: ohne
     * ihn koennte eine fremde Seite den Betreiber auf eine
     * vorbereitete Spotify-Anmeldung schicken und damit IHR Konto
     * hier eintragen.
     */
    if (!$app->auth->checkCsrf($request->get('state'))) {
        return Response::redirect($zurueck . '?error=' . rawurlencode(translate('common.error.form_expired')));
    }

    $code = trim($request->get('code'));

    if ($code === '') {
        return Response::redirect($zurueck . '?error=' . rawurlencode(translate('music.error.no_code')));
    }

    $ergebnis = (new Spotify($app))->exchangeCode($code);

    return Response::redirect($zurueck . ($ergebnis['ok']
        ? '?notice=' . rawurlencode(translate('music.connected'))
        : '?error=' . rawurlencode($ergebnis['error'])));
}, ['auth' => true, 'permission' => 'Music.Global.Edit']);

// -------------------------------------------------------------------
//  Verwaltung: die Bannliste
// -------------------------------------------------------------------
$zurueckVerwaltung = static function (App $app, ?string $notice = null, ?string $error = null, array $extra = []): Response {
    $query = array_filter([
        'notice' => $notice,
        'error'  => $error,
    ] + $extra, static fn (?string $wert): bool => $wert !== null && $wert !== '');

    return Response::redirect(
        $app->url('/display/music') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/music', static function (Request $request) use ($app): Response {
    $spotify = new Spotify($app);

    /*
     * Die Suche steht in der ADRESSE und nicht im Formularergebnis.
     *
     * So laesst sich ein Suchergebnis verschicken und neu laden, und
     * der Zurueck-Knopf des Browsers tut, was er soll. Im alten System
     * war es ein POST, und jedes Neuladen fragte "Formular erneut
     * senden?".
     */
    $suche = trim($request->get('q'));
    $art = $request->get('kind');
    $art = in_array($art, ['track', 'artist'], true) ? $art : 'track';

    $treffer = [];

    if ($suche !== '' && Music::isConnected($app)) {
        $treffer = $art === 'artist'
            ? $spotify->searchArtists($suche, 12)
            : $spotify->searchTracks($suche, 12);
    }

    return Response::html($app->view->render('page', [
        'title'     => translate('music.name'),
        'active'    => 'display/music',
        'enabled'   => Music::enabled($app),
        'connected' => Music::isConnected($app),
        'bans'      => Bans::all($app),
        'wishes'    => Wishes::recent($app, 20),
        'kind'      => $art,
        'query'     => $suche,
        'results'   => $treffer,
        'canEdit'   => $app->auth->can('Music.Bans.Manage'),
        'canToggle' => $app->auth->can('Music.Global.Edit'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => $request->get('notice'),
        'error'     => $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Music.Global.View']);

$router->post('/display/music/toggle', static function (Request $request) use ($app, $zurueckVerwaltung): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckVerwaltung($app, null, translate('common.error.form_expired'));
    }

    if (!$app->auth->can('Music.Global.Edit')) {
        return $zurueckVerwaltung($app, null, translate('common.error.no_permission'));
    }

    $an = !Music::enabled($app);
    Music::setEnabled($app, $an);

    return $zurueckVerwaltung($app, $an ? translate('music.turned_on') : translate('music.turned_off'));
}, ['auth' => true]);

$router->post('/display/music', static function (Request $request) use ($app, $zurueckVerwaltung): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckVerwaltung($app, null, translate('common.error.form_expired'));
    }

    if (!$app->auth->can('Music.Bans.Manage')) {
        return $zurueckVerwaltung($app, null, translate('common.error.no_permission'));
    }

    $behalten = [
        'q'    => trim($request->input('q')),
        'kind' => $request->input('kind'),
    ];

    switch ($request->input('action')) {
        case 'ban':
            $art = (string) $request->input('kind_add');
            $schluessel = trim($request->input('key'));
            $name = trim($request->input('name'));

            if (!Bans::add($app, $art, $schluessel, $name, trim($request->input('detail')), $app->auth->user()['login'] ?? '')) {
                return $zurueckVerwaltung($app, null, translate('music.ban.failed'), $behalten);
            }

            return $zurueckVerwaltung($app, translate('music.ban.added', ['name' => $name !== '' ? $name : $schluessel]), null, $behalten);

        case 'unban':
            Bans::remove($app, (string) $request->input('kind_add'), trim($request->input('key')));

            return $zurueckVerwaltung($app, translate('music.ban.removed'), null, $behalten);
    }

    return $zurueckVerwaltung($app, null, translate('common.error.unknown_action'), $behalten);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Verwaltung: die Einstellungen
// -------------------------------------------------------------------
$zurueckEinstellungen = static function (App $app, ?string $notice = null, ?string $error = null): Response {
    $query = array_filter(['notice' => $notice, 'error' => $error], static fn (?string $w): bool => $w !== null && $w !== '');

    return Response::redirect(
        $app->url('/display/music/settings') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/music/settings', static function (Request $request) use ($app): Response {
    return Response::html($app->view->render('settings', [
        'title'       => translate('music.settings'),
        'active'      => 'display/music',
        'enabled'     => Music::enabled($app),
        'cooldown'    => Music::cooldown($app),
        'rules'       => implode("\n", Music::rules($app)),
        'width'       => Music::width($app),
        'height'      => Music::height($app),
        'clientId'    => Music::clientId($app),
        'hasSecret'   => $app->settings->hasSecret('client_secret', Music::scope()),
        'hasCreds'    => Music::hasCredentials($app),
        'connected'   => Music::isConnected($app),
        'account'     => Music::accountName($app),
        'redirectUri' => Music::redirectUri($app),
        'publicUrl'   => $app->url('/music'),
        'canEdit'     => $app->auth->can('Music.Global.Edit'),
        'csrf'        => $app->auth->csrfToken(),
        'notice'      => $request->get('notice'),
        'error'       => $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Music.Global.View']);

$router->post('/display/music/settings', static function (Request $request) use ($app, $zurueckEinstellungen): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckEinstellungen($app, null, translate('common.error.form_expired'));
    }

    if (!$app->auth->can('Music.Global.Edit')) {
        return $zurueckEinstellungen($app, null, translate('common.error.no_permission'));
    }

    switch ($request->input('action')) {
        case 'save':
            $app->settings->set('cooldown', (int) $request->input('cooldown'), Music::scope());
            $app->settings->set('width', Music::size((int) $request->input('width'), Music::DEFAULT_WIDTH), Music::scope());
            $app->settings->set('height', Music::size((int) $request->input('height'), Music::DEFAULT_HEIGHT), Music::scope());
            Music::setRules($app, (string) $request->input('rules'));

            return $zurueckEinstellungen($app, translate('music.saved'));

        case 'credentials':
            $app->settings->set('client_id', trim($request->input('client_id')), Music::scope());

            /*
             * Ein leeres Feld heisst "nicht aendern" und nicht
             * "loeschen". Sonst wuerfe ein Speichern der Abkuehlzeit
             * nebenbei den Zugang zum Spotify-Konto weg - das Geheimnis
             * wird ja nie wieder angezeigt.
             */
            $geheim = trim($request->input('client_secret'));

            if ($geheim !== '') {
                $app->settings->setSecret('client_secret', $geheim, Music::scope());
            }

            return $zurueckEinstellungen($app, translate('music.saved'));

        case 'connect':
            if (!Music::hasCredentials($app)) {
                return $zurueckEinstellungen($app, null, translate('music.error.no_credentials'));
            }

            // Der Zustandswert ist das Formularmerkmal - siehe die
            // Rueckkehr oben.
            return Response::redirect((new Spotify($app))->authorizeUrl($app->auth->csrfToken()));

        case 'disconnect':
            Music::disconnect($app);

            return $zurueckEinstellungen($app, translate('music.disconnected'));
    }

    return $zurueckEinstellungen($app, null, translate('common.error.unknown_action'));
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die oeffentliche Seite
// -------------------------------------------------------------------
/*
 * Sie hat ihren eigenen Rahmen und nicht den der Verwaltung: hier ist
 * niemand angemeldet, es gibt kein Menue, und die Seite laeuft auf
 * fremden Telefonen.
 */
$oeffentlich = static function (App $app, string $vorlage, array $daten = []) use ($plugin): Response {
    $ich = Visitor::identity($app);

    return Response::html($app->view->render('public/' . $vorlage, $daten + [
        'identity'  => $ich,
        'enabled'   => Music::enabled($app),
        'bypass'    => Privilege::mayBypass($app, $ich),
        'rules'     => Music::rules($app),
        'accepted'  => Visitor::hasAcceptedRules($app, $ich),
        'cooldown'  => Music::cooldown($app),
        'csrf'      => Visitor::csrfToken($app),
        'brand'     => $app->settings->string('twitch_broadcaster_name'),
        'notice'    => '',
        'error'     => '',
    ], 'public/_layout'));
};

/**
 * Darf dieser Besucher gerade wuenschen - und wenn nicht, warum?
 *
 * Eine Funktion und nicht drei Pruefungen an drei Stellen: die Seite
 * zeigt den Grund an, das Formular nimmt ihn entgegen, und beide
 * muessen dasselbe sagen. Zwei Kopien derselben Bedingung laufen
 * auseinander, sobald eine davon waechst.
 *
 * @return array{ok: bool, reason: string, until: int}
 */
$darfWuenschen = static function (App $app, ?array $ich): array {
    if ($ich === null) {
        return ['ok' => false, 'reason' => 'login', 'until' => 0];
    }

    if (Bans::isViewerBanned($app, $ich['login'])) {
        return ['ok' => false, 'reason' => 'banned', 'until' => 0];
    }

    if (!Visitor::hasAcceptedRules($app, $ich)) {
        return ['ok' => false, 'reason' => 'rules', 'until' => 0];
    }

    /*
     * "Grenzen ignorieren" hebt beides auf: den Schalter und die
     * Wartezeit. Die Bannliste hebt es NICHT auf - siehe Privilege.
     */
    if (Privilege::mayBypass($app, $ich)) {
        return ['ok' => true, 'reason' => '', 'until' => 0];
    }

    if (!Music::enabled($app)) {
        return ['ok' => false, 'reason' => 'off', 'until' => 0];
    }

    $frei = Wishes::nextAllowed($app, $ich['user_id']);

    if ($frei > 0) {
        return ['ok' => false, 'reason' => 'cooldown', 'until' => $frei];
    }

    return ['ok' => true, 'reason' => '', 'until' => 0];
};

$router->get('/music', static function (Request $request) use ($app, $oeffentlich, $darfWuenschen): Response {
    $ich = Visitor::identity($app);

    return $oeffentlich($app, 'index', [
        'title'     => translate('music.public.title'),
        'may'       => $darfWuenschen($app, $ich),
        'connected' => Music::isConnected($app),
        'notice'    => $request->get('notice'),
        'error'     => $request->get('error'),
    ]);
});

// Die Anmeldung. Ohne Freigaben: gebraucht wird nur die Auskunft, wer
// da ist.
$router->get('/music/login', static function () use ($app): Response {
    return Response::redirect($app->twitch->oauth()->authorizeUrl(Visitor::PURPOSE, [], false));
});

$router->get('/music/logout', static function () use ($app): Response {
    Visitor::forget($app);

    return Response::redirect($app->url('/music'));
});

$hooks->on('core.oauth.callback', static function (
    mixed $behandelt,
    string $zweck,
    array $token,
    array $twitchUser
) use ($app): mixed {
    if ($behandelt instanceof Response || $zweck !== Visitor::PURPOSE) {
        return $behandelt;
    }

    /*
     * Das Twitch-Token wird WEGGEWORFEN. Gebraucht war die Auskunft,
     * wer da ist - und ein Token, das man nicht braucht, ist nur noch
     * etwas, das gestohlen werden kann.
     */
    Visitor::remember(
        $app,
        (string) ($twitchUser['login'] ?? ''),
        (string) ($twitchUser['display_name'] ?? ($twitchUser['login'] ?? '')),
        (string) ($twitchUser['id'] ?? '')
    );

    return Response::redirect($app->url('/music'));
});

/**
 * Die Warteschlange als JSON - die Seite holt sie im Takt nach.
 *
 * Oeffentlich und ohne Anmeldung: es steht nichts darin, was nicht
 * ohnehin im Stream zu hoeren ist. Der Name des Wuenschenden steht
 * dabei - so wie er auch im Overlay steht.
 */
$router->get('/music/queue', static function () use ($app): Response {
    if (!Music::isConnected($app)) {
        return Response::json(['ok' => false, 'items' => [], 'current' => null]);
    }

    $spotify = new Spotify($app);
    $warteschlange = $spotify->queue();
    $laeuft = $spotify->currentlyPlaying();

    $eintraege = [];
    $uris = [];

    foreach ((array) (is_array($warteschlange) ? ($warteschlange['queue'] ?? []) : []) as $eintrag) {
        if (!is_array($eintrag)) {
            continue;
        }

        $uris[] = (string) ($eintrag['uri'] ?? '');
        $eintraege[] = $eintrag;
    }

    $aktuell = is_array($laeuft) ? ($laeuft['item'] ?? null) : null;

    if (is_array($aktuell)) {
        $uris[] = (string) ($aktuell['uri'] ?? '');
    }

    $wer = Wishes::wishedByMany($app, $uris);

    $schlank = static function (?array $titel) use ($wer): ?array {
        if (!is_array($titel)) {
            return null;
        }

        $uri = (string) ($titel['uri'] ?? '');

        /*
         * Nur was die Seite anzeigt. Spotify schickt zu jedem Titel
         * eine Liste aller Laender, in denen er verfuegbar ist - das
         * sind mehrere Kilobyte je Eintrag, und die Warteschlange hat
         * zwanzig. Das alte System hat sie aus demselben Grund
         * herausgeworfen.
         */
        return [
            'uri'      => $uri,
            'id'       => (string) ($titel['id'] ?? ''),
            'name'     => (string) ($titel['name'] ?? ''),
            'artists'  => implode(', ', array_filter(array_map(
                static fn (array $a): string => (string) ($a['name'] ?? ''),
                (array) ($titel['artists'] ?? [])
            ))),
            'image'    => (string) ($titel['album']['images'][0]['url'] ?? ''),
            'url'      => (string) ($titel['external_urls']['spotify'] ?? ''),
            'wishedBy' => $wer[$uri] ?? '',
        ];
    };

    return Response::json([
        'ok'      => true,
        'current' => $schlank(is_array($aktuell) ? $aktuell : null),
        'items'   => array_values(array_filter(array_map($schlank, $eintraege))),
    ]);
});

$router->post('/music', static function (Request $request) use ($app, $darfWuenschen): Response {
    $zurueck = static function (?string $notice = null, ?string $error = null) use ($app): Response {
        $query = array_filter(['notice' => $notice, 'error' => $error], static fn (?string $w): bool => $w !== null && $w !== '');

        return Response::redirect($app->url('/music') . ($query === [] ? '' : '?' . http_build_query($query)));
    };

    if (!Visitor::checkCsrf($app, (string) $request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    $ich = Visitor::identity($app);

    if ($ich === null) {
        return $zurueck(null, translate('music.public.login_first'));
    }

    if ($request->input('action') === 'accept_rules') {
        Visitor::acceptRules($app, $ich['user_id']);

        return $zurueck(translate('music.public.rules_accepted'));
    }

    if ($request->input('action') !== 'wish') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    $darf = $darfWuenschen($app, $ich);

    if (!$darf['ok']) {
        return $zurueck(null, \TwitchController\Plugin\Music\Texts::denied($darf['reason']));
    }

    $uri = \TwitchController\Plugin\Music\Link::toUri(trim((string) $request->input('link')));

    if ($uri === null) {
        return $zurueck(null, translate('music.public.bad_link'));
    }

    $spotify = new Spotify($app);
    $titel = $spotify->track(\TwitchController\Plugin\Music\Link::trackId($uri) ?? '');

    if ($titel === null) {
        return $zurueck(null, translate('music.public.track_unknown'));
    }

    // Die Genres haengen bei Spotify am INTERPRETEN, nicht am Titel -
    // genau so prueft es das alte System.
    $interpreten = array_values(array_filter(array_map(
        static fn (array $a): string => (string) ($a['id'] ?? ''),
        (array) ($titel['artists'] ?? [])
    )));

    $genres = [];

    foreach ($spotify->artists($interpreten) as $interpret) {
        foreach ((array) ($interpret['genres'] ?? []) as $genre) {
            $genres[] = (string) $genre;
        }
    }

    $gesperrt = Bans::check($app, (string) ($titel['id'] ?? ''), $interpreten, $genres);

    if ($gesperrt !== null) {
        return $zurueck(null, \TwitchController\Plugin\Music\Texts::banned($gesperrt['kind'], $gesperrt['name']));
    }

    if (!$spotify->enqueue($uri)) {
        return $zurueck(null, translate('music.public.queue_failed'));
    }

    Wishes::add(
        $app,
        $uri,
        (string) ($titel['name'] ?? ''),
        implode(', ', array_filter(array_map(
            static fn (array $a): string => (string) ($a['name'] ?? ''),
            (array) ($titel['artists'] ?? [])
        ))),
        $ich['user_id'],
        $ich['display_name']
    );

    return $zurueck(translate('music.public.wished'));
});
