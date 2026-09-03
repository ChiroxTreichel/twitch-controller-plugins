<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Chatbefehle
 * ===================================================================
 *
 * Zwei Reiter, wie im alten System:
 *
 *   Grundbefehle   !befehle und !discord, fest eingebaut, mit eigenen
 *                  Feldern
 *   Eigene Befehle Name und Antworttext, beliebig viele, mit {USER}
 *
 * Gelesen und geantwortet wird ueber die Kernfaehigkeit Chat: das
 * Plugin haengt sich an core.chat.message und schickt seine Antwort
 * mit $app->chat->send(). Keine IRC-Verbindung, keine Zugangsdaten.
 *
 * !discord ist der einzige Befehl, der vorher etwas nachfragt - siehe
 * src/Discord.php, dort stehen die vier Feinheiten.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\ChatCommands\Commands;
use TwitchController\Plugin\ChatCommands\Dispatcher;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte - eines je Reiter
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['ChatCommands'] = [
        'label'       => translate('chat_commands.name'),
        'permissions' => [
            'ChatCommands.Basic.View'   => translate('chat_commands.perm.basic_view'),
            'ChatCommands.Basic.Edit'   => translate('chat_commands.perm.basic_edit'),
            'ChatCommands.Custom.View'  => translate('chat_commands.perm.custom_view'),
            'ChatCommands.Custom.Edit'  => translate('chat_commands.perm.custom_edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav): array {
    $nav['chat'] = [
        'label' => translate('chat_commands.nav.chat'),
        'order' => 20,
        'items' => [
            [
                'label'      => translate('chat_commands.name'),
                'href'       => '/chat/commands',
                'permission' => 'ChatCommands.Basic.View',
            ],
        ],
    ];

    return $nav;
});

// -------------------------------------------------------------------
//  Auf Chatnachrichten reagieren
// -------------------------------------------------------------------
$hooks->on('core.chat.message', static function (array $message) use ($app): void {
    (new Dispatcher($app))->handle($message);
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------

/** Die Reiter samt dem Recht, das sie sichtbar macht. */
$reiter = [
    'basic'  => ['label' => translate('chat_commands.tab.basic'),  'permission' => 'ChatCommands.Basic.View'],
    'custom' => ['label' => translate('chat_commands.tab.custom'), 'permission' => 'ChatCommands.Custom.View'],
];

$erlaubteReiter = static function () use ($reiter): array {
    $offen = [];
    foreach ($reiter as $key => $tab) {
        if (permission($tab['permission'])) {
            $offen[$key] = $tab;
        }
    }

    return $offen;
};

$seite = static function (Request $request, array $params = []) use ($app, $plugin, $erlaubteReiter): Response {
    $offene = $erlaubteReiter();
    if ($offene === []) {
        return Response::redirect($app->url('/'));
    }

    $gewuenscht = strtolower(trim((string) ($params['tab'] ?? '')));
    $offen = isset($offene[$gewuenscht]) ? $gewuenscht : (string) array_key_first($offene);

    $vorlagen = $app->view->from($plugin->directory . '/views');
    $csrf = $app->auth->csrfToken();

    // Nur der offene Reiter wird gebaut - der andere kostet sonst
    // Abfragen fuer etwas, das niemand sieht.
    if ($offen === 'basic') {
        $einstellungen = [];
        $eingebaut = Commands::builtin();
        foreach (array_keys($eingebaut) as $name) {
            $einstellungen[$name] = Commands::settingsOf($app, (string) $name);
        }

        $inhalt = $vorlagen->render('basic', [
            'builtin'  => $eingebaut,
            'settings' => $einstellungen,
            'csrf'     => $csrf,
        ], null);
    } else {
        $inhalt = $vorlagen->render('custom', [
            'commands' => Commands::custom($app),
            'csrf'     => $csrf,
            'maxLength' => Commands::MAX_RESPONSE,
        ], null);
    }

    return Response::html($vorlagen->render('page', [
        'title'   => translate('chat_commands.name'),
        // Ohne Schraegstrich am Anfang - so heissen die Menueschluessel,
        // und ohne das bliebe der Menuepunkt unmarkiert.
        'active'  => 'chat/commands',
        'tabs'    => $offene,
        'open'    => $offen,
        'content' => $inhalt,
        'notice'  => (string) $request->get('notice'),
        'error'   => (string) $request->get('error'),
    ]));
};

// Ohne Reiter in der Adresse zuerst - sonst faengt {tab} den Aufruf.
$router->get('/chat/commands', $seite, [
    'auth'       => true,
    'permission' => 'ChatCommands.Basic.View',
]);
$router->get('/chat/commands/{tab}', $seite, [
    'auth'       => true,
    'permission' => 'ChatCommands.Basic.View',
]);

// -------------------------------------------------------------------
//  Speichern
// -------------------------------------------------------------------

/**
 * Zurueck zum Reiter, mit Meldung.
 */
$zurueck = static function (string $tab, ?string $notice = null, ?string $error = null) use ($app): Response {
    $query = [];
    if ($notice !== null) {
        $query['notice'] = $notice;
    }
    if ($error !== null) {
        $query['error'] = $error;
    }

    return Response::redirect(
        $app->url('/chat/commands/' . rawurlencode($tab))
        . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/chat/commands/basic', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck('basic', null, translate('common.error.form_expired'));
    }

    if (!permission('ChatCommands.Basic.Edit')) {
        return $zurueck('basic', null, translate('common.error.no_permission'));
    }

    $name = strtolower(trim((string) $request->input('command')));
    if (!Commands::isBuiltin($name)) {
        return $zurueck('basic', null, translate('chat_commands.error.unknown_command'));
    }

    $werte = $request->post['fields'] ?? [];
    Commands::saveSettings($app, $name, is_array($werte) ? $werte : []);

    return $zurueck('basic', translate('chat_commands.saved', ['name' => $name]));
}, ['auth' => true, 'permission' => 'ChatCommands.Basic.View']);

$router->post('/chat/commands/custom', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck('custom', null, translate('common.error.form_expired'));
    }

    if (!permission('ChatCommands.Custom.Edit')) {
        return $zurueck('custom', null, translate('common.error.no_permission'));
    }

    $aktion = (string) $request->input('action');

    if ($aktion === 'delete') {
        $name = (string) $request->input('previous');

        return Commands::deleteCustom($app, $name)
            ? $zurueck('custom', translate('chat_commands.deleted', ['name' => Commands::normalizeName($name)]))
            : $zurueck('custom', null, translate('chat_commands.error.unknown_command'));
    }

    if ($aktion !== 'save' && $aktion !== 'create') {
        return $zurueck('custom', null, translate('common.error.unknown_action'));
    }

    $name = (string) $request->input('command');
    $antwort = (string) $request->input('response');
    $vorher = $aktion === 'save' ? (string) $request->input('previous') : '';

    $fehler = Commands::saveCustom($app, $name, $antwort, $vorher);
    if ($fehler !== '') {
        return $zurueck('custom', null, $fehler);
    }

    return $zurueck('custom', $aktion === 'create'
        ? translate('chat_commands.created', ['name' => Commands::normalizeName($name)])
        : translate('chat_commands.saved', ['name' => Commands::normalizeName($name)]));
}, ['auth' => true, 'permission' => 'ChatCommands.Custom.View']);
