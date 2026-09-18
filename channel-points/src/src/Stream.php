<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * Laeuft der Stream, und worum geht es gerade?
 *
 * Daran haengen die Bedingungen: ohne Titel und Kategorie laesst sich
 * nicht entscheiden, ob eine Belohnung an oder aus gehoert.
 *
 * Woher die Angaben kommen:
 *
 *   stream.online / .offline   abonniert der Kern schon
 *   channel.update             fordert dieses Plugin nach - dort
 *                              stehen Titel und Kategorie, und die
 *                              aendern sich mitten im Stream
 *   Helix als Rueckfall        einmal alle paar Minuten, falls beim
 *                              Start des Plugins schon gestreamt wurde
 *
 * Dieselbe Bauart wie im Timer-Plugin, und mit Absicht eine eigene
 * Kopie: ein Plugin, das ein anderes zum Laufen braucht, ist keines.
 * Die paar Zeilen sind billiger als die Abhaengigkeit.
 */
final class Stream
{
    /**
     * Wie lange ein ueber Helix geholter Stand gilt.
     *
     * Kurz genug, dass ein verpasstes Event nicht den ganzen Stream
     * kostet; lang genug, dass daraus keine Abfrage je Takt wird.
     */
    private const REFRESH_SECONDS = 300;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @return array{live: bool, started_at: int, title: string, game: string, checked_at: int}
     */
    public function state(): array
    {
        $roh = $this->app->settings->get('stream', null, Rewards::scope());
        $roh = is_array($roh) ? $roh : [];

        return [
            'live'       => !empty($roh['live']),
            'started_at' => (int) ($roh['started_at'] ?? 0),
            'title'      => (string) ($roh['title'] ?? ''),
            'game'       => (string) ($roh['game'] ?? ''),
            'checked_at' => (int) ($roh['checked_at'] ?? 0),
        ];
    }

    /**
     * @param array{live: bool, started_at: int, title: string, game: string, checked_at: int} $stand
     */
    private function store(array $stand): void
    {
        $this->app->settings->set('stream', $stand, Rewards::scope());
    }

    /**
     * Auf ein Twitch-Event reagieren.
     *
     * @param array<string, mixed> $event Nutzlast von core.event.stored
     */
    public function onEvent(string $typ, array $event): void
    {
        $stand = $this->state();

        switch ($typ) {
            case 'twitch.stream.online':
                $stand['live'] = true;
                $stand['started_at'] = self::timestamp($event['started_at'] ?? null);
                $stand['checked_at'] = time();
                break;

            case 'twitch.stream.offline':
                $stand['live'] = false;
                $stand['started_at'] = 0;
                $stand['checked_at'] = time();
                break;

            case 'twitch.channel.update':
                // Genau hierfuer ist das Abo da: wer mitten im Stream
                // die Kategorie wechselt, erwartet, dass die
                // Belohnungen mitziehen.
                $stand['title'] = (string) ($event['title'] ?? $stand['title']);
                $stand['game'] = (string) ($event['category_name'] ?? $stand['game']);
                break;

            default:
                return;
        }

        $this->store($stand);
    }

    /**
     * Den Stand bei Twitch nachfragen, wenn er alt genug ist.
     *
     * @return array{live: bool, started_at: int, title: string, game: string, checked_at: int}
     */
    public function refreshed(): array
    {
        $stand = $this->state();
        $jetzt = time();

        if ($jetzt - $stand['checked_at'] < self::REFRESH_SECONDS) {
            return $stand;
        }

        $kanalId = $this->app->settings->string('twitch_broadcaster_id');
        if ($kanalId === '') {
            return $stand;
        }

        try {
            $antwort = $this->app->twitch->api()->as(TokenStore::BROADCASTER)->get('streams', [
                'user_id' => $kanalId,
            ]);
        } catch (Throwable $e) {
            // Kein Grund, den Takt abzubrechen: der alte Stand gilt
            // weiter, und beim naechsten Mal klappt es vielleicht.
            $this->app->log('Kanalpunkte: Stream-Status nicht abrufbar: ' . $e->getMessage());

            return $stand;
        }

        if (!$antwort->ok()) {
            $this->app->log('Kanalpunkte: Stream-Status nicht abrufbar: ' . $antwort->error());

            return $stand;
        }

        $daten = is_array($antwort->json['data'] ?? null) ? $antwort->json['data'] : [];
        $erster = is_array($daten[0] ?? null) ? $daten[0] : [];

        // Eine leere Liste heisst: der Kanal streamt gerade nicht.
        $laeuft = $erster !== [];

        $stand = [
            'live'       => $laeuft,
            'started_at' => $laeuft ? self::timestamp($erster['started_at'] ?? null) : 0,
            'title'      => $laeuft ? (string) ($erster['title'] ?? '') : $stand['title'],
            'game'       => $laeuft ? (string) ($erster['game_name'] ?? '') : $stand['game'],
            'checked_at' => $jetzt,
        ];

        $this->store($stand);

        return $stand;
    }

    /**
     * Zeitstempel von Twitch als Unixzeit. Fehlt er, gilt jetzt.
     */
    private static function timestamp(mixed $roh): int
    {
        $text = trim((string) $roh);
        if ($text === '') {
            return time();
        }

        $zeit = strtotime($text);

        return $zeit === false ? time() : $zeit;
    }
}
