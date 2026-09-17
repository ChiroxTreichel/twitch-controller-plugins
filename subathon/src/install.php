<?php

declare(strict_types=1);

/**
 * Eine Tabelle: der Verlauf.
 *
 * Im Programm war das eine Datei namens "logs" neben der exe, Zeile
 * fuer Zeile mit Semikolon getrennt. Alles andere - Zeit, Grenzen,
 * Farben, Nachrichten, Happy Hour - lag in der config.json und liegt
 * hier in den Einstellungen unter "plugin:subathon".
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run("
    CREATE TABLE IF NOT EXISTS subathon_log (
        id         BIGSERIAL   PRIMARY KEY,
        kind       TEXT        NOT NULL,
        who        TEXT        NOT NULL DEFAULT '',
        amount     TEXT        NOT NULL DEFAULT '',
        seconds    INTEGER     NOT NULL DEFAULT 0,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )
");

// Der Verlauf wird immer von hinten gelesen - neueste zuerst.
$db->run('CREATE INDEX IF NOT EXISTS subathon_log_neu_idx ON subathon_log (id DESC)');
