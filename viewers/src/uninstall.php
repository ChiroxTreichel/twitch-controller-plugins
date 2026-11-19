<?php

declare(strict_types=1);

/**
 * Die Tabellen gehen mit dem Plugin - auch die Geschichte der
 * ehemaligen Abonnenten und Follower. Die Follower-Liste selbst
 * gehoert dem Kern und bleibt. Die Einstellungen unter plugin:viewers
 * loescht der Kern.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS viewers_unfollows');
$db->run('DROP TABLE IF EXISTS viewers_subs');
