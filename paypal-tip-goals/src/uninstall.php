<?php

declare(strict_types=1);

/**
 * Beide Tabellen gehen mit, die Einstellungen raeumt der Kern ab -
 * die PayPal-Zugangsdaten und die Rechtstexte inbegriffen.
 *
 * Die Spendenhistorie geht damit auch. Das ist eine Ansage wert: wer
 * belegen koennen will, welche Spende wann kam, holt sie sich VORHER
 * heraus. Bei PayPal bleibt selbstverstaendlich alles stehen - dort
 * sind die Zahlungen zuhause, hier stand nur, welchem Ziel sie galten.
 *
 * Zuerst die Spenden, dann die Ziele: die eine Tabelle zeigt auf die
 * andere.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS pp_donation_intents');
$db->run('DROP TABLE IF EXISTS pp_tip_goals');
