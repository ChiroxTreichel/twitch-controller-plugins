<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ModChat;

use TwitchController\Core\App;
use TwitchController\Core\Support\Dates;

/**
 * Die Nachrichten des Moderatorenchats.
 *
 * Ein einziger Raum fuer alle, die sich hier anmelden koennen. Kein
 * eigenes Recht: wer im Team ist, redet mit - ein Teamchat, in dem ein
 * Teil des Teams nicht mitlesen darf, ist keiner.
 *
 * Nachrichten lassen sich nicht loeschen und nicht bearbeiten. Was
 * dasteht, haben alle anderen schon gelesen; eine stille Korrektur
 * hinterher macht aus einem Verlauf ein Raetsel.
 */
final class Messages
{
    public const SLUG = 'mod-chat';

    /** So viele Nachrichten stehen beim Oeffnen der Seite da. */
    public const SHOW = 200;

    /** Laenger darf eine Nachricht nicht sein. */
    public const MAX_LENGTH = 1000;

    /** Nach so vielen Tagen raeumt der Worker eine Nachricht ab. */
    public const KEEP_DAYS = 30;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * Die letzten Nachrichten, die aelteste zuerst - so, wie ein Chat
     * sich liest.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(): array
    {
        return $this->app->db->all(
            'SELECT * FROM (
                 SELECT id, twitch_id, display_name, text, created_at
                   FROM mod_chat_messages
                  ORDER BY id DESC
                  LIMIT ' . self::SHOW . '
             ) AS letzte
             ORDER BY id'
        );
    }

    /**
     * Was nach einer bestimmten Nummer dazukam.
     *
     * Die Nummer und nicht die Uhrzeit: zwei Nachrichten in derselben
     * Mikrosekunde gibt es, zwei mit derselben Nummer nicht.
     *
     * @return list<array<string, mixed>>
     */
    public function after(int $id): array
    {
        return $this->app->db->all(
            'SELECT id, twitch_id, display_name, text, created_at
               FROM mod_chat_messages
              WHERE id > CAST(:id AS BIGINT)
              ORDER BY id
              LIMIT ' . self::SHOW,
            ['id' => (string) $id]
        );
    }

    /**
     * Eine Nachricht des angemeldeten Benutzers ablegen.
     *
     * @param array<string, mixed> $user aus $app->auth->user()
     * @return string leer bei Erfolg, sonst der Grund
     */
    public function add(array $user, string $text): string
    {
        $text = self::clean($text);

        if ($text === '') {
            return translate('mod_chat.error.empty');
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            return translate('mod_chat.error.too_long', ['max' => (string) self::MAX_LENGTH]);
        }

        $this->app->db->run(
            'INSERT INTO mod_chat_messages (twitch_id, display_name, text)
             VALUES (:id, :name, :text)',
            [
                'id'   => (string) $user['twitch_id'],
                'name' => (string) ($user['display_name'] ?? $user['login'] ?? '?'),
                'text' => $text,
            ]
        );

        return '';
    }

    /** Was aelter ist als KEEP_DAYS, kommt weg. Laeuft im Worker. */
    public function prune(): void
    {
        // Schreibweise wie im Kern: ein gebundener Parameter kann in
        // Postgres nicht direkt hinter INTERVAL stehen.
        $this->app->db->run(
            'DELETE FROM mod_chat_messages
              WHERE created_at < now() - (:days || \' days\')::interval',
            ['days' => (string) self::KEEP_DAYS]
        );
    }

    /**
     * Eine Zeile so, wie Seite und Skript sie brauchen.
     *
     * Beide bekommen dieselbe Form - die Seite rendert sie beim Laden,
     * das Skript baut daraus die Zeilen, die danach dazukommen. Zwei
     * Formen liefen auseinander, und dann saehe eine nachgeladene
     * Nachricht anders aus als eine von vorhin.
     *
     * @param array<string, mixed> $row
     * @return array{id: int, name: string, text: string, time: string, day: string, color: string, own: bool}
     */
    public static function present(array $row, string $me): array
    {
        $absender = (string) $row['twitch_id'];

        return [
            'id'    => (int) $row['id'],
            'name'  => (string) $row['display_name'],
            'text'  => (string) $row['text'],
            'time'  => Dates::format((string) $row['created_at'], 'H:i'),
            'day'   => Dates::day((string) $row['created_at']),
            'color' => self::color($absender),
            'own'   => $absender !== '' && $absender === $me,
        ];
    }

    /**
     * Eine feste Farbe je Person.
     *
     * Aus der Twitch-ID und nicht aus dem Namen: wer sich bei Twitch
     * umbenennt, behaelt seine Farbe, und alle sehen fuer dieselbe
     * Person dieselbe. Hell genug fuer den dunklen Hintergrund der
     * Verwaltung.
     */
    public static function color(string $twitchId): string
    {
        return sprintf('hsl(%d, 70%%, 72%%)', crc32($twitchId) % 360);
    }

    /**
     * Zeilenenden vereinheitlichen, Steuerzeichen raus, aussen kuerzen.
     *
     * Zeilenumbrueche bleiben: "Raid kommt gleich" und darunter der
     * Kanalname ist eine gewoehnliche Nachricht. Mehr als eine Leerzeile
     * am Stueck schiebt aber nur den Verlauf weg.
     *
     * Nur Steuerzeichen, nicht alles aus \p{C}: darin steckt auch der
     * unsichtbare Verbinder, aus dem Emoji wie die Familie bestehen -
     * ohne ihn stuenden dort drei einzelne Gesichter.
     */
    public static function clean(string $text): string
    {
        // Ungueltiges UTF-8 liesse preg_replace() mit /u scheitern, und
        // aus der Nachricht wuerde stillschweigend eine leere.
        $text = mb_scrub($text, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[\x{0}-\x{8}\x{B}-\x{1F}\x{7F}-\x{9F}]/u', '', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
