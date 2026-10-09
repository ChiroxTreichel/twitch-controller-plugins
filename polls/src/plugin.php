<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Umfragen
 * ===================================================================
 *
 * Uebernommen aus umfragen.talutah.de. Dort war es ein eigenes System
 * mit eigener Twitch-App, JSON-Dateien und einer Liste fester Admins;
 * hier haengt es am Kern:
 *
 *   Anmeldung der Zuschauer  ueber den Login-Weg des Kerns
 *                            (core.oauth.callback), signiertes Cookie
 *   Wer verwalten darf       die Rechte des Kerns statt Share-Codes
 *   Overlay                  ein Platz in der Flaeche, live bei jeder
 *                            Stimme statt alle zehn Sekunden gefragt
 *   Ergebnis ansagen         der Worker statt einer Endlosschleife
 *
 * Adressen:
 *
 *   /tools/polls             Verwaltung: Liste, Zwischenstand, Overlay
 *   /tools/polls/new         neue Umfrage
 *   /tools/polls/settings    Discord-Webhook und Overlay
 *   /polls/{id}              die Seite fuer die Zuschauer
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Polls\Announce;
use TwitchController\Plugin\Polls\Polls;
use TwitchController\Plugin\Polls\Visitor;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Polls'] = [
        'label'       => translate('polls.name'),
        'permissions' => [
            'Polls.Global.View'   => translate('polls.perm.view'),
            'Polls.Global.Create' => translate('polls.perm.create'),
            'Polls.Global.Edit'   => translate('polls.perm.edit'),
            'Polls.Global.Delete' => translate('polls.perm.delete'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav): array {
    // Anhaengen, nicht die Gruppe setzen: Subathon und Giveaway haengen
    // in dieselbe.
    $nav['tools']['label'] = translate('polls.nav.group');
    $nav['tools']['order'] = 30;
    $nav['tools']['items'][] = [
        'label'      => translate('polls.name'),
        'href'       => '/tools/polls',
        'permission' => 'Polls.Global.View',
    ];

    return $nav;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[Polls::SLUG] = [
        'label' => translate('polls.settings'),
        'href'  => '/tools/polls/settings',
    ];

    return $links;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/polls/assets/polls.css');
    $assets['js'][] = $app->asset('/plugin/polls/assets/polls.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Overlay
// -------------------------------------------------------------------
// Immer angemeldet und leer, solange keine Umfrage darin steht. Je nach
// Auswahl an- und abmelden liesse die Browserquelle jedes Mal neu laden.
$hooks->on('overlay.slots', static function (array $slots) use ($app): array {
    $slots[Polls::SLOT] = [
        'label'    => translate('polls.name'),
        'position' => Polls::overlayPosition($app),
        'width'    => Polls::overlayWidth($app) . 'px',
    ];

    return $slots;
});

$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/polls/assets/overlay.css');

    // Reihenfolge zaehlt: state.js legt den Anfangszustand ab, overlay.js
    // zeigt ihn an. Die Leitung spielt nichts nach.
    $assets['js'][] = $app->url('/tools/polls/state.js');
    $assets['js'][] = $app->asset('/plugin/polls/assets/overlay.js');

    return $assets;
});

$router->get('/tools/polls/state.js', static function () use ($app): Response {
    return Response::html(
        'window.POLLS_STATE = ' . json_encode(
            (new Polls($app))->overlayState(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        ) . ";\n",
        200,
        [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, must-revalidate',
        ]
    );
}, ['auth' => true, 'permission' => 'Account.Overlay.View']);

// -------------------------------------------------------------------
//  Das Ergebnis ansagen, wenn die Zeit um ist
// -------------------------------------------------------------------
$hooks->on('cron.tick', static function () use ($app): void {
    (new Announce($app))->due();
});

// -------------------------------------------------------------------
//  Hilfsmittel fuer die Routen
// -------------------------------------------------------------------
$zurueck = static function (string $pfad, ?string $notice = null, ?string $error = null) use ($app): Response {
    $query = array_filter(['notice' => $notice, 'error' => $error], static fn (?string $w): bool => $w !== null && $w !== '');

    return Response::redirect($app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query)));
};

$ansicht = static fn () => $app->view->from($plugin->directory . '/views');

// -------------------------------------------------------------------
//  Verwaltung: die Liste
// -------------------------------------------------------------------
$router->get('/tools/polls', static function (Request $request) use ($app, $ansicht): Response {
    $polls = new Polls($app);
    $liste = [];

    foreach ($polls->all() as $poll) {
        $liste[] = $poll + ['results' => $polls->results($poll)];
    }

    return Response::html($ansicht()->render('page', [
        'title'       => translate('polls.name'),
        'active'      => 'tools/polls',
        'polls'       => $liste,
        'overlayPoll' => Polls::overlayPoll($app),
        'hasWebhook'  => Polls::webhook($app) !== '',
        'canCreate'   => $app->auth->can('Polls.Global.Create'),
        'canEdit'     => $app->auth->can('Polls.Global.Edit'),
        'canDelete'   => $app->auth->can('Polls.Global.Delete'),
        'csrf'        => $app->auth->csrfToken(),
        'notice'      => (string) $request->get('notice'),
        'error'       => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Polls.Global.View']);

$router->post('/tools/polls', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck('/tools/polls', null, translate('common.error.form_expired'));
    }

    $aktion = $request->input('action');
    $recht = $aktion === 'delete' ? 'Polls.Global.Delete' : 'Polls.Global.Edit';

    if (!$app->auth->can($recht)) {
        return $zurueck('/tools/polls', null, translate('common.error.no_permission'));
    }

    $polls = new Polls($app);
    $poll = $polls->find((int) $request->input('id'));

    if ($poll === null) {
        return $zurueck('/tools/polls', null, translate('polls.error.unknown'));
    }

    switch ($aktion) {
        case 'overlay':
            // Ein Platz, eine Umfrage: die neue verdraengt die alte. Ein
            // zweiter Klick auf dieselbe nimmt sie heraus.
            $an = Polls::overlayPoll($app) !== $poll['id'];
            Polls::setOverlayPoll($app, $an ? $poll['id'] : 0);
            $polls->sendOverlay();

            return $zurueck('/tools/polls', $an
                ? translate('polls.overlay_on', ['title' => $poll['title']])
                : translate('polls.overlay_off'));

        case 'end':
            $polls->endNow($poll['id']);

            return $zurueck('/tools/polls', translate('polls.ended', ['title' => $poll['title']]));

        case 'delete':
            $polls->delete($poll['id']);

            return $zurueck('/tools/polls', translate('polls.deleted', ['title' => $poll['title']]));
    }

    return $zurueck('/tools/polls', null, translate('common.error.unknown_action'));
}, ['auth' => true, 'permission' => 'Polls.Global.View']);

// -------------------------------------------------------------------
//  Verwaltung: neue Umfrage
// -------------------------------------------------------------------
$formular = static function (array $alt, array $fehler) use ($app, $ansicht): Response {
    return Response::html($ansicht()->render('new', [
        'title'      => translate('polls.new'),
        'active'     => 'tools/polls',
        'old'        => $alt,
        'errors'     => $fehler,
        'hasWebhook' => Polls::webhook($app) !== '',
        'csrf'       => $app->auth->csrfToken(),
    ]));
};

$router->get('/tools/polls/new', static function () use ($formular): Response {
    // Vorgaben wie im alten System: Dauer statt Zeitpunkt, fuenf
    // Minuten, einen Eintrag waehlen. Der feste Zeitpunkt steht auf
    // "in einer Stunde", falls man umschaltet.
    return $formular([
        'time_mode' => 'duration',
        'days'      => '0',
        'hours'     => '0',
        'minutes'   => '5',
        'ends_at'   => date('Y-m-d\TH:i', time() + 3600),
        'max_choices' => '1',
    ], []);
}, ['auth' => true, 'permission' => 'Polls.Global.Create']);

$router->post('/tools/polls/new', static function (Request $request) use ($app, $formular, $zurueck): Response {
    $felder = ['title', 'description', 'max_choices', 'time_mode', 'days', 'hours', 'minutes', 'ends_at',
        'suggestions', 'announce_discord_new', 'announce_discord_result', 'announce_chat_new', 'announce_chat_result'];

    $eingabe = [];
    foreach ($felder as $feld) {
        $eingabe[$feld] = $request->input($feld);
    }

    // Die Eintraege sind eine Liste von Feldern (options[]) - input()
    // gibt fuer eine Liste nur eine leere Zeichenkette.
    $eingabe['options'] = array_values(array_map(
        static fn (mixed $w): string => is_string($w) ? $w : '',
        is_array($request->post['options'] ?? null) ? $request->post['options'] : []
    ));

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $formular($eingabe, [translate('common.error.form_expired')]);
    }

    $polls = new Polls($app);
    $ergebnis = $polls->create($eingabe, (array) $app->auth->user());

    // Bei Fehlern dieselbe Seite mit allem, was eingetippt war - und
    // keine Weiterleitung, die es wegwirft.
    if ($ergebnis['errors'] !== []) {
        return $formular($eingabe, $ergebnis['errors']);
    }

    $poll = $polls->find($ergebnis['id']);
    if ($poll !== null) {
        (new Announce($app))->newPoll($poll);
    }

    return $zurueck('/tools/polls', translate('polls.created', ['title' => (string) ($poll['title'] ?? '')]));
}, ['auth' => true, 'permission' => 'Polls.Global.Create']);

// -------------------------------------------------------------------
//  Verwaltung: Einstellungen
// -------------------------------------------------------------------
$router->get('/tools/polls/settings', static function (Request $request) use ($app, $ansicht): Response {
    return Response::html($ansicht()->render('settings', [
        'title'     => translate('polls.settings'),
        'active'    => 'tools/polls',
        'webhook'   => Polls::webhook($app),
        'position'  => Polls::overlayPosition($app),
        'width'     => Polls::overlayWidth($app),
        'canEdit'   => $app->auth->can('Polls.Global.Edit'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Polls.Global.View']);

$router->post('/tools/polls/settings', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/tools/polls/settings';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, null, translate('common.error.form_expired'));
    }

    if (!$app->auth->can('Polls.Global.Edit')) {
        return $zurueck($ziel, null, translate('common.error.no_permission'));
    }

    if ($request->input('action') === 'test_discord') {
        $ergebnis = (new Announce($app))->discord(translate('polls.discord.bot_new'), translate('polls.discord.test'));

        return $ergebnis['ok']
            ? $zurueck($ziel, translate('polls.test_sent'))
            : $zurueck($ziel, null, $ergebnis['error']);
    }

    $fehler = Polls::saveSettings($app, [
        'discord_webhook'  => $request->input('discord_webhook'),
        'overlay_position' => $request->input('overlay_position'),
        'overlay_width'    => $request->input('overlay_width'),
    ]);

    return $fehler === '' ? $zurueck($ziel, translate('polls.saved')) : $zurueck($ziel, null, $fehler);
}, ['auth' => true, 'permission' => 'Polls.Global.View']);

// -------------------------------------------------------------------
//  Die Seite fuer die Zuschauer
// -------------------------------------------------------------------
// Oeffentlich, ohne Anmeldung am System: wer abstimmt, ist kein
// Benutzer, sondern ein Zuschauer mit Twitch-Konto. Siehe Visitor.
$oeffentlich = static function (string $vorlage, array $daten, int $status = 200) use ($app, $ansicht): Response {
    return Response::html($ansicht()->render('public/' . $vorlage, $daten + [
        'brand'    => $app->settings->string('twitch_broadcaster_name'),
        'identity' => Visitor::identity($app),
    ], 'public/_layout'), $status);
};

$router->get('/polls/{id}', static function (Request $request, array $params) use ($app, $oeffentlich): Response {
    $polls = new Polls($app);
    $poll = ctype_digit((string) $params['id']) ? $polls->find((int) $params['id']) : null;

    if ($poll === null) {
        return $oeffentlich('missing', ['title' => translate('polls.public.missing')], 404);
    }

    $ich = Visitor::identity($app);
    $offen = Polls::isOpen($poll);

    return $oeffentlich('poll', [
        'title'   => $poll['title'],
        'poll'    => $poll,
        'open'    => $offen,
        'options' => $polls->options($poll['id']),
        // Das Ergebnis erst, wenn die Zeit um ist - wie im alten System.
        // Ein Zwischenstand lenkt die, die noch abstimmen.
        'results' => $offen ? null : $polls->results($poll),
        'chosen'  => $ich === null ? [] : $polls->choicesOf($poll['id'], $ich['user_id']),
        'csrf'    => Visitor::csrfToken($app),
        'notice'  => (string) $request->get('notice'),
        'error'   => (string) $request->get('error'),
    ]);
});

$router->post('/polls/{id}', static function (Request $request, array $params) use ($app, $zurueck): Response {
    $id = ctype_digit((string) $params['id']) ? (int) $params['id'] : 0;
    $ziel = '/polls/' . $id;

    $polls = new Polls($app);
    $poll = $polls->find($id);
    $ich = Visitor::identity($app);

    if ($poll === null) {
        return Response::text('Not Found', 404);
    }

    if ($ich === null) {
        return $zurueck($ziel, null, translate('polls.public.login_first'));
    }

    if (!Visitor::checkCsrf($app, $request->input('csrf'))) {
        return $zurueck($ziel, null, translate('common.error.form_expired'));
    }

    if ($request->input('action') === 'suggest') {
        $fehler = $polls->suggest($poll, $ich, $request->input('suggestion'));

        return $fehler === '' ? $zurueck($ziel, translate('polls.public.suggested')) : $zurueck($ziel, null, $fehler);
    }

    $auswahl = $request->post['choice'] ?? [];
    $fehler = $polls->vote($poll, $ich, is_array($auswahl) ? array_values($auswahl) : [$auswahl]);

    return $fehler === '' ? $zurueck($ziel, translate('polls.public.voted')) : $zurueck($ziel, null, $fehler);
});

// Die Anmeldung. Ohne Freigaben: gebraucht wird nur die Auskunft, wer
// da ist. Die Umfrage reist im signierten state mit - danach geht es
// genau dorthin zurueck.
$router->get('/polls/{id}/login', static function (Request $request, array $params) use ($app): Response {
    $id = ctype_digit((string) $params['id']) ? (int) $params['id'] : 0;

    return Response::redirect($app->twitch->oauth()->authorizeUrl(Visitor::PURPOSE, [], false, ['poll' => $id]));
});

$router->get('/polls/{id}/logout', static function (Request $request, array $params) use ($app): Response {
    Visitor::forget($app);

    return Response::redirect($app->url('/polls/' . (ctype_digit((string) $params['id']) ? $params['id'] : '')));
});

$hooks->on('core.oauth.callback', static function (
    mixed $behandelt,
    string $zweck,
    array $token,
    array $twitchUser,
    array $extra = []
) use ($app): mixed {
    if ($behandelt instanceof Response || $zweck !== Visitor::PURPOSE) {
        return $behandelt;
    }

    // Das Twitch-Token wird weggeworfen: gebraucht war nur die
    // Auskunft, wer da ist.
    Visitor::remember(
        $app,
        (string) ($twitchUser['login'] ?? ''),
        (string) ($twitchUser['display_name'] ?? ($twitchUser['login'] ?? '')),
        (string) ($twitchUser['id'] ?? '')
    );

    return Response::redirect($app->url('/polls/' . max(0, (int) ($extra['poll'] ?? 0))));
});
