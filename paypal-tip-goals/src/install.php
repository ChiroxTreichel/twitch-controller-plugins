<?php

declare(strict_types=1);

/**
 * Keine eigenen Tabellen: die Spenden stehen bei Tip-Goals, und was
 * PayPal sich merken muss - Order und Capture - steht dort in den
 * Spalten fuer den Anbieter.
 *
 * Bleibt der Umzug aus 1.x. Damals lagen Ziele, Spenden und
 * Einstellungen hier; seit 2.0 gehoeren sie Tip-Goals. Umgezogen wird
 * von dort aus - hier nur angestossen, falls Tip-Goals schon da ist und
 * dieses Plugin als Zweites aktualisiert wird. Ist es noch nicht da,
 * passiert hier nichts, und Tip-Goals zieht bei seiner Installation um.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

use TwitchController\Plugin\TipGoals\Migration;

if ($app->plugins->isInstalled('tip-goals') && class_exists(Migration::class)) {
    // Frisch von der Platte: discover() haelt sonst noch die alte
    // Fassung dieses Plugins fest, und der Umzug hielte es fuer 1.x.
    $app->plugins->discover(true);

    Migration::fromPaypal($app);
}
