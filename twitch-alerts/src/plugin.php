<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Twitch-Alerts
 * ===================================================================
 *
 * Sechs Alert-Typen: Follows, Bits, Subs, Gifted-Subs, Prime-Subs und
 * Raids. Die Oberflaeche haengt sich als Reiter in das Alerts-Plugin,
 * die Alerts selbst gehen ueber dessen Flaeche ins Overlay.
 *
 * Dieses Plugin bringt also den Inhalt, nicht den Rahmen - siehe
 * src/Types.php fuer die Tabelle, die beides treibt.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Alerts\Alerts;
use TwitchController\Plugin\TwitchAlerts\Config;
use TwitchController\Plugin\TwitchAlerts\Dispatcher;
use TwitchController\Plugin\TwitchAlerts\Types;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte - eines je Typ, wie im alten System
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $rechte = [];

    foreach (Types::all() as $type => $definition) {
        // Bereich.Funktion.Recht - die Funktion ist der Alert-Typ.
        $funktion = str_replace('-', '', ucfirst($type));

        $rechte['TwitchAlerts.' . $funktion . '.View']   = translate('twitch_alerts.perm.view', ['type' => $definition['label']]);
        $rechte['TwitchAlerts.' . $funktion . '.Edit']   = translate('twitch_alerts.perm.edit', ['type' => $definition['label']]);
        $rechte['TwitchAlerts.' . $funktion . '.Toggle'] = translate('twitch_alerts.perm.toggle', ['type' => $definition['label']]);
        $rechte['TwitchAlerts.' . $funktion . '.Test']   = translate('twitch_alerts.perm.test', ['type' => $definition['label']]);
    }

    $catalog['TwitchAlerts'] = [
        'label'       => translate('twitch_alerts.name'),
        'permissions' => $rechte,
    ];

    return $catalog;
});

/**
 * Der Rechte-Bereich eines Typs, z.B. "TwitchAlerts.Follow".
 */
$bereich = static function (string $type): string {
    return 'TwitchAlerts.' . str_replace('-', '', ucfirst($type));
};

// -------------------------------------------------------------------
//  Reiter im Alerts-Plugin
// -------------------------------------------------------------------
$hooks->on('alerts.tabs', static function (array $tabs) use ($app, $plugin, $bereich): array {
    foreach (Types::all() as $type => $definition) {
        // Wer den Typ nicht sehen darf, bekommt auch keinen Reiter.
        if (!permission($bereich($type) . '.View')) {
            continue;
        }

        $tabs['twitch-' . $type] = [
            'label' => $definition['label'],
            'order' => (int) $definition['order'],
            // Wird nur fuer den offenen Reiter aufgerufen.
            'render' => static function () use ($app, $plugin, $type, $definition, $bereich): string {
                $config = Config::of($app, $type);

                return $app->view->from($plugin->directory . '/views')->render('tab', [
                    'type'        => $type,
                    'definition'  => $definition,
                    'config'      => $config,
                    'area'        => $bereich($type),
                    'csrf'        => $app->auth->csrfToken(),
                    'defaultDuration' => Alerts::DEFAULT_DURATION,
                ], null);
            },
        ];
    }

    return $tabs;
});

// -------------------------------------------------------------------
//  Zusaetzliche Twitch-Abos und -Rechte
// -------------------------------------------------------------------
// Der Kern abonniert Follows, Subs, Bits und Raids schon selbst. Was
// hier fehlte, waere ein Abo fuer geschenkte Abos - das gehoert zu
// channel.subscription.gift und ist im Kern dabei. Deshalb nichts
// nachzufordern; der Eintrag bleibt als Stelle, an der es hingehoert.

// -------------------------------------------------------------------
//  Auf Events reagieren
// -------------------------------------------------------------------
$hooks->on('core.event.stored', static function (array $event) use ($app): void {
    // Laeuft im Webhook-Request: nur entscheiden und weitergeben.
    (new Dispatcher($app))->handle($event);
});

// -------------------------------------------------------------------
//  Speichern und testen
// -------------------------------------------------------------------
$router->post('/display/alerts/twitch/{type}', static function (
    Request $request,
    array $params
) use ($app, $bereich): Response {
    $type = strtolower(trim((string) ($params['type'] ?? '')));

    $zurueck = static function (?string $notice, ?string $error = null) use ($app, $type): Response {
        $query = [];
        if ($notice !== null) {
            $query['notice'] = $notice;
        }
        if ($error !== null) {
            $query['error'] = $error;
        }

        return Response::redirect(
            $app->url('/display/alerts/twitch-' . rawurlencode($type))
            . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };

    if (!Types::exists($type)) {
        return Response::redirect($app->url('/display/alerts'));
    }

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    $definition = Types::get($type);
    $aktion = $request->input('action');

    // -- Ein- und Ausschalten ---------------------------------------
    if ($aktion === 'toggle') {
        if (!permission($bereich($type) . '.Toggle')) {
            return $zurueck(null, translate('common.error.no_permission'));
        }

        $an = !Config::of($app, $type)['enabled'];
        Config::setEnabled($app, $type, $an);

        return $zurueck($an
            ? translate('twitch_alerts.turned_on', ['type' => $definition['label']])
            : translate('twitch_alerts.turned_off', ['type' => $definition['label']]));
    }

    // -- Test senden ------------------------------------------------
    if ($aktion === 'test') {
        if (!permission($bereich($type) . '.Test')) {
            return $zurueck(null, translate('common.error.no_permission'));
        }

        if (!Alerts::enabled($app)) {
            return $zurueck(null, translate('twitch_alerts.test_while_all_off'));
        }

        if (!Config::of($app, $type)['enabled']) {
            return $zurueck(null, translate('twitch_alerts.test_while_type_off'));
        }

        $fall = (string) $request->input('case');

        // Die Werte kommen aus dem Formular und werden nicht
        // gespeichert: sie sind zum Wegwerfen. Vorher musste man sie
        // erst sichern, damit ein Test sie benutzt.
        $werte = $request->post['preview'] ?? [];
        $werte = is_array($werte) ? $werte : [];

        $sauber = [];
        foreach (array_keys($definition['preview']) as $key) {
            $wert = trim((string) ($werte[$key] ?? ''));

            $sauber[$key] = function_exists('mb_substr')
                ? mb_substr($wert, 0, 200)
                : substr($wert, 0, 200);
        }

        return (new Dispatcher($app))->test($type, $fall, $sauber)
            ? $zurueck(translate('twitch_alerts.test_sent', ['type' => $definition['label']]))
            : $zurueck(null, translate('twitch_alerts.test_nothing'));
    }

    // Ab hier wird geschrieben.
    if (!permission($bereich($type) . '.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    $config = Config::of($app, $type);

    // -- Stufe anlegen oder loeschen --------------------------------
    if ($aktion === 'add_tier' && $definition['mode'] === 'tiers') {
        if (count($config['tiers']) >= Config::MAX_TIERS) {
            return $zurueck(null, translate('twitch_alerts.too_many_tiers', ['max' => Config::MAX_TIERS]));
        }

        // Die neue Stufe liegt ueber der hoechsten - sonst waere sie
        // sofort von einer anderen verdeckt.
        $hoechste = 0;
        foreach ($config['tiers'] as $stufe) {
            $hoechste = max($hoechste, (int) $stufe['min']);
        }

        $neu = Types::defaultTier($type);
        $neu['min'] = $hoechste + 1;

        $config['tiers'][] = $neu;
        Config::save($app, $type, $config);

        return $zurueck(translate('twitch_alerts.tier_added'));
    }

    if ($aktion === 'delete_tier' && $definition['mode'] === 'tiers') {
        $index = (int) $request->input('tier');

        if (count($config['tiers']) <= 1) {
            return $zurueck(null, translate('twitch_alerts.last_tier'));
        }

        if (!isset($config['tiers'][$index])) {
            return $zurueck(null, translate('twitch_alerts.no_such_tier'));
        }

        unset($config['tiers'][$index]);
        $config['tiers'] = array_values($config['tiers']);
        Config::save($app, $type, $config);

        return $zurueck(translate('twitch_alerts.tier_deleted'));
    }

    // -- Speichern --------------------------------------------------
    if ($aktion !== 'save') {
        return $zurueck(null, translate('common.error.unknown_action'));
    }

    if ($definition['mode'] === 'tiers') {
        $eingaben = $request->post['tiers'] ?? [];
        $stufen = [];

        if (is_array($eingaben)) {
            foreach ($eingaben as $stufe) {
                if (!is_array($stufe)) {
                    continue;
                }

                $stufen[] = [
                    'min'      => max(0, (int) ($stufe['min'] ?? 0)),
                    'text'     => trim((string) ($stufe['text'] ?? '')),
                    'video'    => Alerts::mediaUrl((string) ($stufe['video'] ?? '')),
                    'audio'    => Alerts::mediaUrl((string) ($stufe['audio'] ?? '')),
                    'duration' => max(0, min(120, (int) ($stufe['duration'] ?? 0))),
                ];
            }
        }

        if ($stufen === []) {
            return $zurueck(null, translate('twitch_alerts.no_tiers'));
        }

        $config['tiers'] = Config::sortTiers($stufen);
    } else {
        $eingaben = $request->post['cases'] ?? [];

        foreach (array_keys($definition['cases']) as $key) {
            $fall = is_array($eingaben) ? ($eingaben[$key] ?? []) : [];
            $fall = is_array($fall) ? $fall : [];

            $config['cases'][$key] = [
                'text'     => trim((string) ($fall['text'] ?? '')),
                'video'    => Alerts::mediaUrl((string) ($fall['video'] ?? '')),
                'audio'    => Alerts::mediaUrl((string) ($fall['audio'] ?? '')),
                'duration' => max(0, min(120, (int) ($fall['duration'] ?? 0))),
            ];
        }
    }

    Config::save($app, $type, $config);

    return $zurueck(translate('twitch_alerts.saved', ['type' => $definition['label']]));
}, ['auth' => true]);
