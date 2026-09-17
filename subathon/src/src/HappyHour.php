<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Subathon;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Happy Hour
 * ===================================================================
 *
 * Eine Stunde am Tag, in der eine Spende mehr bringt. Vier Arten, wie
 * im Programm (HAPPY_HOUR_TYPE):
 *
 *   keine          nichts Besonderes
 *   Double-Time    die Zeit zaehlt doppelt
 *   Extend-Only    sie verlaengert NUR die Obergrenze, nicht die Zeit
 *   Extend         beides - Obergrenze und Zeit
 *
 * Der Unterschied ist der Punkt der ganzen Sache: "Extend-Only" macht
 * den Stream nicht laenger, sondern macht es moeglich, ihn laenger zu
 * machen. Wer in dieser Stunde spendet, kauft Spielraum fuer spaeter.
 *
 * Sie gilt nur fuer SPENDEN. Abos und Bits bringen immer dasselbe -
 * so war es im Programm, und eine der Laufschriften sagt es auch:
 * "In der Happy Hour zaehlen nur Donations."
 */
final class HappyHour
{
    public const NONE = 0;
    public const DOUBLE_TIME = 1;
    public const EXTEND_ONLY = 2;
    public const EXTEND = 3;

    public const TYPES = [self::NONE, self::DOUBLE_TIME, self::EXTEND_ONLY, self::EXTEND];

    /**
     * Die eingetragenen Stunden, nach Uhrzeit sortiert.
     *
     * @return list<array{start_hour: int, type: int}>
     */
    public static function all(App $app): array
    {
        return self::normalize(json_decode(
            $app->settings->string('happy_hour', '[]', Subathon::scope()),
            true
        ));
    }

    /**
     * Aufraeumen, was aus den Einstellungen kommt.
     *
     * Dieselben Regeln wie im Programm (config.load): Stunde auf 0-23
     * bringen, unbekannte Arten und "keine" herauswerfen, jede Stunde
     * nur einmal, sortiert.
     *
     * Die Stunde nur einmal: zwei Eintraege fuer dieselbe Stunde
     * waeren zwei Antworten auf dieselbe Frage, und welche gilt, sieht
     * man der Liste nicht an.
     *
     * @return list<array{start_hour: int, type: int}>
     */
    public static function normalize(mixed $roh): array
    {
        if (!is_array($roh)) {
            return [];
        }

        $sauber = [];

        foreach ($roh as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }

            $stunde = (int) ($eintrag['start_hour'] ?? 0);
            $stunde = $stunde < 0 ? 0 : $stunde % 24;

            $art = (int) ($eintrag['type'] ?? self::NONE);

            if (!in_array($art, self::TYPES, true) || $art === self::NONE) {
                continue;
            }

            $sauber[$stunde] = ['start_hour' => $stunde, 'type' => $art];
        }

        ksort($sauber);

        return array_values($sauber);
    }

    /** @param list<array{start_hour: int, type: int}> $eintraege */
    public static function save(App $app, array $eintraege): void
    {
        $app->settings->set(
            'happy_hour',
            json_encode(self::normalize($eintraege)),
            Subathon::scope()
        );
    }

    /**
     * Welche Art gilt zu dieser Stunde?
     *
     * @param list<array{start_hour: int, type: int}> $eintraege
     */
    public static function typeAt(array $eintraege, int $stunde): int
    {
        foreach ($eintraege as $eintrag) {
            if ($eintrag['start_hour'] === $stunde) {
                return $eintrag['type'];
            }
        }

        return self::NONE;
    }

    /**
     * Die naechste Happy Hour von jetzt aus gesehen.
     *
     * Auch die LAUFENDE zaehlt als naechste - genau wie im Programm
     * (GetNextHappyHour sortiert nach dem Abstand modulo 24, und der
     * ist fuer die laufende Stunde 0). In der Laufschrift steht damit
     * waehrend der Happy Hour deren eigene Zeit, und das ist die
     * Auskunft, die in dem Moment zaehlt.
     *
     * @param list<array{start_hour: int, type: int}> $eintraege
     * @return array{start_hour: int, type: int}|null
     */
    public static function next(array $eintraege, int $stunde): ?array
    {
        if ($eintraege === []) {
            return null;
        }

        usort(
            $eintraege,
            static fn (array $a, array $b): int
                => (($a['start_hour'] - $stunde) + 24) % 24 <=> (($b['start_hour'] - $stunde) + 24) % 24
        );

        return $eintraege[0];
    }

    /**
     * Was eine Gutschrift in dieser Stunde wert ist.
     *
     * Zurueck kommen zwei Zahlen: was auf die Zeit geht und was auf
     * die Obergrenze. 1:1 aus Form1.ApplyHappyHourToTimer.
     *
     * @return array{seconds: int, extra: int}
     */
    public static function apply(int $sekunden, int $art): array
    {
        switch ($art) {
            case self::DOUBLE_TIME:
                return ['seconds' => $sekunden * 2, 'extra' => 0];

            case self::EXTEND_ONLY:
                return ['seconds' => 0, 'extra' => $sekunden];

            case self::EXTEND:
                return ['seconds' => $sekunden, 'extra' => $sekunden];
        }

        return ['seconds' => $sekunden, 'extra' => 0];
    }
}
