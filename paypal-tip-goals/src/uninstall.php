<?php

declare(strict_types=1);

/**
 * Die Zugangsdaten und Gebuehrensaetze raeumt der Kern mit dem Bereich
 * dieses Plugins ab. Spenden und Ziele gehoeren Tip-Goals und bleiben.
 *
 * Nur falls der Umzug aus 1.x nie stattfand - Tip-Goals wurde nie
 * installiert - liegen hier noch die alten Tabellen. Die gehen mit,
 * wie sie es in 1.x auch getan haetten.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS pp_donation_intents');
$db->run('DROP TABLE IF EXISTS pp_tip_goals');
