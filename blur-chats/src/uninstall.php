<?php

declare(strict_types=1);

/**
 * Bereiche und Screenshots gehoeren diesem Plugin und gehen mit ihm.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS blur_chats_regions');
$db->run('DROP TABLE IF EXISTS blur_chats_screens');
