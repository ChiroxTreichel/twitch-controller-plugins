<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Alerts - der Rahmen
 * ===================================================================
 *
 * Bringt den Bereich "Anzeigen > Alerts", die Flaeche im Overlay und
 * die Grundeinstellungen. Was ein Alert ist, liefern andere Plugins -
 * siehe src/Alerts.php fuer den Vertrag.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Alerts\Alerts;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

$scope = Alerts::scope();

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
// zu sehen ist. Konto hat order 0, das hier kommt dahinter.
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

$hooks->on('plugin.settings', static function (array $pages) use ($plugin): array {
    $pages[$plugin->slug] = [
        'label' => translate('alerts.nav.basics'),
        'href'  => '/display/alerts',
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

// Eigenes CSS fuer die Verwaltungsseiten - Schalter und aufklappbare
// Faelle bringt der Kern nicht mit.
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/alerts/assets/admin.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$seite = static function (Request $request, array $params) use ($app, $plugin, $scope): Response {
    $reiter = Alerts::tabs($app);

    // Der erste Reiter ist immer die Grundeinstellung - sie gehoert
    // diesem Plugin und braucht keinen Hook.
    $offen = strtolower(trim((string) ($params['tab'] ?? '')));
    if ($offen === '' || ($offen !== 'basics' && !isset($reiter[$offen]))) {
        $offen = 'basics';
    }

    $inhalt = '';
    if ($offen !== 'basics' && $reiter[$offen]['render'] !== null) {
        // Ein Fehler im Reiter eines anderen Plugins darf nicht die
        // ganze Seite kosten - sonst kommt man nicht mehr an die
        // Grundeinstellungen, um es abzuschalten.
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
            'title'      => translate('alerts.name'),
            'active'     => 'display/alerts',
            'tabs'       => $reiter,
            'open'       => $offen,
            'content'    => $inhalt,
            'enabled'    => Alerts::enabled($app),
            'width'      => Alerts::width($app),
            'offsetTop'  => Alerts::offsetTop($app),
            'mediaWidth' => $app->settings->int('media_width', 0, $scope),
            'mediaHeight' => $app->settings->int('media_height', 0, $scope),
            'duration'   => $app->settings->int('duration', Alerts::DEFAULT_DURATION, $scope),
            'csrf'       => $app->auth->csrfToken(),
            'notice'     => $request->get('notice'),
            'error'      => $request->get('error'),
        ])
    );
};

$router->get('/display/alerts', $seite, [
    'auth' => true,
    'permission' => 'Alerts.Global.View',
]);
$router->get('/display/alerts/{tab}', $seite, [
    'auth' => true,
    'permission' => 'Alerts.Global.View',
]);

// -------------------------------------------------------------------
//  Grundeinstellungen speichern
// -------------------------------------------------------------------
$router->post('/display/alerts', static function (Request $request) use ($app, $scope): Response {
    $zurueck = static function (?string $notice = null, ?string $error = null) use ($app): Response {
        $query = [];
        if ($notice !== null) {
            $query['notice'] = $notice;
        }
        if ($error !== null) {
            $query['error'] = $error;
        }

        return Response::redirect(
            $app->url('/display/alerts') . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    switch ($request->input('action')) {
        case 'basics':
            if (!permission('Alerts.Global.Edit')) {
                return $zurueck(null, translate('common.error.no_permission'));
            }

            $breite = (int) $request->input('width');
            $abstand = (int) $request->input('offset_top');
            $dauer = (int) $request->input('duration');

            if ($breite < 160 || $breite > 3840) {
                return $zurueck(null, translate('alerts.width_invalid'));
            }

            if ($dauer < 1 || $dauer > Alerts::MAX_DURATION) {
                return $zurueck(null, translate('alerts.duration_invalid', ['max' => Alerts::MAX_DURATION]));
            }

            $app->settings->setMany([
                'width'        => $breite,
                'offset_top'   => max(0, min(2160, $abstand)),
                'duration'     => $dauer,
                // Leer heisst automatisch - dafuer 0 speichern.
                'media_width'  => max(0, (int) $request->input('media_width')),
                'media_height' => max(0, (int) $request->input('media_height')),
            ], $scope);

            return $zurueck(translate('alerts.saved'));

        case 'toggle':
            if (!permission('Alerts.Global.Toggle')) {
                return $zurueck(null, translate('common.error.no_permission'));
            }

            $an = !Alerts::enabled($app);
            $app->settings->set('enabled', $an, $scope);

            return $zurueck($an ? translate('alerts.turned_on') : translate('alerts.turned_off'));

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
