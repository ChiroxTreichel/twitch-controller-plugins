<?php

declare(strict_types=1);

/**
 * Die Tabelle geht mit, die Einstellungen (Hauptschalter, Runde) raeumt
 * der Kern ab.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS auto_shoutout_channels');
