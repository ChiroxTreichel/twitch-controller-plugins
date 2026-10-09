<?php

declare(strict_types=1);

/**
 * Umfragen, Eintraege und Stimmen gehoeren diesem Plugin und gehen mit
 * ihm. Reihenfolge wegen der Fremdschluessel: Stimmen zuerst.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS polls_votes');
$db->run('DROP TABLE IF EXISTS polls_options');
$db->run('DROP TABLE IF EXISTS polls_polls');
