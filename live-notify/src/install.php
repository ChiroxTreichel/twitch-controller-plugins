<?php

declare(strict_types=1);

/**
 * Eine Tabelle: die beobachteten Kanaele samt ihrem Live-Zustand.
 *
 * Der Zustand MUSS gespeichert werden, und das ist der Kern dieser
 * Funktion: gemeldet wird nicht "ist live", sondern der Uebergang
 * offline -> live. Ohne gemerkten Zustand kaeme bei jedem Tick eine
 * neue Nachricht, also alle 15 Sekunden - und niemand haette den
 * Discord-Kanal danach noch abonniert.
 *
 * Welche Ziele je Kanal an sind, steht als JSONB-Liste in derselben
 * Zeile. Nicht je Ziel eine Spalte: die Ziele sind Plugins, und ein
 * neues Ziel darf keine Migration der Tabelle des Basis-Plugins
 * verlangen.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run("
    CREATE TABLE IF NOT EXISTS live_notify_channels (
        login        TEXT        PRIMARY KEY,
        display_name TEXT        NOT NULL DEFAULT '',
        targets      JSONB       NOT NULL DEFAULT '[]'::jsonb,
        live         BOOLEAN     NOT NULL DEFAULT false,
        started_at   TIMESTAMPTZ,
        checked_at   TIMESTAMPTZ,
        added_at     TIMESTAMPTZ NOT NULL DEFAULT now()
    )
");
