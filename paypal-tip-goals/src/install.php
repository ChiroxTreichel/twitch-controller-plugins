<?php

declare(strict_types=1);

/**
 * Zwei Tabellen: die Spendenziele und die vorgemerkten Spenden.
 *
 * Betraege als NUMERIC und nicht als Fliesskomma. Bei Geld sieht man,
 * dass 0.1 + 0.2 nicht 0.3 ist - der Balken stuende irgendwann auf
 * 49,999999 statt auf 50, und auf einer SPENDENseite ist das keine
 * Kleinigkeit.
 *
 * Die Vormerkungen brauchen eine eigene Tabelle, weil zwischen "der
 * Spender hat auf Spenden gedrueckt" und "das Geld ist da" eine fremde
 * Seite liegt. Waehrenddessen muss irgendwo stehen, worum es ging -
 * welcher Betrag, welches Ziel, welche Nachricht, wer.
 *
 * goal_id zeigt auf ein Ziel und wird beim Loeschen auf NULL gesetzt
 * statt die Spende mitzureissen: die Spende ist trotzdem angekommen.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run("
    CREATE TABLE IF NOT EXISTS pp_tip_goals (
        id         SERIAL PRIMARY KEY,
        position   INTEGER        NOT NULL DEFAULT 0,
        title      TEXT           NOT NULL DEFAULT '',
        current    NUMERIC(12, 2) NOT NULL DEFAULT 0,
        target     NUMERIC(12, 2) NOT NULL DEFAULT 0,
        created_at TIMESTAMPTZ    NOT NULL DEFAULT now()
    )
");

$db->run('CREATE INDEX IF NOT EXISTS pp_tip_goals_position_idx ON pp_tip_goals (position, id)');

$db->run("
    CREATE TABLE IF NOT EXISTS pp_donation_intents (
        token               TEXT PRIMARY KEY,
        twitch_user_id      TEXT           NOT NULL DEFAULT '',
        twitch_login        TEXT           NOT NULL DEFAULT '',
        twitch_display_name TEXT           NOT NULL DEFAULT '',
        message             TEXT,
        anonymous           BOOLEAN        NOT NULL DEFAULT false,
        amount_eur          NUMERIC(10, 2) NOT NULL CHECK (amount_eur > 0),
        goal_id             INTEGER        REFERENCES pp_tip_goals (id) ON DELETE SET NULL,
        paypal_order_id     TEXT,
        paypal_capture_id   TEXT,
        status              TEXT           NOT NULL DEFAULT 'open',
        created_at          TIMESTAMPTZ    NOT NULL DEFAULT now(),
        expires_at          TIMESTAMPTZ    NOT NULL,
        captured_at         TIMESTAMPTZ
    )
");

// Der Rueckweg von PayPal bringt die Order-Nummer mit, nicht unseren
// Merker - danach wird also nachgeschlagen.
$db->run('CREATE INDEX IF NOT EXISTS pp_donation_intents_order_idx ON pp_donation_intents (paypal_order_id)');

// Und das Aufraeumen fragt nach dem Zustand.
$db->run('CREATE INDEX IF NOT EXISTS pp_donation_intents_status_idx ON pp_donation_intents (status)');

// Eine Capture-ID darf es nur einmal geben. Das ist der zweite Riegel
// gegen eine doppelte Buchung - der erste ist die Bedingung im UPDATE,
// der dritte PayPals eigene Idempotenz.
$db->run('CREATE UNIQUE INDEX IF NOT EXISTS pp_donation_intents_capture_idx
              ON pp_donation_intents (paypal_capture_id)
           WHERE paypal_capture_id IS NOT NULL');
