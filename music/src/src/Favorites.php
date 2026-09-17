<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Die Merkliste des Zuschauers
 * ===================================================================
 *
 * Aus dem alten System uebernommen: wer einen Titel gut findet, legt
 * ihn ab und wuenscht ihn spaeter mit einem Klick, ohne den Link
 * wieder heraussuchen zu muessen.
 *
 * Dort war es eine Datei je Twitch-ID im Ordner "favoriten", und der
 * Dateiname kam aus einem COOKIE - er musste erst entschaerft werden
 * (sanitizeUserId), damit aus "../../.env" kein Pfad wird. Ein Schritt,
 * den man vergessen kann. Eine Spalte kann man nicht vergessen.
 *
 * Erkannt wird ein Eintrag an der Spotify-Kennung und nicht an der
 * Adresse wie damals: dieselbe Kennung hat je nach Land verschiedene
 * Adressen, und derselbe Titel landete sonst zweimal in der Liste.
 */
final class Favorites
{
    public const TABLE = 'music_favorites';

    /** Mehr merkt sich niemand, und die Seite soll eine Seite bleiben. */
    public const MAX = 100;

    /**
     * Die Merkliste eines Zuschauers, neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public static function of(App $app, string $twitchId): array
    {
        if (trim($twitchId) === '') {
            return [];
        }

        return $app->db->all(
            'SELECT track_id, track_uri, name, artists, image, url, created_at
               FROM ' . self::TABLE . '
              WHERE twitch_id = :id
              ORDER BY created_at DESC
              LIMIT ' . self::MAX,
            ['id' => $twitchId]
        );
    }

    public static function has(App $app, string $twitchId, string $trackId): bool
    {
        if (trim($twitchId) === '' || trim($trackId) === '') {
            return false;
        }

        return $app->db->value(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE twitch_id = :id AND track_id = :track',
            ['id' => $twitchId, 'track' => $trackId]
        ) !== null;
    }

    public static function count(App $app, string $twitchId): int
    {
        if (trim($twitchId) === '') {
            return 0;
        }

        return (int) $app->db->value(
            'SELECT count(*) FROM ' . self::TABLE . ' WHERE twitch_id = :id',
            ['id' => $twitchId]
        );
    }

    /**
     * Einen Titel merken.
     *
     * Name, Interpret und Bild kommen mit und werden nicht bei Bedarf
     * nachgeschlagen: die Liste soll sich anzeigen lassen, ohne fuer
     * jeden Eintrag bei Spotify nachzufragen - das waeren bei
     * hundert Eintraegen hundert Aufrufe.
     *
     * @param array<string, mixed> $titel wie Spotify ihn liefert
     */
    public static function add(App $app, string $twitchId, array $titel): bool
    {
        $id = trim((string) ($titel['id'] ?? ''));

        if (trim($twitchId) === '' || $id === '') {
            return false;
        }

        if (self::count($app, $twitchId) >= self::MAX && !self::has($app, $twitchId, $id)) {
            return false;
        }

        /*
         * ON CONFLICT DO NOTHING: zweimal merken ist keine
         * Beanstandung, es ist gemerkt. Und zwischen "nachsehen" und
         * "einfuegen" passt ein zweiter Klick.
         */
        $app->db->run(
            'INSERT INTO ' . self::TABLE . ' (twitch_id, track_id, track_uri, name, artists, image, url)
                  VALUES (:id, :track, :uri, :name, :artists, :image, :url)
             ON CONFLICT (twitch_id, track_id) DO NOTHING',
            [
                'id'      => $twitchId,
                'track'   => $id,
                'uri'     => (string) ($titel['uri'] ?? 'spotify:track:' . $id),
                'name'    => (string) ($titel['name'] ?? ''),
                'artists' => implode(', ', array_filter(array_map(
                    static fn (array $a): string => (string) ($a['name'] ?? ''),
                    (array) ($titel['artists'] ?? [])
                ))),
                'image'   => (string) ($titel['album']['images'][0]['url'] ?? ''),
                'url'     => (string) ($titel['external_urls']['spotify'] ?? ''),
            ]
        );

        return true;
    }

    public static function remove(App $app, string $twitchId, string $trackId): void
    {
        if (trim($twitchId) === '' || trim($trackId) === '') {
            return;
        }

        $app->db->run(
            'DELETE FROM ' . self::TABLE . ' WHERE twitch_id = :id AND track_id = :track',
            ['id' => $twitchId, 'track' => $trackId]
        );
    }

    /**
     * Einen gemerkten Titel holen - fuer "das wuensche ich jetzt".
     *
     * @return array<string, mixed>|null
     */
    public static function find(App $app, string $twitchId, string $trackId): ?array
    {
        if (trim($twitchId) === '' || trim($trackId) === '') {
            return null;
        }

        return $app->db->first(
            'SELECT track_id, track_uri, name, artists, image, url
               FROM ' . self::TABLE . '
              WHERE twitch_id = :id AND track_id = :track',
            ['id' => $twitchId, 'track' => $trackId]
        );
    }
}
