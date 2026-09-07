<?php

declare(strict_types=1);

/**
 * Eine Tabelle: die Raid-Anfragen.
 *
 * Der Login ist der Schluessel, weil es je Kanal genau eine Anfrage
 * gibt - eine zweite waere keine neue Frage, sondern dieselbe
 * lauter. Wer sich erneut meldet, ueberschreibt seine alte Zeile.
 *
 * KEIN Profilbild. Gespeichert veraltet es, sobald jemand sein Bild
 * bei Twitch wechselt, und dann braeuchte es Code, der es nachzieht.
 * Geholt wird es beim Anzeigen der Seite, in einem Aufruf fuer alle -
 * siehe Requests::profiles(). Die Rechnung geht auf, weil die Liste
 * kurz ist.
 *
 * Die Twitch-ID steht trotzdem hier: sie aendert sich NIE, und der
 * Raid braucht sie. Sie zu speichern erspart einen Aufruf in dem
 * Moment, in dem es schnell gehen soll.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run("
    CREATE TABLE IF NOT EXISTS raid_requests (
        login        TEXT        PRIMARY KEY,
        user_id      TEXT        NOT NULL DEFAULT '',
        display_name TEXT        NOT NULL DEFAULT '',
        status       TEXT        NOT NULL DEFAULT 'pending',
        requested_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        decided_at   TIMESTAMPTZ
    )
");

// Gefragt wird nach dem Zustand: die offenen fuer den Reiter, die
// angenommenen fuer den Live-Reiter, die entschiedenen zum Aufraeumen.
$db->run('CREATE INDEX IF NOT EXISTS raid_requests_status_idx ON raid_requests (status)');
