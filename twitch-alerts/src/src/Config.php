<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TwitchAlerts;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Einstellungen je Alert-Typ.
 *
 * Ein Eintrag je Typ statt vierzig flacher Schluessel: das alte System
 * hatte "alert_sub_text_first", "alert_sub_video_first",
 * "alert_sub_duration_first" und so weiter. Hier steht alles zu einem
 * Typ in einem Wert - ein Lesevorgang, ein Schreibvorgang, und ein
 * neuer Fall braucht keine sechs neuen Schluessel.
 *
 * Aufbau:
 *
 *   [
 *     'enabled' => true,
 *     'cases'   => ['first' => ['text' => …, 'video' => …, 'audio' => …, 'duration' => 6]],
 *     'tiers'   => [['min' => 100, 'text' => …, 'video' => …, 'audio' => …, 'duration' => 6]],
 *     'preview' => ['username' => 'Talutah', …],
 *   ]
 */
final class Config
{
    public const SLUG = 'twitch-alerts';

    /** Mehr Stufen wird unuebersichtlich, und es ist nie noetig. */
    public const MAX_TIERS = 20;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    /**
     * Die Einstellungen eines Typs - immer vollstaendig, mit Vorgaben
     * fuer alles, was noch nie gespeichert wurde.
     *
     * @return array{enabled: bool, cases: array<string, array<string, mixed>>, tiers: list<array<string, mixed>>, preview: array<string, string>}
     */
    public static function of(App $app, string $type): array
    {
        $definition = Types::get($type);
        if ($definition === null) {
            return ['enabled' => false, 'cases' => [], 'tiers' => [], 'preview' => []];
        }

        $gespeichert = $app->settings->get($type, null, self::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $faelle = [];
        foreach ($definition['cases'] as $key => $label) {
            $vorhanden = $gespeichert['cases'][$key] ?? [];
            $vorhanden = is_array($vorhanden) ? $vorhanden : [];

            $faelle[$key] = [
                'text'     => (string) ($vorhanden['text'] ?? $definition['defaults'][$key] ?? ''),
                'video'    => (string) ($vorhanden['video'] ?? ''),
                'audio'    => (string) ($vorhanden['audio'] ?? ''),
                'duration' => self::duration($vorhanden['duration'] ?? null),
            ];
        }

        $stufen = [];
        if ($definition['mode'] === 'tiers') {
            $roh = $gespeichert['tiers'] ?? null;

            // Noch nie gespeichert? Dann eine Vorgabestufe, damit nach
            // der Installation ueberhaupt ein Alert kommt.
            if (!is_array($roh) || $roh === []) {
                $roh = [Types::defaultTier($type)];
            }

            foreach ($roh as $stufe) {
                if (!is_array($stufe)) {
                    continue;
                }

                $stufen[] = [
                    'min'      => max(0, (int) ($stufe['min'] ?? 0)),
                    'text'     => (string) ($stufe['text'] ?? ''),
                    'video'    => (string) ($stufe['video'] ?? ''),
                    'audio'    => (string) ($stufe['audio'] ?? ''),
                    'duration' => self::duration($stufe['duration'] ?? null),
                ];
            }

            $stufen = self::sortTiers($stufen);
        }

        // Testdaten sind reine Vorgaben fuer das Formular, kein
        // gespeicherter Zustand: was man dort eintippt, gilt fuer
        // diesen einen Test und wird nicht gesichert.
        $vorschau = [];
        foreach ($definition['preview'] as $key => $vorgabe) {
            $vorschau[$key] = (string) $vorgabe;
        }

        return [
            'enabled' => (bool) ($gespeichert['enabled'] ?? true),
            'cases'   => $faelle,
            'tiers'   => $stufen,
            'preview' => $vorschau,
        ];
    }

    /**
     * @param array<string, mixed> $daten
     */
    public static function save(App $app, string $type, array $daten): void
    {
        if (!Types::exists($type)) {
            return;
        }

        $app->settings->set($type, $daten, self::scope());
    }

    public static function setEnabled(App $app, string $type, bool $an): void
    {
        $aktuell = self::of($app, $type);
        $aktuell['enabled'] = $an;

        self::save($app, $type, $aktuell);
    }

    /**
     * Die passende Stufe zu einer Zahl: die hoechste, deren
     * Mindestwert erreicht ist.
     *
     * Beispiel mit Stufen ab 1, 100 und 1000 Bits:
     *   50 Bits   -> Stufe "ab 1"
     *   100 Bits  -> Stufe "ab 100"
     *   5000 Bits -> Stufe "ab 1000"
     *
     * @param list<array<string, mixed>> $tiers
     * @return array<string, mixed>|null
     */
    public static function matchTier(array $tiers, int $amount): ?array
    {
        $treffer = null;

        foreach (self::sortTiers($tiers) as $stufe) {
            if ($amount >= (int) $stufe['min']) {
                $treffer = $stufe;
            }
        }

        return $treffer;
    }

    /**
     * Stufen aufsteigend nach Mindestwert. Die Reihenfolge im Formular
     * soll nicht darueber entscheiden, welche greift.
     *
     * @param list<array<string, mixed>> $tiers
     * @return list<array<string, mixed>>
     */
    public static function sortTiers(array $tiers): array
    {
        usort($tiers, static fn (array $a, array $b): int => ((int) $a['min']) <=> ((int) $b['min']));

        return array_values(array_slice($tiers, 0, self::MAX_TIERS));
    }

    /**
     * Eine Dauer, die nicht aus dem Rahmen faellt. 0 heisst: es
     * gilt die Vorgabe aus Alerts::DEFAULT_DURATION - die Dauer
     * gehoert zum Alert, nicht zu einer globalen Einstellung.
     */
    private static function duration(mixed $wert): int
    {
        $zahl = (int) $wert;

        if ($zahl <= 0) {
            return 0;
        }

        return min(120, $zahl);
    }
}
