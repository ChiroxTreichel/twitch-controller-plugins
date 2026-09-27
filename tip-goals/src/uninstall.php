<?php

declare(strict_types=1);

/**
 * Beide Tabellen gehen mit, die Einstellungen raeumt der Kern ab -
 * die Rechtstexte inbegriffen.
 *
 * Die Spendenhistorie geht damit auch. Das ist eine Ansage wert: wer
 * belegen koennen will, welche Spende wann kam, holt sie sich VORHER
 * heraus. Beim Zahlungsanbieter bleibt selbstverstaendlich alles
 * stehen - dort sind die Zahlungen zuhause, hier stand nur, welchem
 * Ziel sie galten.
 *
 * Zuerst die Spenden, dann die Ziele: die eine Tabelle zeigt auf die
 * andere.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS tip_donations');
$db->run('DROP TABLE IF EXISTS tip_goals');
