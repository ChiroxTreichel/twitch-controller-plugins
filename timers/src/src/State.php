<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Timers;

use TwitchController\Core\App;

/**
 * Der Laufzeitstand: wie viele Chatzeilen seit dem letzten Post, wann
 * das war, und welche Nachricht als naechstes dran ist.
 *
 * Absichtlich getrennt von den Timern selbst: die Timer aendert ein
 * Mensch, den Stand aendert der Betrieb. Zusammen in einem Wert waere
 * jedes Zaehlen ein Schreiben auf die Einstellungen, die gerade jemand
 * im Formular offen hat - und beim Speichern waere der Zaehler wieder
 * der alte.
 */
final class State
{
    /**
     * Der ganze Stand.
     *
     * @return array{session: int, last_post: int, timers: array<string, array{lines: int, last_posted_at: int, message_index: int}>}
     */
    public static function load(App $app): array
    {
        $roh = $app->settings->get('state', null, Timers::scope());
        $roh = is_array($roh) ? $roh : [];

        $timer = [];
        foreach (is_array($roh['timers'] ?? null) ? $roh['timers'] : [] as $id => $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }

            $timer[(string) $id] = self::eintrag($eintrag);
        }

        return [
            'session'   => (int) ($roh['session'] ?? 0),
            'last_post' => (int) ($roh['last_post'] ?? 0),
            'timers'    => $timer,
        ];
    }

    /**
     * @param array{session: int, last_post: int, timers: array<string, array<string, int>>} $stand
     */
    public static function save(App $app, array $stand): void
    {
        $app->settings->set('state', $stand, Timers::scope());
    }

    /**
     * Der Stand eines einzelnen Timers - immer vollstaendig.
     *
     * @param array{session: int, last_post: int, timers: array<string, array<string, int>>} $stand
     * @return array{lines: int, last_posted_at: int, message_index: int}
     */
    public static function of(array $stand, string $id): array
    {
        return self::eintrag($stand['timers'][$id] ?? []);
    }

    /**
     * @param array{session: int, last_post: int, timers: array<string, array<string, int>>} $stand
     * @param array{lines: int, last_posted_at: int, message_index: int} $eintrag
     * @return array{session: int, last_post: int, timers: array<string, array<string, int>>}
     */
    public static function set(array $stand, string $id, array $eintrag): array
    {
        $stand['timers'][$id] = self::eintrag($eintrag);

        return $stand;
    }

    /**
     * Eine Chatzeile zaehlen - fuer jeden Timer.
     *
     * Je Timer ein eigener Zaehler, weil jeder nach seinem eigenen
     * Post wieder bei null anfaengt.
     *
     * @param array{session: int, last_post: int, timers: array<string, array<string, int>>} $stand
     * @param list<array<string, mixed>> $timer
     * @return array{session: int, last_post: int, timers: array<string, array<string, int>>}
     */
    public static function countLine(array $stand, array $timer): array
    {
        foreach ($timer as $eintrag) {
            $id = (string) $eintrag['id'];
            $stand['timers'][$id] = self::eintrag($stand['timers'][$id] ?? []);
            $stand['timers'][$id]['lines']++;
        }

        return $stand;
    }

    /**
     * Neuer Stream, neuer Anfang.
     *
     * Zaehler und Zeitpunkte aus dem letzten Stream sagen nichts mehr:
     * die Zuschauer sind andere, und "seit dem letzten Post sind zwei
     * Tage vergangen" wuerde jeden Timer sofort feuern lassen.
     *
     * Die Stelle in der Reihe bleibt absichtlich stehen - sonst
     * begaenne jeder Stream mit derselben Nachricht.
     *
     * @param array{session: int, last_post: int, timers: array<string, array<string, int>>} $stand
     * @return array{session: int, last_post: int, timers: array<string, array<string, int>>}
     */
    public static function newSession(array $stand, int $gestartet): array
    {
        $weiter = [];
        foreach ($stand['timers'] as $id => $eintrag) {
            $weiter[(string) $id] = [
                'lines'          => 0,
                'last_posted_at' => 0,
                'message_index'  => (int) ($eintrag['message_index'] ?? 0),
            ];
        }

        return [
            'session'   => $gestartet,
            'last_post' => 0,
            'timers'    => $weiter,
        ];
    }

    public static function forget(App $app, string $id): void
    {
        $stand = self::load($app);

        if (!isset($stand['timers'][$id])) {
            return;
        }

        unset($stand['timers'][$id]);
        self::save($app, $stand);
    }

    /**
     * @param array<string, mixed> $roh
     * @return array{lines: int, last_posted_at: int, message_index: int}
     */
    private static function eintrag(array $roh): array
    {
        return [
            'lines'          => max(0, (int) ($roh['lines'] ?? 0)),
            'last_posted_at' => max(0, (int) ($roh['last_posted_at'] ?? 0)),
            'message_index'  => max(0, (int) ($roh['message_index'] ?? 0)),
        ];
    }
}
