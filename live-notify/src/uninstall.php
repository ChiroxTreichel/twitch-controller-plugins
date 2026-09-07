<?php

declare(strict_types=1);

/**
 * Die Tabelle geht mit, die Einstellungen raeumt der Kern ab - darunter
 * die Webhook-Adresse.
 *
 * Bei Discord aendert sich nichts: der Webhook bleibt dort bestehen und
 * wird nur nicht mehr benutzt. Wer ihn wirklich los sein will, loescht
 * ihn in Discord.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS live_notify_channels');
