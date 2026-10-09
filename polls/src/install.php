<?php

declare(strict_types=1);

/**
 * Drei Tabellen: die Umfragen, ihre Eintraege, die Stimmen.
 *
 * Im alten System lag jede Umfrage als JSON-Datei, die Stimmen in einer
 * zweiten. Zwei Zuschauer, die im selben Augenblick abstimmen, lasen
 * dieselbe Datei und schrieben sie nacheinander zurueck - die Stimme
 * des ersten war dann weg. Eine Zeile je Stimme kann das nicht.
 *
 * Eine Stimme ist eine Zeile je gewaehltem Eintrag. Wer seine Wahl
 * aendert, dessen Zeilen werden ersetzt (Polls::vote()).
 *
 * Eintraege sind je Umfrage eindeutig, ohne Gross und Klein - sonst
 * stuende ein Vorschlag "pizza" neben dem "Pizza", den es schon gibt.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run('
    CREATE TABLE IF NOT EXISTS polls_polls (
        id                      BIGSERIAL   PRIMARY KEY,
        title                   TEXT        NOT NULL,
        description             TEXT        NOT NULL DEFAULT \'\',
        max_choices             INTEGER     NOT NULL DEFAULT 1,
        ends_at                 TIMESTAMPTZ NOT NULL,
        suggestions             BOOLEAN     NOT NULL DEFAULT false,
        announce_discord_new    BOOLEAN     NOT NULL DEFAULT false,
        announce_discord_result BOOLEAN     NOT NULL DEFAULT false,
        announce_chat_new       BOOLEAN     NOT NULL DEFAULT false,
        announce_chat_result    BOOLEAN     NOT NULL DEFAULT false,
        result_announced_at     TIMESTAMPTZ,
        creator_id              TEXT        NOT NULL DEFAULT \'\',
        creator_name            TEXT        NOT NULL DEFAULT \'\',
        created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
    )
');

$db->run('
    CREATE TABLE IF NOT EXISTS polls_options (
        id           BIGSERIAL   PRIMARY KEY,
        poll_id      BIGINT      NOT NULL REFERENCES polls_polls (id) ON DELETE CASCADE,
        label        TEXT        NOT NULL,
        position     INTEGER     NOT NULL,
        suggested_by TEXT        NOT NULL DEFAULT \'\',
        created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
    )
');

$db->run('CREATE UNIQUE INDEX IF NOT EXISTS polls_options_label ON polls_options (poll_id, lower(label))');

$db->run('
    CREATE TABLE IF NOT EXISTS polls_votes (
        poll_id     BIGINT      NOT NULL REFERENCES polls_polls (id) ON DELETE CASCADE,
        option_id   BIGINT      NOT NULL REFERENCES polls_options (id) ON DELETE CASCADE,
        voter_id    TEXT        NOT NULL,
        voter_login TEXT        NOT NULL DEFAULT \'\',
        voted_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
        PRIMARY KEY (poll_id, voter_id, option_id)
    )
');

$db->run('CREATE INDEX IF NOT EXISTS polls_votes_option ON polls_votes (option_id)');
