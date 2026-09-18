<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Kanalpunkte
 * ===================================================================
 *
 * Kanalpunkt-Belohnungen anlegen, aendern - und vor allem: sie je
 * nach Stream-Titel und Kategorie von selbst ein- und ausschalten.
 * Eine Belohnung "Boss-Kampf uebernehmen" gehoert nicht in einen
 * Just-Chatting-Stream, und daran denkt beim Umschalten niemand.
 *
 * Was dabei ueber Twitch zu wissen ist, steht in src/Rewards.php. Die
 * Kurzfassung: aendern duerfen wir nur, was wir selbst angelegt
 * haben, und ein Symbol laesst sich ueber die Schnittstelle gar nicht
 * setzen.
 *
 * Geschaltet wird im Hintergrundprozess (cron.tick) - siehe
 * src/Runner.php.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\ChannelPoints\Conditions;
use TwitchController\Plugin\ChannelPoints\Groups;
use TwitchController\Plugin\ChannelPoints\RewardApi;
use TwitchController\Plugin\ChannelPoints\Rewards;
use TwitchController\Plugin\ChannelPoints\Runner;
use TwitchController\Plugin\ChannelPoints\Stream;
use TwitchController\Plugin\ChannelPoints\Sync;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['ChannelPoints'] = [
        'label'       => translate('channel_points.name'),
        'permissions' => [
            'ChannelPoints.Global.View'   => translate('channel_points.perm.view'),
            'ChannelPoints.Global.Edit'   => translate('channel_points.perm.edit'),
            'ChannelPoints.Global.Toggle' => translate('channel_points.perm.toggle'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Freigabe bei Twitch
// -------------------------------------------------------------------
$hooks->on('core.twitch.broadcaster_scopes', static function (array $scopes): array {
    $scopes[] = Rewards::SCOPE;

    return $scopes;
});

$hooks->on('core.twitch.scope_labels', static function (array $labels): array {
    $labels[Rewards::SCOPE] = [
        'label'  => translate('channel_points.scope'),
        'reason' => translate('channel_points.scope.why'),
    ];

    return $labels;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    /*
     * In die Gruppe "Chat": eine Kanalpunkt-Belohnung wird im Chat
     * eingeloest und steht dort neben Timern, Chatbefehlen und dem
     * Loeschbot.
     *
     * Eintraege ANHAENGEN, nicht die Gruppe setzen: dort haengen
     * mehrere Plugins hinein, und wer sie ueberschreibt, laesst je
     * nach Ladereihenfolge die anderen Menuepunkte verschwinden.
     */
    $nav['chat']['label'] = translate('channel_points.nav.chat');
    $nav['chat']['order'] = 20;
    $nav['chat']['items'][] = [
        'label'      => translate('channel_points.name'),
        'href'       => '/chat/points',
        'permission' => 'ChannelPoints.Global.View',
        'toggle'     => [
            'on'         => Rewards::enabled($app),
            'action'     => '/chat/points/toggle',
            'value'      => 'toggle',
            'permission' => 'ChannelPoints.Global.Toggle',
            'title'      => translate('channel_points.toggle_hint'),
        ],
    ];

    return $nav;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/channel-points/assets/channel-points.css');

    // "+" und "x" bei den Aus-Bedingungen im Browser. Eine Zugabe:
    // ohne das Skript bleibt am Ende jeder Liste eine leere Zeile
    // stehen, und es geht auch so - nur langsamer.
    $assets['js'][] = $app->asset('/plugin/channel-points/assets/channel-points.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Was der Kern nicht abonniert
// -------------------------------------------------------------------
$hooks->on('core.eventsub.subscriptions', static function (array $subs, string $broadcasterId): array {
    // Titel und Kategorie aendern sich mitten im Stream. Ohne dieses
    // Abo merkt das Plugin einen Wechsel erst beim naechsten
    // Rueckfall ueber Helix - also bis zu fuenf Minuten spaeter.
    $subs[] = [
        'type'      => 'channel.update',
        'version'   => '2',
        'condition' => ['broadcaster_user_id' => $broadcasterId],
    ];

    return $subs;
});

$hooks->on('core.event.stored', static function (array $event) use ($app): void {
    $typ = (string) ($event['event_type'] ?? '');

    if (!str_starts_with($typ, 'twitch.stream.') && $typ !== 'twitch.channel.update') {
        return;
    }

    $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

    (new Stream($app))->onEvent($typ, $payload);
});

// -------------------------------------------------------------------
//  Schalten
// -------------------------------------------------------------------
$hooks->on('cron.tick', static function () use ($app): void {
    (new Runner($app))->tick();
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
/** Die beiden Reiter. Alles andere fuehrt auf den ersten. */
$reiter = static function (Request $request, array $params = []): string {
    $wunsch = (string) ($params['tab'] ?? $request->get('tab'));

    return $wunsch === 'groups' ? 'groups' : 'rewards';
};

$seite = static function (Request $request, array $params = []) use ($app, $plugin, $reiter): Response {
    $stream = (new Stream($app))->state();
    $api = new RewardApi($app);
    $gruppen = Groups::all($app);

    $zeilen = [];

    // Alphabetisch - beim Anzeigen. Gespeichert bleibt die
    // Reihenfolge, in der Twitch sie liefert.
    foreach (Rewards::sorted(Rewards::all($app)) as $belohnung) {
        $zeilen[] = [
            'reward' => $belohnung,
            'remote' => Rewards::isRemote((string) $belohnung['id']),
            'auto'   => Conditions::automatic($belohnung),
            'want'   => Conditions::decide($belohnung, $stream, $gruppen),
            'why'    => Conditions::reason($belohnung, $stream, $gruppen),
            'groups' => Groups::forReward($gruppen, (string) $belohnung['id']),
        ];
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'tab'      => $reiter($request, $params),
        'groups'   => $gruppen,
        'title'    => translate('channel_points.name'),
        'active'   => 'chat/points',
        'enabled'  => Rewards::enabled($app),
        'allowed'  => $api->allowed(),

        // Als Daten und nicht als permission() in der Vorlage: so
        // laesst sich die Seite in beiden Faellen rendern und pruefen.
        'canEdit'  => $app->auth->can('ChannelPoints.Global.Edit'),
        'canToggle' => $app->auth->can('ChannelPoints.Global.Toggle'),
        'stream'   => $stream,
        'rewards'  => $zeilen,
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => (string) $request->get('notice'),
        'error'    => (string) $request->get('error'),
        'limits'   => [
            'title'    => Rewards::MAX_TITLE,
            'prompt'   => Rewards::MAX_PROMPT,
            'cooldown' => Rewards::MAX_COOLDOWN,
        ],
    ]));
};

$router->get('/chat/points', $seite, [
    'auth'       => true,
    'permission' => 'ChannelPoints.Global.View',
]);

$router->get('/chat/points/{tab}', $seite, [
    'auth'       => true,
    'permission' => 'ChannelPoints.Global.View',
]);

// -------------------------------------------------------------------
//  Speichern
// -------------------------------------------------------------------
$zurueck = static function (?string $notice = null, ?string $error = null, string $tab = 'rewards') use ($app): Response {
    $query = [];
    if ($notice !== null) {
        $query['notice'] = $notice;
    }
    if ($error !== null) {
        $query['error'] = $error;
    }

    // Zurueck auf den Reiter, von dem die Eingabe kam - sonst sucht
    // man nach dem Speichern seine Gruppe wieder.
    $ziel = $tab === 'groups' ? '/chat/points/groups' : '/chat/points';

    return Response::redirect(
        $app->url($ziel) . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/chat/points/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    if (!permission('ChannelPoints.Global.Toggle')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    $an = !Rewards::enabled($app);
    Rewards::setEnabled($app, $an);

    return $zurueck($an
        ? translate('channel_points.turned_on')
        : translate('channel_points.turned_off'));
}, ['auth' => true, 'permission' => 'ChannelPoints.Global.View']);

/**
 * Die Eingaben eines Formulars als Belohnung.
 *
 * @return array<string, mixed>
 */
$ausFormular = static function (Request $request): array {
    return [
        'id'            => (string) $request->input('id'),
        'title'         => (string) $request->input('title'),
        'prompt'        => (string) $request->input('prompt'),
        'cost'          => (int) $request->input('cost'),
        'user_input'    => $request->input('user_input') !== '',
        'color'         => (string) $request->input('color'),
        'skip_queue'    => $request->input('skip_queue') !== '',
        'limits'        => $request->input('limits') !== '',
        'cooldown'      => (int) $request->input('cooldown'),
        'cooldown_unit' => (string) $request->input('cooldown_unit'),
        'per_stream'    => (int) $request->input('per_stream'),
        'per_user'      => (int) $request->input('per_user'),
        'is_enabled'    => $request->input('is_enabled') !== '',
        'title_on'      => (string) $request->input('title_on'),
        'game_on'       => (string) $request->input('game_on'),
        // Je ein Feld pro Zeile - leere fallen in Rewards::entries()
        // weg, und genau eine leere zeigt die Oberflaeche immer an.
        'title_off'     => is_array($request->post['title_off'] ?? null) ? $request->post['title_off'] : [],
        'game_off'      => is_array($request->post['game_off'] ?? null) ? $request->post['game_off'] : [],
    ];
};

$router->post('/chat/points', static function (Request $request) use ($app, $zurueck, $ausFormular): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if (!permission('ChannelPoints.Global.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    $aktion = (string) $request->input('action');
    $id = (string) $request->input('id');

    // ---------------------------------------------------------------
    //  Gruppen
    // ---------------------------------------------------------------
    if (str_starts_with($aktion, 'group_')) {
        if ($aktion === 'group_delete') {
            $gruppe = Groups::find($app, $id);

            if ($gruppe === null) {
                return $zurueck(null, translate('channel_points.error.unknown_group'), 'groups');
            }

            Groups::forget($app, $id);

            return $zurueck(translate('channel_points.group.deleted', [
                'name' => (string) $gruppe['name'],
            ]), null, 'groups');
        }

        if ($aktion === 'group_toggle') {
            $gruppe = Groups::find($app, $id);

            if ($gruppe === null) {
                return $zurueck(null, translate('channel_points.error.unknown_group'), 'groups');
            }

            /*
             * Aus heisst: diese Regel zaehlt nicht mit. NICHT, dass
             * die Belohnungen darin ausgehen - die richten sich dann
             * nach ihren eigenen Bedingungen.
             */
            $gruppe['enabled'] = empty($gruppe['enabled']);
            Groups::put($app, $gruppe);

            // Literale Schluessel: ein translate($bedingung ? 'a' : 'b')
            // findet bin/lang.php nicht, und dann faellt ein
            // fehlender Text erst dem Benutzer auf.
            $name = ['name' => (string) $gruppe['name']];

            return $zurueck($gruppe['enabled']
                ? translate('channel_points.group.turned_on', $name)
                : translate('channel_points.group.turned_off', $name), null, 'groups');
        }

        if ($aktion !== 'group_save' && $aktion !== 'group_create') {
            return $zurueck(null, translate('common.error.unknown_action'), 'groups');
        }

        $mitglieder = $request->post['members'] ?? [];

        $eingabe = [
            'id'        => $aktion === 'group_save' ? $id : '',
            'name'      => (string) $request->input('name'),
            'members'   => is_array($mitglieder) ? $mitglieder : [],
            'enabled'   => $aktion === 'group_create' ? true : $request->input('enabled') !== '',
            'title_on'  => (string) $request->input('title_on'),
            'game_on'   => (string) $request->input('game_on'),
            'title_off' => is_array($request->post['title_off'] ?? null) ? $request->post['title_off'] : [],
            'game_off'  => is_array($request->post['game_off'] ?? null) ? $request->post['game_off'] : [],
        ];

        if (trim($eingabe['name']) === '') {
            return $zurueck(null, translate('channel_points.error.group_name'), 'groups');
        }

        if (!Groups::put($app, $eingabe)) {
            return $zurueck(null, translate('channel_points.error.too_many_groups'), 'groups');
        }

        $name = ['name' => trim($eingabe['name'])];

        return $zurueck($aktion === 'group_create'
            ? translate('channel_points.group.created', $name)
            : translate('channel_points.group.saved', $name), null, 'groups');
    }

    // ---------------------------------------------------------------
    //  Laden
    // ---------------------------------------------------------------
    if ($aktion === 'import') {
        $sync = new Sync($app);
        $ergebnis = $sync->import();

        if ($ergebnis === null) {
            return $zurueck(null, $sync->error());
        }

        return $zurueck(translate('channel_points.imported', [
            'added'   => (string) $ergebnis['added'],
            'updated' => (string) $ergebnis['updated'],
            'removed' => (string) $ergebnis['removed'],
        ]));
    }

    // ---------------------------------------------------------------
    //  Neu anlegen und Nachholen
    // ---------------------------------------------------------------
    if ($aktion === 'recreate' || $aktion === 'push') {
        $belohnung = Rewards::find($app, $id);

        if ($belohnung === null) {
            return $zurueck(null, translate('channel_points.error.unknown'));
        }

        $sync = new Sync($app);
        $ok = $aktion === 'recreate' ? $sync->recreate($id) : $sync->push($id);

        if (!$ok) {
            return $zurueck(null, $sync->error());
        }

        $name = ['title' => (string) $belohnung['title']];

        return $zurueck($aktion === 'recreate'
            ? translate('channel_points.recreated', $name)
            : translate('channel_points.pushed', $name));
    }

    // ---------------------------------------------------------------
    //  Loeschen
    // ---------------------------------------------------------------
    if ($aktion === 'delete') {
        $belohnung = Rewards::find($app, $id);

        if ($belohnung === null) {
            return $zurueck(null, translate('channel_points.error.unknown'));
        }

        /*
         * Loeschen bei Twitch geht nur bei eigenen Belohnungen -
         * dieselbe Client-ID-Regel wie beim Aendern. Eine fremde
         * verschwindet hier aus der Liste und bleibt bei Twitch
         * stehen; alles andere vorzutaeuschen waere gelogen.
         */
        if (!empty($belohnung['manageable']) && Rewards::isRemote($id)) {
            $api = new RewardApi($app);

            if (!$api->delete($id)) {
                return $zurueck(null, $api->error());
            }
        }

        $fremd = empty($belohnung['manageable']) && Rewards::isRemote($id);

        Rewards::forget($app, $id);

        // Eine Kennung, die niemandem mehr gehoert, hat in keiner
        // Mitgliederliste mehr etwas zu suchen.
        Groups::dropMember($app, $id);

        $name = ['title' => (string) $belohnung['title']];

        return $zurueck($fremd
            ? translate('channel_points.removed_here', $name)
            : translate('channel_points.deleted', $name));
    }

    if ($aktion !== 'save' && $aktion !== 'create') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    // ---------------------------------------------------------------
    //  Anlegen und Aendern
    // ---------------------------------------------------------------
    $eingabe = $ausFormular($request);
    $api = new RewardApi($app);

    if ($aktion === 'create') {
        if (count(Rewards::all($app)) >= Rewards::MAX_REWARDS) {
            return $zurueck(null, translate('channel_points.error.too_many'));
        }

        $eingabe['id'] = '';
        $eingabe['manageable'] = true;

        $antwort = $api->create($eingabe);

        if ($antwort === null) {
            /*
             * Nicht verlieren, was jemand getippt hat: die Belohnung
             * bleibt lokal stehen und laesst sich mit "Bei Twitch
             * anlegen" nachholen.
             */
            $eingabe['id'] = Rewards::localId();
            $eingabe['manageable'] = false;
            Rewards::put($app, $eingabe);

            return $zurueck(null, $api->error());
        }

        $neu = Rewards::fromTwitch($antwort, $eingabe, true);

        if ($neu !== null) {
            Rewards::put($app, $neu);
        }

        return $zurueck(translate('channel_points.created', [
            'title' => (string) $eingabe['title'],
        ]));
    }

    $vorher = Rewards::find($app, $id);

    if ($vorher === null) {
        return $zurueck(null, translate('channel_points.error.unknown'));
    }

    /*
     * Eine fremde Belohnung laesst sich bei Twitch nicht aendern -
     * ihre Felder gehoeren dem Dashboard. Was hier trotzdem
     * uebernommen wird, sind die Bedingungen: die sind unsere, und
     * ohne sie waere die Zeile in der Liste nutzlos.
     */
    if (empty($vorher['manageable'])) {
        $vorher['title_on'] = $eingabe['title_on'];
        $vorher['game_on'] = $eingabe['game_on'];
        $vorher['title_off'] = $eingabe['title_off'];
        $vorher['game_off'] = $eingabe['game_off'];

        Rewards::put($app, $vorher);

        return $zurueck(translate('channel_points.saved_conditions', [
            'title' => (string) $vorher['title'],
        ]));
    }

    $eingabe['manageable'] = true;

    // Nur hier vorhanden: dann gibt es bei Twitch nichts zu aendern.
    if (!Rewards::isRemote($id)) {
        Rewards::put($app, $eingabe);

        return $zurueck(translate('channel_points.saved_local', [
            'title' => (string) $eingabe['title'],
        ]));
    }

    $antwort = $api->update($id, $eingabe);

    if ($antwort === null) {
        return $zurueck(null, $api->error());
    }

    $neu = Rewards::fromTwitch($antwort, $eingabe, true);

    if ($neu !== null) {
        Rewards::put($app, $neu);
    }

    return $zurueck(translate('channel_points.saved', [
        'title' => (string) $eingabe['title'],
    ]));
}, ['auth' => true, 'permission' => 'ChannelPoints.Global.View']);
