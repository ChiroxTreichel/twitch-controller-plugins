<?php

declare(strict_types=1);

/**
 * Die Tabelle geht mit, die Einstellungen raeumt der Kern ab.
 *
 * Damit ist /raidme weg und die Liste der Anfragen auch. Das ist
 * richtig: wer das Plugin entfernt, will keine Anfragen mehr. Wer nur
 * heute keine will, schliesst sie im Reiter.
 *
 * Die Cookies der Besucher bleiben in ihren Browsern stehen und
 * laufen dort ab. Sie zeigen dann auf nichts - eine Seite, die es
 * nicht mehr gibt, kann sie nicht lesen.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS raid_requests');
