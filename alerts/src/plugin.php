<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Alerts - der Rahmen
 * ===================================================================
 *
 * Bringt den Bereich "Anzeigen > Alerts", die Flaeche im Overlay und
 * die Warteschlange davor. Was ein Alert ist, liefern andere Plugins -
 * siehe src/Alerts.php fuer den Vertrag.
 *
 * Zwei Seiten, und die Trennung ist Absicht:
 *
 *   /display/alerts            die Reiter der Alert-Plugins, plus dem
 *                              Hauptschalter. Das ist Betrieb.
 *   /display/alerts/settings   Groesse und Lage der Flaeche. Das sind
 *                              die Einstellungen dieses Plugins und
 *                              stehen deshalb in der Plugin-Liste -
 *                              nicht als Reiter zwischen den Alerts.
 *
 * Wie lange ein Alert steht, gehoert nicht hierher: das legt jeder
 * Alert selbst fest, je Fall oder je Stufe.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Alerts\Alerts;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

$scope = Alerts::scope();

/**
 * Zurueck zu einer Seite dieses Plugins, mit Meldung.
 */
$zurueckZu = static function (string $pfad) use ($app): callable {
    return static function (?string $notice = null, ?string $error = null) use ($app, $pfad): Response {
        $query = [];
        if ($notice !== null) {
            $query['notice'] = $notice;
        }
        if ($error !== null) {
            $query['error'] = $error;
        }

        return Response::redirect(
            $app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };
};

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['Alerts'] = [
        'label' => translate('alerts.name'),
        'permissions' => [
            'Alerts.Global.View'   => translate('alerts.permissions.view'),
            'Alerts.Global.Edit'   => translate('alerts.permissions.edit'),
            'Alerts.Global.Toggle' => translate('alerts.permissions.toggle'),
            'Alerts.Global.Test'   => translate('alerts.permissions.test'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
// Eigener Bereich "Anzeigen": dort sammelt sich alles, was im Stream
// zu sehen ist.
$hooks->on('admin.nav', static function (array $nav): array {
    $nav['display'] = [
        'label' => translate('alerts.nav.display'),
        'order' => 10,
        'items' => [
            [
                'label'      => translate('alerts.name'),
                'href'       => '/display/alerts',
                'permission' => 'Alerts.Global.View',
            ],
        ],
    ];

    return $nav;
});

// Die Groesse der Flaeche gehoert zu diesem Plugin, nicht zu den
// Alerts. Deshalb erreichbar aus der Plugin-Liste - und nicht als
// Reiter zwischen Follows und Bits.
$hooks->on('plugin.settings', static function (array $pages) use ($plugin): array {
    $pages[$plugin->slug] = [
        'label' => translate('alerts.nav.settings'),
        'href'  => '/display/alerts/settings',
    ];

    return $pages;
});

// -------------------------------------------------------------------
//  Der Platz im Overlay
// -------------------------------------------------------------------
$hooks->on('overlay.slots', static function (array $slots) use ($app): array {
    $medien = Alerts::mediaSize($app);

    $slots[Alerts::SLOT] = [
        'label'    => translate('alerts.name'),
        // Wie in der Legacy: waagerecht mittig, ein Stueck unter dem
        // oberen Rand.
        'position' => 'top-center',
        'width'    => Alerts::width($app) . 'px',
        'z'        => 50,
        // Die einstellbaren Werte als CSS-Variablen. So braucht das
        // JavaScript sie nicht zu kennen, und das Stylesheet bleibt
        // eine gewoehnliche Datei ohne PHP darin.
        'vars'     => [
            '--alert-offset-top'   => Alerts::offsetTop($app) . 'px',
            '--alert-media-width'  => $medien['width'] !== '' ? $medien['width'] : '100%',
            '--alert-media-height' => $medien['height'] !== '' ? $medien['height'] : 'auto',
        ],
    ];

    return $slots;
});

$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/alerts/assets/alerts.css');
    $assets['js'][]  = $app->asset('/plugin/alerts/assets/alerts.js');

    return $assets;
});

// Eigenes CSS und JS fuer die Verwaltungsseiten - Schalter,
// aufklappbare Faelle und die Dateiauswahl bringt der Kern nicht mit.
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/alerts/assets/admin.css');
    $assets['js'][]  = $app->asset('/plugin/alerts/assets/admin.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Die Alert-Seite: nur die Reiter der Plugins
// -------------------------------------------------------------------
$seite = static function (Request $request, array $params = []) use ($app, $plugin): Response {
    $reiter = Alerts::tabs($app);

    // Ohne Angabe der erste Reiter. Es gibt keinen eigenen - diese
    // Seite gehoert den Alert-Plugins.
    $offen = strtolower(trim((string) ($params['tab'] ?? '')));
    if ($offen === '' || !isset($reiter[$offen])) {
        $offen = (string) array_key_first($reiter);
    }

    $inhalt = '';
    if ($offen !== '' && isset($reiter[$offen]) && $reiter[$offen]['render'] !== null) {
        // Ein Fehler im Reiter eines anderen Plugins darf nicht die
        // ganze Seite kosten - sonst kommt man nicht mehr an den
        // Hauptschalter, um es abzuschalten.
        try {
            $inhalt = (string) ($reiter[$offen]['render'])();
        } catch (Throwable $e) {
            $app->log('Alerts-Reiter "' . $offen . '" ist gescheitert: ' . $e->getMessage());
            $inhalt = '<div class="note note-error">'
                . htmlspecialchars(
                    translate('alerts.tab_failed', ['tab' => $reiter[$offen]['label']]),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</div>';
        }
    }

    return Response::html(
        $app->view->from($plugin->directory . '/views')->render('page', [
            'title'   => translate('alerts.name'),
            'active'  => 'display/alerts',
            'tabs'    => $reiter,
            'open'    => $offen,
            'content' => $inhalt,
            'enabled' => Alerts::enabled($app),
            'csrf'    => $app->auth->csrfToken(),
            'notice'  => $request->get('notice'),
            'error'   => $request->get('error'),
        ])
    );
};

// -------------------------------------------------------------------
//  Einstellungen dieses Plugins
// -------------------------------------------------------------------
// VOR der Muster-Route: der Router nimmt den ersten Treffer in
// Registrierungsreihenfolge, sonst faengt {tab} das "settings" ab.
$router->get('/display/alerts/settings', static function (Request $request) use ($app, $plugin, $scope): Response {
    return Response::html(
        $app->view->from($plugin->directory . '/views')->render('settings', [
            'title'       => translate('alerts.nav.settings'),
            'active'      => 'display/alerts',
            'width'       => Alerts::width($app),
            'offsetTop'   => Alerts::offsetTop($app),
            'mediaWidth'  => $app->settings->int('media_width', 0, $scope),
            'mediaHeight' => $app->settings->int('media_height', 0, $scope),
            'enabled'     => Alerts::enabled($app),
            'csrf'        => $app->auth->csrfToken(),
            'notice'      => $request->get('notice'),
            'error'       => $request->get('error'),
        ])
    );
}, ['auth' => true, 'permission' => 'Alerts.Global.View']);

$router->post('/display/alerts/settings', static function (Request $request) use ($app, $scope, $zurueckZu): Response {
    $zurueck = $zurueckZu('/display/alerts/settings');

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    switch ($request->input('action')) {
        case 'basics':
            if (!permission('Alerts.Global.Edit')) {
                return $zurueck(null, translate('common.error.no_permission'));
            }

            $breite = (int) $request->input('width');

            if ($breite < 160 || $breite > 3840) {
                return $zurueck(null, translate('alerts.width_invalid'));
            }

            $app->settings->setMany([
                'width'      => $breite,
                'offset_top' => max(0, min(2160, (int) $request->input('offset_top'))),
                // Leer heisst automatisch - dafuer 0 speichern.
                'media_width'  => max(0, (int) $request->input('media_width')),
                'media_height' => max(0, (int) $request->input('media_height')),
            ], $scope);

            return $zurueck(translate('alerts.saved'));

        case 'test':
            if (!permission('Alerts.Global.Test')) {
                return $zurueck(null, translate('common.error.no_permission'));
            }

            if (!Alerts::enabled($app)) {
                return $zurueck(null, translate('alerts.test_while_off'));
            }

            Alerts::send($app, [
                'kind'   => 'test',
                'text'   => translate('alerts.test_text'),
                'values' => ['username' => translate('alerts.test_username')],
            ]);

            return $zurueck(translate('alerts.test_sent'));
    }

    return $zurueck(null, translate('common.error.unknown_action'));
}, ['auth' => true]);

$router->get('/display/alerts', $seite, [
    'auth' => true,
    'permission' => 'Alerts.Global.View',
]);
$router->get('/display/alerts/{tab}', $seite, [
    'auth' => true,
    'permission' => 'Alerts.Global.View',
]);

// -------------------------------------------------------------------
//  Hauptschalter
// -------------------------------------------------------------------
$router->post('/display/alerts', static function (Request $request) use ($app, $scope, $zurueckZu): Response {
    $zurueck = $zurueckZu('/display/alerts');

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    if (!permission('Alerts.Global.Toggle')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    $an = !Alerts::enabled($app);
    $app->settings->set('enabled', $an, $scope);

    return $zurueck($an ? translate('alerts.turned_on') : translate('alerts.turned_off'));
}, ['auth' => true]);
