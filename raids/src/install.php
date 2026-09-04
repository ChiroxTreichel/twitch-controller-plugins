<?php

declare(strict_types=1);

/**
 * Eine Tabelle: die gefolgten Kanaele.
 *
 * Warum eine Tabelle und nicht eine Einstellung: es sind hunderte
 * Zeilen mit je zwei Zeitstempeln, und gefragt wird nach "die am
 * laengsten nicht geprueften" und "die in den letzten 30 Tagen aktiven".
 * Beides ist eine Abfrage und keine Schleife ueber ein JSON-Feld.
 *
 * Der Login ist der Schluessel und nicht die Twitch-ID: die Follow-Liste
 * von Twitch nennt beides, aber alles andere in dieser Funktion - die
 * Live-Abfrage, die Favoriten, die Adresse zum Kanal - laeuft ueber den
 * Login. Ein Login kann sich aendern; dann ist es fuer diese Liste ein
 * anderer Kanal, und das ist richtig: der alte Name zeigt auf niemanden
 * mehr.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run("
    CREATE TABLE IF NOT EXISTS raid_channels (
        login             TEXT        PRIMARY KEY,
        user_id           TEXT        NOT NULL DEFAULT '',
        display_name      TEXT        NOT NULL DEFAULT '',
        profile_image_url TEXT        NOT NULL DEFAULT '',
        followed_at       TIMESTAMPTZ,
        last_active_at    TIMESTAMPTZ,
        checked_at        TIMESTAMPTZ,
        favorite          BOOLEAN     NOT NULL DEFAULT false
    )
");

// Wonach gefragt wird, dafuer ein Index.
//
// "Die am laengsten nicht geprueften" ist die Frage jedes Cron-Ticks;
// NULLS FIRST, weil ein nie gepruefter Kanal zuerst drankommt.
$db->run('CREATE INDEX IF NOT EXISTS raid_channels_checked_idx ON raid_channels (checked_at NULLS FIRST)');

// Und die Favoriten - das ist die Liste fuer die Live-Abfrage.
$db->run('CREATE INDEX IF NOT EXISTS raid_channels_favorite_idx ON raid_channels (favorite) WHERE favorite');
