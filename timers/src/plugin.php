<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Timer
 * ===================================================================
 *
 * Wiederkehrende Nachrichten im Chat - aber nur, wenn der Stream
 * laeuft. Das ist die wichtigste Bedingung: ein Timer, der in einen
 * leeren Chat postet, ist nur Muell im Verlauf.
 *
 * Dazu drei weitere Bedingungen je Timer: das Intervall, eine
 * Mindestzahl an Chatzeilen seit dem letzten Mal, und optional Titel
 * und Kategorie des Streams.
 *
 * Gezaehlt wird im Webhook (core.chat.message), gepostet im
 * Hintergrundprozess (cron.tick) - siehe src/Runner.php.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Timers\Runner;
use TwitchController\Plugin\Timers\State;
use TwitchController\Plugin\Timers\Stream;
use TwitchController\Plugin\Timers\Timers;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Timers'] = [
        'label'       => translate('timers.name'),
        'permissions' => [
            'Timers.Global.View'   => translate('timers.perm.view'),
            'Timers.Global.Edit'   => translate('timers.perm.edit'),
            'Timers.Global.Toggle' => translate('timers.perm.toggle'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Eintraege ANHAENGEN, nicht die Gruppe setzen: die Chatbefehle
    // haengen in dieselbe Gruppe, und wer sie ueberschreibt, laesst je
    // nach Ladereihenfolge den anderen Menuepunkt verschwinden.
    $nav['chat']['label'] = translate('timers.nav.chat');
    $nav['chat']['order'] = 20;
    $nav['chat']['items'][] = [
        'label'      => translate('timers.name'),
        'href'       => '/chat/timers',
        'permission' => 'Timers.Global.View',
        'toggle'     => [
            'on'         => Timers::enabled($app),
            'action'     => '/chat/timers/toggle',
            'value'      => 'toggle',
            'permission' => 'Timers.Global.Toggle',
            'title'      => translate('timers.toggle_hint'),
        ],
    ];

    return $nav;
});

// -------------------------------------------------------------------
//  Eigenes CSS fuer die Fortschrittsbalken
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/timers/assets/timers.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Was der Kern nicht abonniert
// -------------------------------------------------------------------
$hooks->on('core.eventsub.subscriptions', static function (array $subs, string $broadcasterId) use ($app): array {
    // Nur wenn die Timer eingeschaltet sind - ein Abo fuer etwas, das
    // bewusst aus ist, waere Verkehr ohne Zweck.
    if (!Timers::enabled($app)) {
        return $subs;
    }

    // Titel und Kategorie aendern sich mitten im Stream. Ohne dieses
    // Abo greift ein Timer, der auf "Farming" wartet, nach einem
    // Wechsel weiter - oder nie. Braucht keinen Scope.
    $subs[] = [
        'type'      => 'channel.update',
        'version'   => '2',
        'condition' => ['broadcaster_user_id' => $broadcasterId],
    ];

    return $subs;
});

// -------------------------------------------------------------------
//  Stream-Zustand mitfuehren
// -------------------------------------------------------------------
$hooks->on('core.event.stored', static function (array $event) use ($app): void {
    $typ = (string) ($event['event_type'] ?? '');

    if (!str_starts_with($typ, 'twitch.stream.') && $typ !== 'twitch.channel.update') {
        return;
    }

    $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

    (new Stream($app))->onEvent($typ, $payload);
});

// -------------------------------------------------------------------
//  Zaehlen und posten
// -------------------------------------------------------------------
$hooks->on('core.chat.message', static function () use ($app): void {
    (new Runner($app))->countLine();
});

$hooks->on('cron.tick', static function () use ($app): void {
    (new Runner($app))->tick();
});

// -------------------------------------------------------------------
//  Als Chatbefehl
// -------------------------------------------------------------------
// Beides greift nur, wenn das Plugin Chatbefehle installiert ist. Ohne
// es laufen die Timer trotzdem - nur eben ohne "!titel".
$hooks->on('chat_commands.names', static function (array $namen) use ($app): array {
    return array_merge($namen, array_keys(Timers::commands($app)));
});

$hooks->on('chat_commands.answer', static function (string $antwort, string $name) use ($app): string {
    // Nur einspringen, wenn noch niemand geantwortet hat: ein
    // Grundbefehl oder ein eigener Befehl hat Vorrang.
    return $antwort !== '' ? $antwort : (new Runner($app))->answerFor($name);
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$seite = static function (Request $request) use ($app, $plugin): Response {
    $stream = (new Stream($app))->state();
    $stand = State::load($app);
    $jetzt = time();

    $timer = [];
    foreach (Timers::all($app) as $eintrag) {
        $einzeln = State::of($stand, (string) $eintrag['id']);

        $timer[] = [
            'timer'    => $eintrag,
            'progress' => Timers::progress($eintrag, $einzeln, $stream, $jetzt),
        ];
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'   => translate('timers.name'),
        'active'  => 'chat/timers',
        'enabled' => Timers::enabled($app),
        'stream'  => $stream,
        'timers'  => $timer,
        'csrf'    => $app->auth->csrfToken(),
        'notice'  => (string) $request->get('notice'),
        'error'   => (string) $request->get('error'),
        'limits'  => [
            'interval_min' => Timers::INTERVAL_MIN,
            'interval_max' => Timers::INTERVAL_MAX,
            'message'      => Timers::MAX_MESSAGE,
        ],
    ]));
};

$router->get('/chat/timers', $seite, [
    'auth'       => true,
    'permission' => 'Timers.Global.View',
]);

// -------------------------------------------------------------------
//  Speichern
// -------------------------------------------------------------------
$zurueck = static function (?string $notice = null, ?string $error = null) use ($app): Response {
    $query = [];
    if ($notice !== null) {
        $query['notice'] = $notice;
    }
    if ($error !== null) {
        $query['error'] = $error;
    }

    return Response::redirect(
        $app->url('/chat/timers') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/chat/timers/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    if (!permission('Timers.Global.Toggle')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    $an = !Timers::enabled($app);
    Timers::setEnabled($app, $an);

    return $zurueck($an ? translate('timers.turned_on') : translate('timers.turned_off'));
}, ['auth' => true, 'permission' => 'Timers.Global.View']);

$router->post('/chat/timers', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if (!permission('Timers.Global.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    $aktion = (string) $request->input('action');

    if ($aktion === 'delete') {
        $id = (string) $request->input('id');
        $timer = Timers::find($app, $id);

        return $timer !== null && Timers::delete($app, $id)
            ? $zurueck(translate('timers.deleted', ['title' => (string) $timer['title']]))
            : $zurueck(null, translate('timers.error.unknown'));
    }

    if ($aktion !== 'save' && $aktion !== 'create') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    // Die Nachrichten kommen als Feld an: eine Eingabe je Nachricht,
    // wie im alten System. Nur so gibt es ein "Loeschen" fuer die
    // einzelne Zeile.
    $zeilen = $request->post['messages'] ?? [];
    $nachrichten = is_array($zeilen) ? array_map('strval', array_values($zeilen)) : [];

    // Hinzufuegen und Loeschen laufen ueber dasselbe Formular: die
    // uebrigen Eingaben gehen dabei nicht verloren, und es braucht kein
    // JavaScript. Das alte System hat das mit JavaScript geloest - hier
    // funktioniert es auch ohne.
    $stelle = $request->input('remove_message');
    if ($stelle !== '' && is_numeric($stelle)) {
        $nachrichten = Timers::withoutMessage($nachrichten, (int) $stelle);
    } elseif ($request->input('add_message') !== '') {
        $nachrichten = Timers::withEmptyMessage($nachrichten);
    }

    $ergebnis = Timers::save($app, [
        'id'               => $aktion === 'save' ? (string) $request->input('id') : '',
        'title'            => (string) $request->input('title'),
        'interval_minutes' => (int) $request->input('interval_minutes'),
        'min_lines'        => (int) $request->input('min_lines'),
        'title_keywords'   => (string) $request->input('title_keywords'),
        'game'             => (string) $request->input('game'),
        'messages'         => $nachrichten,
        'enabled'          => $request->input('enabled') !== '',
        'allow_as_command' => $request->input('allow_as_command') !== '',
    ]);

    if (!$ergebnis['ok']) {
        return $zurueck(null, $ergebnis['error']);
    }

    return $zurueck($aktion === 'create'
        ? translate('timers.created', ['title' => (string) $request->input('title')])
        : translate('timers.saved', ['title' => (string) $request->input('title')]));
}, ['auth' => true, 'permission' => 'Timers.Global.View']);
