<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Raids - Raiden
 * ===================================================================
 *
 * Ein Knopf auf jeder Live-Kachel: raiden. Und einer darueber, der den
 * Raid waehrend seines Vorlaufs wieder abbricht.
 *
 * Kein eigener Reiter und keine eigene Liste - das Plugin haengt sich
 * mit raids.tile_actions in den Live-Reiter von Raids. Die Kacheln
 * stehen dort schon, und wer raiden will, sucht nicht erst eine zweite
 * Seite mit denselben Namen.
 *
 * Der Knopf schickt den LOGIN, nicht die Twitch-ID: er kommt aus der
 * Kachel, und die kennt den Login. Die ID holt Channels::userId() -
 * aus der Tabelle, und fuer einen Kanal, der nur wegen einer
 * Raid-Anfrage dabei ist, frisch bei Twitch.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Raids\Channels;
use TwitchController\Plugin\RaidsRaid\Raid;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
// Nur eines: den Raid ausloesen. Wer raiden darf, darf auch abbrechen -
// ein zweites Recht dafuer waere ein Schloss an der Tuer, durch die man
// schon gegangen ist.
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['RaidsRaid'] = [
        'label'       => translate('raids_raid.name'),
        'permissions' => [
            'RaidsRaid.Global.Start' => translate('raids_raid.perm.start'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Die Twitch-Freigabe
// -------------------------------------------------------------------
$hooks->on('core.twitch.broadcaster_scopes', static function (array $scopes): array {
    $scopes[] = Raid::SCOPE;

    return $scopes;
});

$hooks->on('core.twitch.scope_labels', static function (array $labels): array {
    $labels[Raid::SCOPE] = [
        'label'  => translate('raids_raid.scope'),
        'reason' => translate('raids_raid.scope.why'),
    ];

    return $labels;
});

// -------------------------------------------------------------------
//  Dateien
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/raids-raid/assets/raids-raid.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Der Knopf in der Kachel
// -------------------------------------------------------------------
$hooks->on('raids.tile_actions', static function (array $knoepfe) use ($app, $plugin): array {
    if (!permission('RaidsRaid.Global.Start')) {
        return $knoepfe;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');
    $bereit = Raid::ready($app);
    $csrf = $app->auth->csrfToken();

    $knoepfe[] = [
        'order'  => 10,
        'render' => static function (array $kachel) use ($vorlagen, $bereit, $csrf): string {
            return $vorlagen->render('button', [
                'login' => (string) ($kachel['login'] ?? ''),
                'name'  => (string) ($kachel['display_name'] ?? ''),
                'ready' => $bereit,
                'csrf'  => $csrf,
            ], null);
        },
    ];

    return $knoepfe;
});

// -------------------------------------------------------------------
//  Der Abbruch ueber dem Gitter
// -------------------------------------------------------------------
// Nur, solange ueberhaupt einer laufen kann. Ein Knopf, der immer da
// ist und meistens nichts abzubrechen hat, ist ein Knopf, dem man nicht
// glaubt.
$hooks->on('raids.live_actions', static function (array $knoepfe) use ($app, $plugin): array {
    if (!permission('RaidsRaid.Global.Start') || !Raid::pending($app)) {
        return $knoepfe;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');
    $csrf = $app->auth->csrfToken();

    $knoepfe[] = [
        // Nach dem Roulette: der Abbruch gehoert an das Ende der Reihe,
        // damit der Knopf zum Spielen nicht neben dem zum Abbrechen
        // steht.
        'order'  => 90,
        'render' => static fn (): string => $vorlagen->render('cancel', [
            'csrf' => $csrf,
        ], null),
    ];

    return $knoepfe;
});

// -------------------------------------------------------------------
//  Die Routen
// -------------------------------------------------------------------
$zurueck = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/networking/raids/live') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/networking/raids/raid', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('RaidsRaid.Global.Start')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $login = Channels::normalizeLogin($request->input('login'));
    if ($login === '') {
        return $zurueck(['error' => translate('raids_raid.error.unknown_channel')]);
    }

    $ergebnis = Raid::start($app, (new Channels($app))->userId($login));

    if (!$ergebnis['ok']) {
        $app->log('RaidsRaid: Raid auf ' . $login . ' gescheitert: ' . $ergebnis['error']);

        return $zurueck(['error' => $ergebnis['error']]);
    }

    $app->log('RaidsRaid: Raid auf ' . $login . ' gestartet.');

    return $zurueck(['notice' => translate('raids_raid.started', ['name' => $login])]);
}, ['auth' => true]);

$router->post('/networking/raids/raid/cancel', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('RaidsRaid.Global.Start')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $ergebnis = Raid::cancel($app);

    if (!$ergebnis['ok']) {
        return $zurueck(['error' => $ergebnis['error']]);
    }

    $app->log('RaidsRaid: Raid abgebrochen.');

    return $zurueck(['notice' => translate('raids_raid.cancelled')]);
}, ['auth' => true]);
