<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Die Bannliste
 * ===================================================================
 *
 * Vier Arten, eine Tabelle: Titel, Interpret, Genre, Zuschauer. Genau
 * die des alten Systems (banned.json), und dieselbe Reihenfolge beim
 * Pruefen - Titel, dann Interpret, dann Genre.
 *
 * Die Reihenfolge ist nicht beliebig: sie entscheidet, WAS in der
 * Absage steht. Ein Titel eines gesperrten Interpreten, der selbst
 * gesperrt ist, wird als Titel gemeldet - das ist die genauere
 * Auskunft.
 */
final class Bans
{
    public const TABLE = 'music_bans';

    /** @var list<string> */
    public const KINDS = ['track', 'artist', 'genre', 'twitch'];

    /**
     * Der Schluessel, unter dem ein Eintrag steht.
     *
     * Titel und Interpret haben eine Spotify-ID - die ist eindeutig
     * und aendert sich nicht, auch wenn der Titel umbenannt wird.
     * Genre und Zuschauer haben nur einen Namen, und der wird
     * kleingeschrieben: "Schlager" und "schlager" sind dasselbe Genre,
     * und Twitch-Namen sind ohnehin gleichgueltig gegen Gross und
     * Klein.
     */
    public static function key(string $kind, string $wert): string
    {
        $wert = trim($wert);

        return in_array($kind, ['genre', 'twitch'], true) ? self::lower($wert) : $wert;
    }

    /**
     * Kleinschreiben, auch ohne mbstring.
     *
     * Der Kern verlaesst sich nirgends darauf, dass die Erweiterung da
     * ist - sie ist im Bild dabei, aber ein Plugin, das ohne sie
     * abstuerzt, waere ein Plugin, das auf einem fremden Server nicht
     * laeuft. Ohne mbstring bleibt ein "Ö" stehen; fuer Genres und
     * Twitch-Namen reicht das, denn die sind ohnehin ASCII.
     */
    private static function lower(string $wert): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($wert) : strtolower($wert);
    }

    /**
     * Alle Eintraege einer Art.
     *
     * @return list<array<string, mixed>>
     */
    public static function of(App $app, string $kind): array
    {
        if (!in_array($kind, self::KINDS, true)) {
            return [];
        }

        return $app->db->all(
            'SELECT kind, key, name, detail, added_by, created_at
               FROM ' . self::TABLE . '
              WHERE kind = :kind
              ORDER BY created_at DESC',
            ['kind' => $kind]
        );
    }

    /**
     * Alles, nach Art sortiert - fuer die Verwaltungsseite.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function all(App $app): array
    {
        $alles = [];

        foreach (self::KINDS as $kind) {
            $alles[$kind] = self::of($app, $kind);
        }

        return $alles;
    }

    /**
     * Einen Eintrag anlegen.
     *
     * ON CONFLICT DO NOTHING und nicht "vorher nachsehen": zwischen
     * Nachsehen und Einfuegen passt ein zweiter Klick, und der
     * scheiterte dann an der Tabelle statt an der Pruefung. Zweimal
     * sperren ist ohnehin keine Beanstandung - es ist gesperrt.
     */
    public static function add(
        App $app,
        string $kind,
        string $key,
        string $name,
        string $detail = '',
        string $addedBy = ''
    ): bool {
        $key = self::key($kind, $key);

        if (!in_array($kind, self::KINDS, true) || $key === '') {
            return false;
        }

        $app->db->run(
            'INSERT INTO ' . self::TABLE . ' (kind, key, name, detail, added_by)
                  VALUES (:kind, :key, :name, :detail, :by)
             ON CONFLICT (kind, key) DO NOTHING',
            [
                'kind'   => $kind,
                'key'    => $key,
                'name'   => trim($name) !== '' ? trim($name) : $key,
                'detail' => trim($detail),
                'by'     => trim($addedBy),
            ]
        );

        return true;
    }

    public static function remove(App $app, string $kind, string $key): bool
    {
        if (!in_array($kind, self::KINDS, true)) {
            return false;
        }

        $app->db->run(
            'DELETE FROM ' . self::TABLE . ' WHERE kind = :kind AND key = :key',
            ['kind' => $kind, 'key' => self::key($kind, $key)]
        );

        return true;
    }

    // -----------------------------------------------------------------
    //  Pruefen
    // -----------------------------------------------------------------

    /** Ist dieser Zuschauer gesperrt? */
    public static function isViewerBanned(App $app, ?string $login): bool
    {
        $login = self::key('twitch', (string) $login);

        if ($login === '') {
            return false;
        }

        return $app->db->value(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE kind = :kind AND key = :key',
            ['kind' => 'twitch', 'key' => $login]
        ) !== null;
    }

    /**
     * Ist dieser Titel gesperrt - und woran liegt es?
     *
     * Gibt die Art und den Namen zurueck, damit die Absage sagen kann,
     * WORAN es lag. "Dieser Titel steht auf der Liste" und "der
     * Interpret steht auf der Liste" sind fuer den Zuschauer zwei
     * verschiedene Auskuenfte - die erste kann er mit einem anderen
     * Titel desselben Interpreten beantworten, die zweite nicht.
     *
     * @param list<string> $artistIds
     * @param list<string> $genres
     * @return array{kind: string, name: string}|null
     */
    public static function check(App $app, string $trackId, array $artistIds = [], array $genres = []): ?array
    {
        $treffer = self::firstMatch($app, 'track', [$trackId]);

        if ($treffer !== null) {
            return $treffer;
        }

        $treffer = self::firstMatch($app, 'artist', $artistIds);

        if ($treffer !== null) {
            return $treffer;
        }

        return self::firstMatch($app, 'genre', array_map(
            static fn (string $g): string => self::lower($g),
            $genres
        ));
    }

    /**
     * @param list<string> $schluessel
     * @return array{kind: string, name: string}|null
     */
    private static function firstMatch(App $app, string $kind, array $schluessel): ?array
    {
        $schluessel = array_values(array_filter(array_map(
            static fn (string $s): string => self::key($kind, $s),
            $schluessel
        )));

        if ($schluessel === []) {
            return null;
        }

        /*
         * Die Platzhalter werden gezaehlt und nicht zusammengeklebt.
         * Ein IN mit eingesetzten Werten waere die eine Stelle, an der
         * ein Interpretenname aus Spotify in eine Abfrage geraet.
         */
        $namen = [];
        $werte = ['kind' => $kind];

        foreach ($schluessel as $i => $eines) {
            $namen[] = ':k' . $i;
            $werte['k' . $i] = $eines;
        }

        $zeile = $app->db->first(
            'SELECT kind, name FROM ' . self::TABLE . '
              WHERE kind = :kind AND key IN (' . implode(', ', $namen) . ')
              LIMIT 1',
            $werte
        );

        return $zeile === null
            ? null
            : ['kind' => (string) $zeile['kind'], 'name' => (string) $zeile['name']];
    }

    public static function count(App $app): int
    {
        return (int) $app->db->value('SELECT count(*) FROM ' . self::TABLE);
    }
}
