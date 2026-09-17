<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Wer sich was gewuenscht hat
 * ===================================================================
 *
 * Zwei Dinge haengen daran, und im alten System lagen sie an zwei
 * verschiedenen Orten:
 *
 *   who.json   welcher Name unter dem laufenden Titel steht
 *   lastWish   ein COOKIE mit dem Zeitpunkt des letzten Wunsches
 *
 * Das Cookie ist der Grund, warum das hier anders ist. Es lag beim
 * Zuschauer, und wer es loeschte, durfte sofort wieder - die
 * Abkuehlzeit war eine Bitte, keine Regel. Hier steht sie beim Wunsch
 * in der Datenbank.
 *
 * who.json hatte ausserdem eine Eigenart: es merkte sich je URI EINEN
 * Namen und loeschte ihn, wenn der Titel durch war. Wuenschten sich
 * zwei denselben Titel, ueberschrieb der zweite den ersten. Hier
 * bleibt jeder Wunsch stehen, und gefragt wird nach dem neuesten - das
 * ist derselbe Name wie vorher, nur ohne Datenverlust.
 */
final class Wishes
{
    public const TABLE = 'music_wishes';

    /**
     * Wie lange ein Wunsch aufbewahrt wird.
     *
     * Er wird fuer zweierlei gebraucht: fuer den Namen unter dem
     * laufenden Titel (Minuten) und fuer die Abkuehlzeit (Stunde). Ein
     * Monat ist grosszuegig und macht die Tabelle trotzdem nicht zum
     * Archiv.
     */
    public const KEEP_DAYS = 30;

    /**
     * Einen Wunsch eintragen.
     *
     * Die Namen des Titels kommen mit und werden nicht bei Bedarf
     * nachgeschlagen: wer in einem halben Jahr nachsieht, wer sich was
     * gewuenscht hat, soll nicht davon abhaengen, dass es den Titel bei
     * Spotify noch gibt.
     */
    public static function add(
        App $app,
        string $uri,
        string $trackName,
        string $artists,
        string $twitchId,
        string $twitchName
    ): void {
        $app->db->run(
            'INSERT INTO ' . self::TABLE . ' (track_uri, track_name, artists, twitch_id, twitch_name)
                  VALUES (:uri, :name, :artists, :id, :wer)',
            [
                'uri'     => $uri,
                'name'    => $trackName,
                'artists' => $artists,
                'id'      => $twitchId,
                'wer'     => $twitchName,
            ]
        );
    }

    /**
     * Wer hat diesen Titel gewuenscht?
     *
     * Der neueste Wunsch gilt. Leer heisst "niemand" - der Titel lief
     * aus der eigenen Wiedergabeliste, und dann steht unter ihm auch
     * nichts.
     */
    public static function wishedBy(App $app, string $uri): string
    {
        if ($uri === '') {
            return '';
        }

        return (string) ($app->db->value(
            'SELECT twitch_name FROM ' . self::TABLE . '
              WHERE track_uri = :uri
              ORDER BY created_at DESC
              LIMIT 1',
            ['uri' => $uri]
        ) ?? '');
    }

    /**
     * Zu mehreren Adressen auf einmal - fuer die Warteschlange.
     *
     * Eine Abfrage statt einer je Eintrag: die Warteschlange hat
     * zwanzig Titel, und zwanzig Abfragen im Takt des Workers sind
     * zwanzig zu viel.
     *
     * @param list<string> $uris
     * @return array<string, string>
     */
    public static function wishedByMany(App $app, array $uris): array
    {
        $uris = array_values(array_unique(array_filter($uris)));

        if ($uris === []) {
            return [];
        }

        $namen = [];
        $werte = [];

        foreach ($uris as $i => $uri) {
            $namen[] = ':u' . $i;
            $werte['u' . $i] = $uri;
        }

        /*
         * DISTINCT ON gibt je Adresse genau eine Zeile - die erste
         * nach der Sortierung, also den neuesten Wunsch. Das ist die
         * Postgres-Art, "der neueste je Gruppe" zu fragen, ohne
         * Unterabfrage.
         */
        $zeilen = $app->db->all(
            'SELECT DISTINCT ON (track_uri) track_uri, twitch_name
               FROM ' . self::TABLE . '
              WHERE track_uri IN (' . implode(', ', $namen) . ')
              ORDER BY track_uri, created_at DESC',
            $werte
        );

        $ergebnis = [];

        foreach ($zeilen as $zeile) {
            $ergebnis[(string) $zeile['track_uri']] = (string) $zeile['twitch_name'];
        }

        return $ergebnis;
    }

    /**
     * Wann darf dieser Zuschauer wieder? 0 heisst "jetzt".
     *
     * Gerechnet wird gegen den letzten Wunsch und nicht gegen einen
     * gespeicherten Zeitpunkt: so aendert eine geaenderte Abkuehlzeit
     * sofort alles, statt erst beim naechsten Wunsch zu wirken.
     */
    public static function nextAllowed(App $app, string $twitchId): int
    {
        if ($twitchId === '') {
            return 0;
        }

        $zuletzt = $app->db->value(
            'SELECT extract(epoch from created_at)::bigint
               FROM ' . self::TABLE . '
              WHERE twitch_id = :id
              ORDER BY created_at DESC
              LIMIT 1',
            ['id' => $twitchId]
        );

        if ($zuletzt === null) {
            return 0;
        }

        $frei = (int) $zuletzt + Music::cooldown($app) * 60;

        return $frei > time() ? $frei : 0;
    }

    /**
     * Die letzten Wuensche - fuer die Verwaltungsseite.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(App $app, int $anzahl = 25): array
    {
        return $app->db->all(
            'SELECT track_uri, track_name, artists, twitch_name, created_at
               FROM ' . self::TABLE . '
              ORDER BY created_at DESC
              LIMIT ' . max(1, min(200, $anzahl))
        );
    }

    /**
     * Alte Wuensche abraeumen.
     *
     * Laeuft im Takt des Workers mit. Gedrosselt wird nicht: das ist
     * ein DELETE mit einer Bedingung auf einem Index, und er trifft an
     * den meisten Tagen keine Zeile.
     */
    public static function cleanup(App $app): void
    {
        $app->db->run(
            'DELETE FROM ' . self::TABLE . "
              WHERE created_at < now() - interval '" . self::KEEP_DAYS . " days'"
        );
    }
}
