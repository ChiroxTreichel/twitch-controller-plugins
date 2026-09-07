<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Raids - Roulette
 * ===================================================================
 *
 * Ein Wuerfel ueber dem Live-Gitter. Das Roulette laeuft durch die
 * Kacheln, wird langsamer und bleibt auf einem Kanal stehen.
 *
 * Das ist alles, und es ist alles im Browser: die Kacheln stehen schon
 * auf der Seite, der Zufall ist ein Aufruf von Math.random(), und
 * gespeichert wird nichts. Ein Server-Anteil waere ein Aufruf fuer
 * etwas, das der Browser umsonst kann - und die Animation muesste die
 * Antwort trotzdem abwarten.
 *
 * Ist "Raids - Raiden" auch installiert, drueckt das Roulette am Ende
 * dessen Knopf. Es kennt das Plugin nicht: es sucht in der
 * Gewinnerkachel nach einem Knopf mit data-raid-start. Ist keiner da,
 * bleibt es beim Hervorheben - genau wie im alten System.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
// Drehen ist ein eigenes Recht und nicht dasselbe wie Raiden: wer
// drehen darf, aber nicht raiden, sieht den Gewinner - und der Knopf
// in der Kachel ist dann gar nicht da.
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['RaidsRoulette'] = [
        'label'       => translate('raids_roulette.name'),
        'permissions' => [
            'RaidsRoulette.Global.Spin' => translate('raids_roulette.perm.spin'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Dateien
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/raids-roulette/assets/roulette.css');
    $assets['js'][] = $app->asset('/plugin/raids-roulette/assets/roulette.js');

    return $assets;
});

// -------------------------------------------------------------------
//  Der Wuerfel ueber dem Gitter
// -------------------------------------------------------------------
$hooks->on('raids.live_actions', static function (array $knoepfe) use ($app, $plugin): array {
    if (!permission('RaidsRoulette.Global.Spin')) {
        return $knoepfe;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    $knoepfe[] = [
        // Vorn: das Roulette ist der Grund, warum man diesen Reiter mit
        // diesem Plugin offen hat.
        'order'  => 10,
        'render' => static fn (): string => $vorlagen->render('button', [], null),
    ];

    return $knoepfe;
});
