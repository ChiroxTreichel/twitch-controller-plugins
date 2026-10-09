<?php

declare(strict_types=1);

/**
 * Zwei Tabellen: wer wie viele Tickets hat, und wer was gewonnen hat.
 *
 * Tickets in einer Tabelle und nicht in den Einstellungen: sie kommen
 * aus dem Chat, also aus vielen Webhook-Requests gleichzeitig. Eine
 * Liste in einer Einstellung liesse sich nur lesen, aendern und
 * zurueckschreiben - und zwei Zuschauer im selben Augenblick
 * ueberschrieben sich gegenseitig das Ticket. Eine Zeile je Zuschauer
 * zaehlt die Datenbank selbst hoch.
 *
 * Die Gewinner bleiben stehen, wenn das Giveaway endet - man will
 * hinterher noch nachsehen, wem man was schicken muss. Geleert wird
 * die Liste erst, wenn die naechste Ziehung beginnt.
 *
 * Was eingestellt ist (Befehl, Texte, Preise) und wo die Ziehung
 * steht, liegt im Scope "plugin:giveaway".
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run('
    CREATE TABLE IF NOT EXISTS giveaway_entries (
        twitch_id      TEXT        PRIMARY KEY,
        login          TEXT        NOT NULL,
        display_name   TEXT        NOT NULL,
        tickets        INTEGER     NOT NULL DEFAULT 0,
        last_ticket_at TIMESTAMPTZ
    )
');

$db->run('
    CREATE TABLE IF NOT EXISTS giveaway_winners (
        id           BIGSERIAL   PRIMARY KEY,
        position     INTEGER     NOT NULL,
        prize        TEXT        NOT NULL,
        twitch_id    TEXT        NOT NULL,
        login        TEXT        NOT NULL,
        display_name TEXT        NOT NULL,
        tickets      INTEGER     NOT NULL,
        drawn_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
        announced_at TIMESTAMPTZ
    )
');
