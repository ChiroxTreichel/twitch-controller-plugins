<?php

declare(strict_types=1);

/**
 * Eine Tabelle: die eingetragenen Kanaele samt ihrem Stand in der
 * laufenden Runde.
 *
 * "round" ist die Runde, in der jemand zuletzt im Chat erkannt wurde.
 * Weicht sie von der aktuellen ab, ist die naechste Nachricht die erste
 * in diesem Stream - und genau dann gibt es den Shoutout. Der Vergleich
 * steht im UPDATE selbst, damit zwei Nachrichten im selben Augenblick
 * nicht zwei Shoutouts ausloesen.
 *
 * "pending" haelt einen Shoutout fest, den Twitch wegen der Sperrfrist
 * (zwei Minuten zwischen zwei Shoutouts) noch nicht angenommen hat. Der
 * Takt holt ihn nach.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run("
    CREATE TABLE IF NOT EXISTS auto_shoutout_channels (
        login        TEXT        PRIMARY KEY,
        user_id      TEXT        NOT NULL DEFAULT '',
        display_name TEXT        NOT NULL DEFAULT '',
        round        TEXT,
        pending      BOOLEAN     NOT NULL DEFAULT false,
        tries        INTEGER     NOT NULL DEFAULT 0,
        seen_at      TIMESTAMPTZ,
        tried_at     TIMESTAMPTZ,
        sent_at      TIMESTAMPTZ,
        last_error   TEXT        NOT NULL DEFAULT '',
        added_at     TIMESTAMPTZ NOT NULL DEFAULT now()
    )
");
