<?php

declare(strict_types=1);

/**
 * Alle drei Tabellen gehen mit, die Einstellungen raeumt der Kern ab -
 * die Spotify-Zugangsdaten inbegriffen.
 *
 * Damit ist auch die Bannliste weg. Das ist eine Ansage wert: sie ist
 * ueber Monate gewachsen und laesst sich nicht aus Spotify
 * zurueckholen. Wer sie behalten will, schreibt sie vorher heraus.
 *
 * Bei Spotify selbst bleibt nichts zurueck ausser der Freigabe fuer
 * diese Anwendung - die nimmt man im eigenen Konto zurueck, nicht hier.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS music_favorites');
$db->run('DROP TABLE IF EXISTS music_wishes');
$db->run('DROP TABLE IF EXISTS music_bans');
