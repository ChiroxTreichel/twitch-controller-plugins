<?php

declare(strict_types=1);

/**
 * Die Tabelle geht mit, die Einstellungen raeumt der Kern ab.
 *
 * Auch die Favoriten sind damit weg. Das ist richtig: sie sind eine
 * Spalte dieser Tabelle, und wer das Plugin entfernt, will die Liste
 * los sein. Wer nur aufhoeren will, es zu benutzen, schaltet es aus.
 *
 * Bei Twitch aendert sich nichts - gefolgt wird weiter, wem gefolgt
 * wurde.
 *
 * @var \TwitchController\Core\Database\Db $db
 */

$db->run('DROP TABLE IF EXISTS raid_channels');
