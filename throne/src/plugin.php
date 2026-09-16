<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Throne
 * ===================================================================
 *
 * Throne ist eine Wunschliste: Zuschauer kaufen dem Streamer etwas
 * davon, geben Geld dazu oder sammeln gemeinsam auf einen Wunsch.
 * Throne meldet das als Webhook hierher.
 *
 * Aus dem alten System uebernommen: die Pruefung der Unterschrift, die
 * drei Ereignisarten, die Alert-Texte, die Farben im Feed.
 *
 * Der Webhook ist OEFFENTLICH - Throne kann sich hier nicht anmelden.
 * Was ihn trotzdem schuetzt, ist die Unterschrift: ohne den passenden
 * oeffentlichen Schluessel kommt nichts durch, und ohne Zeitstempel im
 * Fenster auch nicht.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Alerts\Alerts;
use TwitchController\Plugin\Throne\Config;
use TwitchController\Plugin\Throne\Throne;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Throne'] = [
        'label'       => translate('throne.name'),
        'permissions' => [
            'Throne.Global.View' => translate('throne.perm.view'),
            'Throne.Global.Edit' => translate('throne.perm.edit'),
        ],
    ];

    return $catalog;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[Throne::SLUG] = [
        'label' => translate('throne.settings'),
        'href'  => '/networking/throne',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Webhook
// -------------------------------------------------------------------
//
//  Ohne Anmeldung, aber nicht ohne Pruefung: Throne unterschreibt
//  jede Nachricht mit Ed25519, und unterschrieben wird
//  "<zeitstempel>.<koerper>". Der Zeitstempel gehoert mit hinein -
//  sonst liesse sich eine mitgeschnittene Nachricht spaeter erneut
//  abschicken.
//
//  Die Antworten sind knapp und sagen NICHT, was genau fehlte: der
//  Webhook steht offen im Netz, und wer ihn abklopft, soll daraus
//  nichts lernen. Der Grund steht im Log.
$router->post(Throne::WEBHOOK_PATH, static function (Request $request) use ($app): Response {
    $grund = Throne::verify(
        $app,
        $request->header('x-signature-timestamp'),
        $request->header('x-signature-ed25519'),
        $request->rawBody
    );

    if ($grund !== '') {
        $app->log(Throne::SLUG . ': Webhook abgewiesen - ' . $grund);

        return Response::text('Bad signature', 403);
    }

    $koerper = json_decode($request->rawBody, true);

    if (!is_array($koerper)) {
        $app->log(Throne::SLUG . ': Webhook ohne lesbaren Koerper.');

        return Response::text('Bad request', 400);
    }

    $id = trim((string) ($koerper['event_id'] ?? ''));
    $art = trim((string) ($koerper['event_type'] ?? ''));
    $daten = is_array($koerper['data'] ?? null) ? $koerper['data'] : [];

    if ($id === '' || $art === '') {
        $app->log(Throne::SLUG . ': Webhook ohne event_id oder event_type.');

        return Response::text('Bad request', 400);
    }

    // Der Zeitstempel der Unterschrift ist zugleich der Zeitpunkt des
    // Ereignisses - etwas Genaueres liefert Throne nicht mit.
    $wann = (int) $request->header('x-signature-timestamp');

    $gespeichert = $app->events->store(
        'throne',
        'throne.' . $art,
        $id,
        gmdate('Y-m-d H:i:sP', $wann),
        Throne::normalize($art, $daten),
        $daten,
        $request->rawBody
    );

    // Doppelt zugestellt ist kein Fehler: Throne wiederholt, wenn eine
    // Antwort ausbleibt, und die Datenbank laesst dieselbe event_id
    // kein zweites Mal zu. 200 heisst hier "angekommen", nicht
    // "gespeichert".
    if ($gespeichert === null) {
        $app->log(Throne::SLUG . ': Ereignis ' . $id . ' war schon da.');
    }

    return Response::text('OK', 200);
});

// -------------------------------------------------------------------
//  Der Alert
// -------------------------------------------------------------------
$hooks->on('core.event.stored', static function (array $event) use ($app): void {
    if (!$app->plugins->isEnabled('alerts')) {
        return;
    }

    $fall = Throne::caseOf((string) ($event['event_type'] ?? ''));

    if ($fall === '') {
        return;
    }

    $config = Config::of($app);

    if (!$config['enabled']) {
        return;
    }

    $eines = $config['cases'][$fall] ?? null;

    if ($eines === null) {
        return;
    }

    Alerts::send($app, [
        'kind'     => 'throne',
        'text'     => $eines['text'],
        'video'    => $eines['video'],
        'audio'    => $eines['audio'],
        'duration' => $eines['duration'],
        'values'   => Config::values($event),
    ]);
});

// -------------------------------------------------------------------
//  Im Feed
// -------------------------------------------------------------------
$hooks->on('core.events.labels', static function (array $labels): array {
    return $labels + [
        'throne.gift_purchased'         => translate('throne.event.gift'),
        'throne.contribution_purchased' => translate('throne.event.contribution'),
        'throne.gift_crowdfunded'       => translate('throne.event.crowdfund'),
    ];
});

$hooks->on('core.obs.filters', static function (array $nodes): array {
    $nodes[] = ['key' => 'throne', 'label' => translate('throne.short'), 'order' => 70];

    foreach ([
        'throne.gift'         => translate('throne.filter.gift'),
        'throne.contribution' => translate('throne.filter.contribution'),
        'throne.crowdfund'    => translate('throne.filter.crowdfund'),
    ] as $schluessel => $beschriftung) {
        $nodes[] = ['key' => $schluessel, 'label' => $beschriftung, 'parent' => 'throne'];
    }

    return $nodes;
});

$hooks->on('core.obs.badges', static function (array $badges): array {
    // Die Farben des alten Systems, deckend gerechnet: dort waren es
    // #ff9f4333 und Geschwister, also 20 % ueber dem Feed-Hintergrund
    // #0e1014. Der Farbwaehler in der Verwaltung ist ein
    // <input type="color"> und kennt keine Transparenz - er haette die
    // achtstellige Angabe verworfen und Schwarz hinterlassen.
    return $badges + [
        'throne_gift'         => ['label' => translate('throne.badge.gift'),         'bg' => '#3e2d1d', 'text' => '#ffe7d1'],
        'throne_contribution' => ['label' => translate('throne.badge.contribution'), 'bg' => '#3e371d', 'text' => '#fff3cf'],
        'throne_crowdfund'    => ['label' => translate('throne.badge.crowdfund'),    'bg' => '#193843', 'text' => '#d6f6ff'],
    ];
});

$hooks->on('core.obs.present', static function (?array $view, array $row, array $payload): ?array {
    $fall = Throne::caseOf((string) ($row['event_type'] ?? ''));

    if ($fall === '') {
        return $view;
    }

    $wer = trim((string) ($row['actor_name'] ?? ''));
    $nachricht = trim((string) ($row['message'] ?? ''));

    // Beim Sammelziel gibt es keinen Kaeufer - das ist der Abschluss
    // von vielen. Dann steht dort, WAS voll geworden ist; ohne das
    // stuende die Zeile ohne jede Auskunft da.
    if ($fall === 'crowdfund') {
        return [
            'badge'  => translate('throne.badge.crowdfund'),
            'style'  => 'throne_crowdfund',
            'title'  => $nachricht !== '' ? $nachricht : translate('throne.crowdfund_reached'),
            'filter' => 'throne.crowdfund',
        ];
    }

    return [
        'badge'  => $fall === 'gift'
            ? translate('throne.badge.gift')
            : translate('throne.badge.contribution'),
        'style'  => $fall === 'gift' ? 'throne_gift' : 'throne_contribution',
        'title'  => $wer !== '' ? $wer : translate('throne.someone'),
        'filter' => $fall === 'gift' ? 'throne.gift' : 'throne.contribution',
    ];
});

// -------------------------------------------------------------------
//  Der Reiter auf der Alerts-Seite
// -------------------------------------------------------------------
$hooks->on('alerts.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('Throne.Global.View')) {
        return $tabs;
    }

    $tabs['throne'] = [
        // Der KURZE Name: "Throne - Alerts" saegte auf der
        // Alerts-Seite zweimal dasselbe.
        'label' => translate('throne.short'),
        'order' => 70,
        'render' => static fn (): string => $app->view
            ->from($plugin->directory . '/views')
            ->render('alert_tab', [
                'config'          => Config::of($app),
                'cases'           => [
                    'gift'         => translate('throne.case.gift'),
                    'contribution' => translate('throne.case.contribution'),
                    'crowdfund'    => translate('throne.case.crowdfund'),
                ],
                'placeholders'    => Config::PLACEHOLDERS,
                'preview'         => [
                    'username' => 'marie_123',
                    'amount'   => '25,00 EUR',
                    'item'     => 'AirPods Max',
                    'message'  => translate('throne.preview.message'),
                ],
                'target'          => $app->url('/display/alerts/throne'),
                'ready'           => Throne::hasKey($app),
                'canEdit'         => permission('Throne.Global.Edit'),
                'defaultDuration' => Alerts::DEFAULT_DURATION,
                'maxText'         => Config::MAX_TEXT,
                'csrf'            => $app->auth->csrfToken(),
            ], null),
    ];

    return $tabs;
});

$router->post('/display/alerts/throne', static function (Request $request) use ($app): Response {
    $zurueck = static function (?string $notice, ?string $error = null) use ($app): Response {
        $query = array_filter([
            'notice' => $notice,
            'error'  => $error,
        ], static fn (?string $wert): bool => $wert !== null);

        return Response::redirect(
            $app->url('/display/alerts/throne')
            . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if (!permission('Throne.Global.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    switch ($request->input('action')) {
        case 'toggle':
            $an = !Config::of($app)['enabled'];
            Config::setEnabled($app, $an);

            return $zurueck($an
                ? translate('throne.alert.turned_on')
                : translate('throne.alert.turned_off'));

        case 'test':
            if (!Alerts::enabled($app)) {
                return $zurueck(null, translate('throne.alert.test_while_all_off'));
            }

            if (!Config::of($app)['enabled']) {
                return $zurueck(null, translate('throne.alert.test_while_off'));
            }

            $fall = (string) $request->input('case');

            if (!in_array($fall, Throne::CASES, true)) {
                return $zurueck(null, translate('common.error.unknown_action'));
            }

            $werte = $request->post['preview'] ?? [];
            $werte = is_array($werte) ? array_map('strval', $werte) : [];

            $eines = Config::of($app)['cases'][$fall];

            $ok = Alerts::send($app, [
                'kind'     => 'throne',
                'text'     => $eines['text'],
                'video'    => $eines['video'],
                'audio'    => $eines['audio'],
                'duration' => $eines['duration'],
                'values'   => $werte,
            ]);

            return $ok
                ? $zurueck(translate('throne.alert.test_sent'))
                : $zurueck(null, translate('throne.alert.test_failed'));

        case 'save':
            $eingaben = $request->post['cases'] ?? [];
            Config::save($app, is_array($eingaben) ? $eingaben : []);

            return $zurueck(translate('throne.alert.saved'));
    }

    return $zurueck(null, translate('common.error.unknown_action'));
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Einrichtung
// -------------------------------------------------------------------
$router->get('/networking/throne', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'      => translate('throne.name'),
        'active'     => 'networking/throne',
        'hasKey'     => Throne::hasKey($app),
        'webhookUrl' => $app->url(Throne::WEBHOOK_PATH),
        'canEdit'    => permission('Throne.Global.Edit'),
        'csrf'       => $app->auth->csrfToken(),
        'notice'     => (string) $request->get('notice'),
        'error'      => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'Throne.Global.View']);

$router->post('/networking/throne', static function (Request $request) use ($app): Response {
    $zurueck = static function (?string $notice, ?string $error = null) use ($app): Response {
        $query = array_filter([
            'notice' => $notice,
            'error'  => $error,
        ], static fn (?string $wert): bool => $wert !== null);

        return Response::redirect(
            $app->url('/networking/throne')
            . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if (!permission('Throne.Global.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    if ($request->input('action') === 'forget') {
        Throne::setPublicKey($app, '');

        return $zurueck(translate('throne.key_forgotten'));
    }

    $schluessel = trim($request->input('public_key'));

    // Leer heisst "nicht aendern" und nicht "loeschen" - zum Loeschen
    // gibt es den eigenen Knopf. Sonst wuerfe ein Speichern aus einem
    // anderen Grund den Schluessel nebenbei weg.
    if ($schluessel === '') {
        return $zurueck(translate('throne.saved'));
    }

    // Geprueft wird HIER und nicht erst beim ersten Webhook: ein
    // vertippter Schluessel faellt sonst dadurch auf, dass nichts
    // ankommt - und das sieht aus, als schicke Throne nichts.
    if (!Throne::looksLikeKey($schluessel)) {
        return $zurueck(null, translate('throne.bad_key'));
    }

    Throne::setPublicKey($app, $schluessel);

    return $zurueck(translate('throne.saved'));
}, ['auth' => true]);
