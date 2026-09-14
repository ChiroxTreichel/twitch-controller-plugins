<?php

declare(strict_types=1);

/**
 * Die Tabelle geht mit, die Einstellungen raeumt der Kern ab - das
 * Token inbegriffen.
 *
 * Damit sind auch die Spendenziele weg. Das ist richtig: wer das
 * Plugin entfernt, will genau das - und ohne das Entfernen laesst sich
 * das Streamlabs-Plugin nicht installieren, weil sich die beiden
 * ausschliessen.
 *
 * Bei StreamElements aendert sich nichts. Die Spenden bleiben dort,
 * wo sie sind.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS se_tip_goals');
