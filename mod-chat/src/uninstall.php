<?php

declare(strict_types=1);

/**
 * Der Verlauf gehoert diesem Plugin und geht mit ihm.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS mod_chat_messages');
