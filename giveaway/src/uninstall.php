<?php

declare(strict_types=1);

/**
 * Tickets und Gewinner gehoeren diesem Plugin und gehen mit ihm.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS giveaway_entries');
$db->run('DROP TABLE IF EXISTS giveaway_winners');
