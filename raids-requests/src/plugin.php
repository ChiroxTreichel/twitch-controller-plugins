<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Raids - Anfragen
 * ===================================================================
 *
 * Zwei Seiten fuer dieselbe Sache:
 *
 *   /raidme               oeffentlich. Ein Streamer meldet sich mit
 *                         Twitch an und sagt "raide mich".
 *   Raids > Anfragen      der Kanalinhaber nimmt an oder lehnt ab.
 *
 * Wer angenommen ist, taucht im Live-Reiter von Raids auf, sobald er
 * streamt - dafuer der Hook raids.live_logins. Ein Favorit wird er
 * nicht: eine Anfrage gilt fuer heute Abend, ein Favorit fuer immer.
 *
 * Die oeffentliche Seite legt KEIN Konto an. Wer sich dort anmeldet,
 * bekommt ein signiertes Cookie mit seinem Twitch-Namen und nichts
 * weiter; das Token aus der Anmeldung wird weggeworfen. Siehe
 * Requests.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\RaidsRequests\Requests;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['RaidsRequests'] = [
        'label'       => translate('raids_req.name'),
        'permissions' => [
            'RaidsRequests.Global.View'   => translate('raids_req.perm.view'),
            'RaidsRequests.Global.Decide' => translate('raids_req.perm.decide'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Dateien
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/raids-requests/assets/raids-requests.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Wer angenommen ist, gehoert in den Live-Reiter
// -------------------------------------------------------------------
$hooks->on('raids.live_logins', static function (array $logins) use ($app): array {
    return array_merge($logins, Requests::acceptedLogins($app));
});

// -------------------------------------------------------------------
//  Aufraeumen
// -------------------------------------------------------------------
// Gedrosselt in Requests::cleanup(): hoechstens einmal am Tag. Der
// Worker tickt alle 15 Sekunden, und eine Aufraeumabfrage je Tick waere
// viertausend am Tag fuer eine Tabelle, in der sich meistens nichts
// geaendert hat.
$hooks->on('cron.tick', static function () use ($app): void {
    Requests::cleanup($app);
});

// -------------------------------------------------------------------
//  Der Reiter
// -------------------------------------------------------------------
$hooks->on('raids.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('RaidsRequests.Global.View')) {
        return $tabs;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    $tabs['requests'] = [
        'label' => translate('raids_req.tab'),
        // Hinter "Live" und "Ich folge": man sieht hier nach, wenn
        // jemand gefragt hat, und nicht bei jedem Aufruf.
        'order' => 20,
        'render' => static function () use ($app, $vorlagen): string {
            $anfragen = Requests::visible($app);

            return $vorlagen->render('tab_requests', [
                'requests'  => $anfragen,
                // Frisch von Twitch und nicht gespeichert - ein
                // gespeichertes Bild veraltet, sobald jemand es
                // wechselt.
                'images'    => Requests::profiles(
                    $app,
                    array_map(static fn (array $a): string => $a['login'], $anfragen)
                ),
                'open'      => Requests::open($app),
                'publicUrl' => $app->url('/raidme'),
                'canDecide' => permission('RaidsRequests.Global.Decide'),
                'csrf'      => $app->auth->csrfToken(),
            ], null);
        },
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Entscheiden und den Schalter umlegen
// -------------------------------------------------------------------
$zurueckIntern = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/networking/raids/requests') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/networking/raids/requests', static function (Request $request) use ($app, $zurueckIntern): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckIntern(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('RaidsRequests.Global.Decide')) {
        return $zurueckIntern(['error' => translate('common.error.no_permission')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'toggle') {
        $an = !Requests::open($app);
        Requests::setOpen($app, $an);

        return $zurueckIntern([
            'notice' => translate($an ? 'raids_req.opened' : 'raids_req.closed_now'),
        ]);
    }

    $status = $aktion === 'accept' ? 'accepted' : ($aktion === 'decline' ? 'declined' : '');
    if ($status === '') {
        return $zurueckIntern(['error' => translate('common.error.unknown_action')]);
    }

    if (!Requests::setStatus($app, $request->input('login'), $status)) {
        return $zurueckIntern(['error' => translate('raids_req.error.unknown')]);
    }

    return $zurueckIntern([
        'notice' => translate($status === 'accepted' ? 'raids_req.accepted' : 'raids_req.declined'),
    ]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die oeffentliche Seite
// -------------------------------------------------------------------
//
//  Ohne 'auth' - das ist der Sinn. Was hier passieren kann, ist genau
//  eines: sich selbst auf die Liste setzen. Kein Recht, keine
//  Einstellung, keine Auskunft ueber andere.

$oeffentlich = static function (Request $request, array $params = [], string $notice = '', string $error = '') use ($app, $plugin): Response {
    $wer = Requests::identity($app);
    $eigener = $wer !== null && $wer['login'] === Requests::ownLogin($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('raidme', [
        'channel'  => Requests::ownName($app),
        'identity' => $wer,
        'ownChannel' => $eigener,
        'request'  => $wer === null ? null : Requests::find($app, $wer['login']),
        'open'     => Requests::open($app),
        'notice'   => $notice !== '' ? $notice : (string) $request->get('notice'),
        'error'    => $error !== '' ? $error : (string) $request->get('error'),
        'csrf'     => Requests::csrfToken($app),
    ], null));
};

$router->get('/raidme', $oeffentlich);

// Die Anmeldung. Ohne Freigaben: gebraucht wird nur die Auskunft, wer
// da ist - und je weniger man verlangt, desto eher drueckt jemand auf
// "erlauben".
$router->get('/raidme/login', static function () use ($app): Response {
    return Response::redirect(
        $app->twitch->oauth()->authorizeUrl(Requests::PURPOSE, [], false)
    );
});

$router->get('/raidme/logout', static function () use ($app): Response {
    Requests::forget($app);

    return Response::redirect($app->url('/raidme'));
});

$router->post('/raidme', static function (Request $request) use ($app, $oeffentlich): Response {
    if (!Requests::checkCsrf($app, $request->input('csrf'))) {
        return $oeffentlich($request, [], '', translate('common.error.form_expired'));
    }

    $wer = Requests::identity($app);
    if ($wer === null) {
        return $oeffentlich($request, [], '', translate('raids_req.error.not_signed_in'));
    }

    $ergebnis = Requests::submit($app, $wer['login'], $wer['display_name'], $wer['user_id']);

    if (!$ergebnis['ok']) {
        return $oeffentlich($request, [], '', $ergebnis['error']);
    }

    $app->log('RaidsRequests: Anfrage von ' . $wer['login'] . ' eingetragen.');

    return $oeffentlich($request, [], translate('raids_req.submitted'));
});

// -------------------------------------------------------------------
//  Der Rueckweg von Twitch
// -------------------------------------------------------------------
// Der Kern faengt jeden Rueckweg ab und fragt hier nach, wer den Zweck
// kennt. Das Token wird NICHT gespeichert: gebraucht wird die Auskunft,
// wer da ist, und ein Token eines fremden Kanals waere ein Schluessel,
// fuer den es kein Schloss gibt.
$hooks->on('core.oauth.callback', static function (
    mixed $behandelt,
    string $zweck,
    array $token,
    array $twitchUser
) use ($app): mixed {
    if ($behandelt instanceof Response || $zweck !== Requests::PURPOSE) {
        return $behandelt;
    }

    $login = Requests::normalizeLogin((string) ($twitchUser['login'] ?? ''));
    if ($login === '') {
        return Response::redirect(
            $app->url('/raidme') . '?error=' . rawurlencode(translate('raids_req.error.no_identity'))
        );
    }

    Requests::remember(
        $app,
        $login,
        (string) ($twitchUser['display_name'] ?? $login),
        (string) ($twitchUser['id'] ?? '')
    );

    return Response::redirect($app->url('/raidme'));
});
