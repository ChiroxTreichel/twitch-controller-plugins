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
use TwitchController\Plugin\Music\Favorites;
use TwitchController\Plugin\Music\Link;
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

    /*
     * Reihenfolge zaehlt: state.js legt den Anfangszustand ab,
     * overlay.js zeigt ihn an.
     *
     * Ohne den Anfangszustand blieb der Platz leer, obwohl Musik lief.
     * Die Leitung ins Overlay beginnt bei der hoechsten bekannten
     * Nachrichtennummer und spielt nichts nach - und der Takt schickt
     * nur BEI AENDERUNG. Eine frisch geladene Browserquelle bekam also
     * nichts, bis der Titel wechselte.
     */
    $assets['js'][] = $app->url('/display/music/state.js');
    $assets['js'][] = $app->asset('/plugin/music/assets/overlay.js');

    return $assets;
});

$router->get('/display/music/state.js', static function () use ($app): Response {
    $js = 'window.MUSIC_STATE = ' . json_encode(
        Music::overlayState($app),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . ";\n";

    return Response::html($js, 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        // Nicht zwischenspeichern: hier steht, was GERADE laeuft.
        'Cache-Control' => 'no-store, must-revalidate',
    ]);
}, ['auth' => true, 'permission' => 'Account.Overlay.View']);

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

    $zustand = Music::overlayState($app);

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

$router->get('/display/music', static function (Request $request) use ($app, $plugin): Response {
    $spotify = new Spotify($app);

    /*
     * Der Reiter IST die Art. Auf "Titel" sucht man Titel, auf
     * "Interpreten" Interpreten - ein zweites Auswahlfeld daneben
     * waere eine zweite Stelle, an der dasselbe steht.
     *
     * Und er steht in der ADRESSE, wie die Suche: so laesst sich ein
     * Ergebnis verschicken und neu laden, und der Zurueck-Knopf des
     * Browsers tut, was er soll. Im alten System war die Suche ein
     * POST, und jedes Neuladen fragte "Formular erneut senden?".
     */
    $reiter = (string) $request->get('tab');
    $reiter = in_array($reiter, ['track', 'artist', 'genre', 'twitch', 'wishes'], true)
        ? $reiter
        : 'track';

    $suche = trim($request->get('q'));

    $treffer = [];

    if ($suche !== '' && Music::isConnected($app) && in_array($reiter, ['track', 'artist'], true)) {
        $treffer = $reiter === 'artist'
            ? $spotify->searchArtists($suche, 12)
            : $spotify->searchTracks($suche, 12);
    }

    /*
     * Alle vier Listen werden geholt, obwohl nur eine angezeigt wird -
     * die Zahlen stehen an den Reitern. Ohne sie muesste man jeden
     * aufmachen, um zu sehen, wo ueberhaupt etwas drinsteht, und das
     * ist der eine Vorteil, den die alte Ansicht untereinander hatte.
     */
    $alle = Bans::all($app);

    $zahlen = [];

    foreach ($alle as $art => $eintraege) {
        $zahlen[$art] = count($eintraege);
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'     => translate('music.name'),
        'active'    => 'display/music',
        'enabled'   => Music::enabled($app),
        'connected' => Music::isConnected($app),
        'tab'       => $reiter,
        'entries'   => $alle[$reiter] ?? [],
        'counts'    => $zahlen,
        'wishes'    => $reiter === 'wishes' ? Wishes::recent($app, 50) : [],
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
        'q'   => trim($request->input('q')),
        'tab' => $request->input('tab'),
    ];

    switch ($request->input('action')) {
        case 'ban':
            $art = (string) $request->input('kind_add');
            $schluessel = trim($request->input('key'));
            $name = trim($request->input('name'));
            $zusatz = trim($request->input('detail'));

            /*
             * Bei Titeln und Interpreten darf auch ein Link eingefuegt
             * werden - das Feld daneben ist dafuer da. Kommt einer, wird
             * daraus die Kennung, und der NAME wird bei Spotify geholt:
             * eine Liste voller "4cOdK2wGLETKBW3PvgPWqT" waere keine
             * Liste, sondern ein Raetsel.
             *
             * Aus der Suche kommt der Name schon mit; dann passiert
             * hier nichts.
             */
            if (in_array($art, ['track', 'artist'], true) && $name === '') {
                $kennung = Link::idFor($art, $schluessel);

                if ($kennung === null) {
                    return $zurueckVerwaltung($app, null, translate('music.ban.bad_link'), $behalten);
                }

                $schluessel = $kennung;

                if (Music::isConnected($app)) {
                    $spotify = new Spotify($app);

                    if ($art === 'track') {
                        $titel = $spotify->track($kennung);

                        if (is_array($titel)) {
                            $name = (string) ($titel['name'] ?? '');
                            $zusatz = implode(', ', array_filter(array_map(
                                static fn (array $a): string => (string) ($a['name'] ?? ''),
                                (array) ($titel['artists'] ?? [])
                            )));
                        }
                    } else {
                        $interpreten = $spotify->artists([$kennung]);
                        $interpret = $interpreten[0] ?? null;

                        if (is_array($interpret)) {
                            $name = (string) ($interpret['name'] ?? '');
                            $zusatz = implode(', ', array_slice((array) ($interpret['genres'] ?? []), 0, 4));
                        }
                    }
                }

                /*
                 * Spotify kennt die Kennung nicht - dann steht sie auf
                 * der Liste, aber sie wird nie greifen. Lieber jetzt
                 * sagen als spaeter suchen lassen.
                 */
                if ($name === '') {
                    return $zurueckVerwaltung($app, null, translate('music.ban.unknown'), $behalten);
                }
            }

            if (!Bans::add($app, $art, $schluessel, $name, $zusatz, $app->auth->user()['login'] ?? '')) {
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

$router->get('/display/music/settings', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
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
        'panelUrl'    => $app->url('/music/panel'),
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

    return Response::html($app->view->from($plugin->directory . '/views')->render('public/' . $vorlage, $daten + [
        'identity'  => $ich,
        'enabled'   => Music::enabled($app),
        'bypass'    => Privilege::mayBypass($app, $ich),
        'rules'     => Music::rules($app),
        'accepted'  => Visitor::hasAcceptedRules($app, $ich),
        'favorites' => [],
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
        'favorites' => $ich === null ? [] : Favorites::of($app, $ich['user_id']),
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

    /*
     * Und was eben lief. Die ganz alte Fassung des alten Systems hatte
     * das zwischen "Laeuft gerade" und "Als Naechstes" - drei Titel,
     * und damit die Antwort auf "wie hiess das eben nochmal?", die
     * sonst im Chat landet.
     *
     * Spotify liefert hier Eintraege mit einem "track" darin, nicht
     * den Titel selbst - eine andere Form als bei der Warteschlange.
     */
    $vorher = [];

    foreach ((array) (is_array($zuletzt = $spotify->recent(3)) ? ($zuletzt['items'] ?? []) : []) as $eintrag) {
        if (is_array($eintrag) && is_array($eintrag['track'] ?? null)) {
            $vorher[] = $eintrag['track'];
        }
    }

    /*
     * Der laufende Titel steht bei Spotify auch schon in "zuletzt
     * gespielt", sobald er ein paar Sekunden laeuft. Zweimal
     * untereinander sieht nach Fehler aus.
     */
    $laufendeUri = is_array($aktuell) ? (string) ($aktuell['uri'] ?? '') : '';

    $vorher = array_values(array_filter(
        $vorher,
        static fn (array $titel): bool => (string) ($titel['uri'] ?? '') !== $laufendeUri
    ));

    return Response::json([
        'ok'      => true,
        'current' => $schlank(is_array($aktuell) ? $aktuell : null),
        'recent'  => array_values(array_filter(array_map($schlank, array_slice($vorher, 0, 3)))),
        'items'   => array_values(array_filter(array_map($schlank, $eintraege))),
    ]);
});

// -------------------------------------------------------------------
//  Das Panel: Text fuer OBS
// -------------------------------------------------------------------
/*
 * Aus dem alten System uebernommen (public/panel.php): reiner Text,
 * kein HTML. Er ist fuer eine Textquelle in OBS gedacht, die eine
 * Adresse ausliest - und die zeigt HTML als HTML an.
 *
 * Aufbau Zeile fuer Zeile wie dort: Songname, Interpret, "Von", dann
 * die Warteschlange mit fuenf Eintraegen und "-> von X" darunter.
 */
$router->get('/music/panel', static function () use ($app): Response {
    $zeilen = [];

    if (Music::isConnected($app)) {
        $spotify = new Spotify($app);
        $laeuft = $spotify->currentlyPlaying();
        $titel = is_array($laeuft) ? ($laeuft['item'] ?? null) : null;

        $name = static fn (?array $eines): string => is_array($eines)
            ? (string) ($eines['artists'][0]['name'] ?? '')
            : '';

        if (is_array($titel)) {
            $uri = (string) ($titel['uri'] ?? '');
            $wer = Wishes::wishedBy($app, $uri);

            $zeilen[] = translate('music.panel.track');
            $zeilen[] = '     ' . (string) ($titel['name'] ?? '');
            $zeilen[] = '';
            $zeilen[] = translate('music.panel.artist');
            $zeilen[] = '     ' . $name($titel);
            $zeilen[] = '';

            if ($wer !== '') {
                $zeilen[] = translate('music.panel.by');
                $zeilen[] = '     ' . $wer;
                $zeilen[] = '';
            }
        }

        $zeilen[] = translate('music.panel.queue');
        $zeilen[] = '';

        $warteschlange = $spotify->queue();
        $eintraege = array_slice(
            (array) (is_array($warteschlange) ? ($warteschlange['queue'] ?? []) : []),
            0,
            5
        );

        $uris = array_map(
            static fn (array $eines): string => (string) ($eines['uri'] ?? ''),
            array_filter($eintraege, 'is_array')
        );

        $wer = Wishes::wishedByMany($app, array_values($uris));

        foreach ($eintraege as $eines) {
            if (!is_array($eines)) {
                continue;
            }

            $zeilen[] = '   ' . $name($eines) . ' - ' . (string) ($eines['name'] ?? '');

            $wunsch = $wer[(string) ($eines['uri'] ?? '')] ?? '';

            if ($wunsch !== '') {
                $zeilen[] = '      -> ' . translate('music.panel.from', ['name' => $wunsch]);
            }

            $zeilen[] = '';
        }
    }

    /*
     * text/plain und ohne Zwischenspeicher: OBS liest die Adresse in
     * einem eigenen Takt, und ein Browser-Zwischenspeicher zeigte
     * dort den Titel von vorhin.
     */
    return Response::html(implode("\n", $zeilen), 200, [
        'Content-Type'  => 'text/plain; charset=utf-8',
        'Cache-Control' => 'no-store, must-revalidate',
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

    /*
     * Vergessen geht immer - auch wenn gerade nicht gewuenscht werden
     * darf. Es ist die eigene Liste, und sie aufzuraeumen hat mit dem
     * Andrang nichts zu tun.
     */
    if ($request->input('action') === 'unfavorite') {
        Favorites::remove($app, $ich['user_id'], trim((string) $request->input('track')));

        return $zurueck(translate('music.public.unfavorited'));
    }

    if (!in_array($request->input('action'), ['wish', 'favorite'], true)) {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    $spotify = new Spotify($app);

    /*
     * Zwei Wege zu demselben Titel: ein eingefuegter Link, oder einer
     * aus der Merkliste. Der zweite spart den Weg zu Spotify - was
     * dort steht, wurde beim Merken schon geholt.
     */
    $gemerkt = trim((string) $request->input('track'));

    if ($gemerkt !== '') {
        $eintrag = Favorites::find($app, $ich['user_id'], $gemerkt);

        if ($eintrag === null) {
            return $zurueck(null, translate('music.public.not_favorited'));
        }

        $uri = (string) $eintrag['track_uri'];
        $titel = $spotify->track($gemerkt);
    } else {
        $uri = \TwitchController\Plugin\Music\Link::toUri(trim((string) $request->input('link')));

        if ($uri === null) {
            return $zurueck(null, translate('music.public.bad_link'));
        }

        $titel = $spotify->track(\TwitchController\Plugin\Music\Link::trackId($uri) ?? '');
    }

    if ($titel === null) {
        return $zurueck(null, translate('music.public.track_unknown'));
    }

    /*
     * Merken ist KEIN Wunsch: es landet nichts in der Warteschlange,
     * niemand hoert es, und die Wartezeit hat damit nichts zu tun.
     * Deshalb steht es vor der Pruefung - und die Bannliste auch
     * nicht: was gesperrt ist, merkt man sich vergeblich, aber
     * erfahren tut man das beim Wuenschen.
     */
    if ($request->input('action') === 'favorite') {
        return Favorites::add($app, $ich['user_id'], $titel)
            ? $zurueck(translate('music.public.favorited'))
            : $zurueck(null, translate('music.public.favorite_failed'));
    }

    $darf = $darfWuenschen($app, $ich);

    if (!$darf['ok']) {
        return $zurueck(null, \TwitchController\Plugin\Music\Texts::denied($darf['reason']));
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
