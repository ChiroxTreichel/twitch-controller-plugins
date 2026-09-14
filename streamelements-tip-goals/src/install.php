<?php

declare(strict_types=1);

/**
 * Eine Tabelle: die Spendenziele.
 *
 * Warum eine Tabelle und keine Einstellung: hier steht Geld. Eine
 * Spende wird mit
 *
 *   UPDATE ... SET current = current + :betrag
 *
 * verbucht, und das ist EIN Schritt. Laege die Liste als JSON in den
 * Einstellungen, muesste der Worker sie lesen, aendern und
 * zurueckschreiben - und eine Spende, die dazwischen kaeme, waere weg.
 *
 * NUMERIC und nicht Fliesskomma, aus demselben Grund: bei Geld sieht
 * man, dass 0.1 + 0.2 nicht 0.3 ist. Der Balken stuende irgendwann auf
 * 49,999999 statt auf 50.
 *
 * position statt einer Sortierung nach Namen: das oberste Ziel ist das
 * laufende, und welches das ist, entscheidet der Streamer.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run("
    CREATE TABLE IF NOT EXISTS se_tip_goals (
        id         SERIAL PRIMARY KEY,
        position   INTEGER        NOT NULL DEFAULT 0,
        title      TEXT           NOT NULL DEFAULT '',
        current    NUMERIC(12, 2) NOT NULL DEFAULT 0,
        target     NUMERIC(12, 2) NOT NULL DEFAULT 0,
        created_at TIMESTAMPTZ    NOT NULL DEFAULT now()
    )
");

$db->run('CREATE INDEX IF NOT EXISTS se_tip_goals_position_idx ON se_tip_goals (position, id)');
