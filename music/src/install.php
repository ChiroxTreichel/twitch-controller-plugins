<?php

declare(strict_types=1);

/**
 * Drei Tabellen. Im alten System waren das JSON-Dateien neben dem Code:
 * banned.json, who.json, favoriten/<twitchid>.json, playing, song.json.
 *
 * Das ging, solange genau ein Prozess schrieb - und der Cronjob lief
 * als Endlosschleife daneben. Zwei gleichzeitige Schreiber auf
 * derselben Datei verlieren einen von beiden, und zwar lautlos: die
 * Datei ist danach vollstaendig, nur eben ohne den einen Eintrag.
 *
 * Hier liegt alles in der Datenbank, wie alles andere auch.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var \TwitchController\Core\Config\Settings $settings
 * @var string|null $fromVersion
 */

use TwitchController\Plugin\Music\Music;

/*
 * Der Token der Steuer-API.
 *
 * Er wird HIER gewuerfelt und nicht beim ersten Aufruf: eine Anlage,
 * die erst einen Token bekommt, wenn jemand danach fragt, steht bis
 * dahin ohne einen da - und ein leerer Token heisst in Api::tokenOk()
 * "niemand darf", was richtig ist, aber nur, wenn es nie vorkommt.
 *
 * Ein vorhandener bleibt. Diese Datei laeuft auch bei jeder
 * Aktualisierung; wuerde sie neu wuerfeln, waeren danach alle
 * hinterlegten Knoepfe tot.
 */
Music::ensureApiToken($settings);

/*
 * Die Bannliste.
 *
 * Vier Arten in EINER Tabelle und nicht vier Tabellen: die Verwaltung
 * zeigt sie nebeneinander, geprueft werden sie zusammen, und ein
 * Eintrag ist immer dasselbe - eine Art, ein Schluessel, ein Name.
 *
 * Der Schluessel ist bei Titeln und Interpreten die Spotify-ID, bei
 * Genres und Zuschauern der kleingeschriebene Name. Beides zusammen ist
 * eindeutig, darum der Primaerschluessel ueber beide Spalten: derselbe
 * Titel laesst sich nicht zweimal sperren.
 */
$db->run("
    CREATE TABLE IF NOT EXISTS music_bans (
        kind       TEXT        NOT NULL,
        key        TEXT        NOT NULL,
        name       TEXT        NOT NULL DEFAULT '',
        detail     TEXT        NOT NULL DEFAULT '',
        added_by   TEXT        NOT NULL DEFAULT '',
        created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        PRIMARY KEY (kind, key)
    )
");

$db->run('CREATE INDEX IF NOT EXISTS music_bans_kind_idx ON music_bans (kind, created_at DESC)');

/*
 * Wer sich was gewuenscht hat.
 *
 * Zweierlei haengt daran, und darum steht es in einer Tabelle statt in
 * zwei Dateien:
 *
 *   who.json   welcher Name unter dem laufenden Titel steht
 *   lastWish   wann der Zuschauer zuletzt durfte (Abkuehlzeit)
 *
 * Im alten System war die Abkuehlzeit ein COOKIE - wer es loeschte,
 * durfte sofort wieder. Hier steht sie beim Wunsch, und das ist der
 * einzige Ort, an dem sie stehen kann, ohne dass der Zuschauer sie
 * anfassen kann.
 */
$db->run("
    CREATE TABLE IF NOT EXISTS music_wishes (
        id           SERIAL PRIMARY KEY,
        track_uri    TEXT        NOT NULL,
        track_name   TEXT        NOT NULL DEFAULT '',
        artists      TEXT        NOT NULL DEFAULT '',
        twitch_id    TEXT        NOT NULL DEFAULT '',
        twitch_name  TEXT        NOT NULL DEFAULT '',
        created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
    )
");

// Fuer "wer hat den laufenden Titel gewuenscht" - die Adresse ist der
// Zugriffsweg, und der neueste Wunsch gilt.
$db->run('CREATE INDEX IF NOT EXISTS music_wishes_uri_idx ON music_wishes (track_uri, created_at DESC)');

// Und fuer die Abkuehlzeit: wann war dieser Zuschauer zuletzt dran?
$db->run('CREATE INDEX IF NOT EXISTS music_wishes_user_idx ON music_wishes (twitch_id, created_at DESC)');

/*
 * Die Merkliste je Zuschauer.
 *
 * Im alten System eine Datei je Twitch-ID im Ordner "favoriten". Der
 * Name der Datei kam dabei aus einem Cookie und musste erst
 * entschaerft werden (sanitizeUserId) - ein Schritt, den man vergessen
 * kann. Eine Spalte kann man nicht vergessen.
 */
$db->run("
    CREATE TABLE IF NOT EXISTS music_favorites (
        twitch_id  TEXT        NOT NULL,
        track_id   TEXT        NOT NULL,
        track_uri  TEXT        NOT NULL DEFAULT '',
        name       TEXT        NOT NULL DEFAULT '',
        artists    TEXT        NOT NULL DEFAULT '',
        image      TEXT        NOT NULL DEFAULT '',
        url        TEXT        NOT NULL DEFAULT '',
        created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        PRIMARY KEY (twitch_id, track_id)
    )
");

$db->run('CREATE INDEX IF NOT EXISTS music_favorites_user_idx ON music_favorites (twitch_id, created_at DESC)');
