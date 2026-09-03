<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Timers;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * Laeuft der Stream, und worum geht es gerade?
 *
 * Timer haengen daran: ohne Stream posten sie nicht, und die Filter
 * fuer Titel und Kategorie brauchen den aktuellen Stand.
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
 * Der Rueckfall ist der Grund, warum das hier nicht nur aus Events
 * besteht: wer das Plugin mitten im Stream installiert, haette sonst
 * bis zum naechsten Streamende einen Zustand "offline" - und keinen
 * einzigen Timer.
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
     * Der aktuelle Stand.
     *
     * @return array{live: bool, started_at: int, title: string, game: string, checked_at: int}
     */
    public function state(): array
    {
        $roh = $this->app->settings->get('stream', null, Timers::scope());
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
        $this->app->settings->set('stream', $stand, Timers::scope());
    }

    // -----------------------------------------------------------------
    //  Events
    // -----------------------------------------------------------------

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
                // Titel und Kategorie aendern sich mitten im Stream.
                // Ohne dieses Abo greift ein Timer, der auf "Farming"
                // wartet, nach einem Wechsel weiter - oder nie.
                $stand['title'] = (string) ($event['title'] ?? $stand['title']);
                $stand['game'] = (string) ($event['category_name'] ?? $stand['game']);
                break;

            default:
                return;
        }

        $this->store($stand);
    }

    // -----------------------------------------------------------------
    //  Rueckfall ueber Helix
    // -----------------------------------------------------------------

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
            $this->app->log('Timer: Stream-Status nicht abrufbar: ' . $e->getMessage());

            return $stand;
        }

        if (!$antwort->ok()) {
            $this->app->log('Timer: Stream-Status nicht abrufbar: ' . $antwort->error());

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
     * Zeitstempel von Twitch als Unixzeit. Fehlt er, gilt jetzt -
     * besser eine Minute daneben als ein Timer, der sofort feuert.
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
