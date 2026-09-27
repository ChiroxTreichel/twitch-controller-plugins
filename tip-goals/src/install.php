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
 * welcher Betrag, welches Ziel, welche Nachricht, wer, und ueber
 * welchen Anbieter.
 *
 * Die Spalten fuer den Anbieter heissen allgemein und nicht nach
 * PayPal: provider_ref ist das, womit der Anbieter die Zahlung
 * zurueckmeldet (bei PayPal die Order), provider_tx das, was beim
 * Einzug entsteht (bei PayPal die Capture).
 *
 * goal_id zeigt auf ein Ziel und wird beim Loeschen auf NULL gesetzt
 * statt die Spende mitzureissen: die Spende ist trotzdem angekommen.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

use TwitchController\Plugin\TipGoals\Migration;

$db->run("
    CREATE TABLE IF NOT EXISTS tip_goals (
        id         SERIAL PRIMARY KEY,
        position   INTEGER        NOT NULL DEFAULT 0,
        title      TEXT           NOT NULL DEFAULT '',
        current    NUMERIC(12, 2) NOT NULL DEFAULT 0,
        target     NUMERIC(12, 2) NOT NULL DEFAULT 0,
        created_at TIMESTAMPTZ    NOT NULL DEFAULT now()
    )
");

$db->run('CREATE INDEX IF NOT EXISTS tip_goals_position_idx ON tip_goals (position, id)');

$db->run("
    CREATE TABLE IF NOT EXISTS tip_donations (
        token               TEXT PRIMARY KEY,
        provider            TEXT           NOT NULL,
        twitch_user_id      TEXT           NOT NULL DEFAULT '',
        twitch_login        TEXT           NOT NULL DEFAULT '',
        twitch_display_name TEXT           NOT NULL DEFAULT '',
        message             TEXT,
        anonymous           BOOLEAN        NOT NULL DEFAULT false,
        amount_eur          NUMERIC(10, 2) NOT NULL CHECK (amount_eur > 0),
        net_eur             NUMERIC(10, 2),
        goal_id             INTEGER        REFERENCES tip_goals (id) ON DELETE SET NULL,
        provider_ref        TEXT,
        provider_tx         TEXT,
        status              TEXT           NOT NULL DEFAULT 'open',
        created_at          TIMESTAMPTZ    NOT NULL DEFAULT now(),
        expires_at          TIMESTAMPTZ    NOT NULL,
        captured_at         TIMESTAMPTZ
    )
");

// Der Rueckweg vom Anbieter bringt dessen Nummer mit, nicht unseren
// Merker - danach wird also nachgeschlagen.
$db->run('CREATE INDEX IF NOT EXISTS tip_donations_ref_idx ON tip_donations (provider, provider_ref)');

// Und das Aufraeumen fragt nach dem Zustand.
$db->run('CREATE INDEX IF NOT EXISTS tip_donations_status_idx ON tip_donations (status)');

// Eine Einzugsnummer darf es je Anbieter nur einmal geben. Das ist der
// zweite Riegel gegen eine doppelte Buchung - der erste ist die
// Bedingung im UPDATE, der dritte die Idempotenz beim Anbieter.
$db->run('CREATE UNIQUE INDEX IF NOT EXISTS tip_donations_tx_idx
              ON tip_donations (provider, provider_tx)
           WHERE provider_tx IS NOT NULL');

// Was bisher in "Tip-Goals - PayPal" 1.x stand, zieht hierher um.
// Laeuft auch aus dessen install.php - wer zuletzt kommt, zieht um.
Migration::fromPaypal($app);
