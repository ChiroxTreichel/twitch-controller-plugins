<?php

declare(strict_types=1);

/**
 * Der Verlauf geht mit, die Einstellungen raeumt der Kern ab.
 *
 * Damit ist auch die Zeit weg: Start, Dauer, Pausen. Wer mitten in
 * einem Subathon das Plugin entfernt, faengt danach bei null an - das
 * ist kein Versehen, sondern das, was "entfernen" heisst.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS subathon_log');
