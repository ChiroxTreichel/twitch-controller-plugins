<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Raids;

use TwitchController\Core\App;

/**
 * Die Reiter der Raid-Seite.
 *
 * Raids bringt zwei mit: wer gerade live ist, und wem der Kanal folgt.
 * Was daran haengt, kommt als eigenes Plugin und haengt sich hier ein -
 * das Raiden selbst, das Roulette, die Anfragen.
 *
 * Dieselbe Verabredung wie bei Goals, Alerts und Streaminfo:
 * Schluessel, Titel, Platz in der Reihe, und eine Funktion fuer den
 * Inhalt. Aufgerufen wird sie nur fuer den offenen Reiter - hier
 * steckt in einem davon eine Twitch-Abfrage, und die soll ein Reiter,
 * den niemand ansieht, nicht kosten.
 */
final class Raids
{
    /**
     * @return array<string, array{label: string, order: int, render: callable|null}>
     */
    public static function tabs(App $app): array
    {
        $tabs = $app->hooks->filter('raids.tabs', []);
        if (!is_array($tabs)) {
            return [];
        }

        $sauber = [];

        foreach ($tabs as $key => $tab) {
            $key = strtolower(trim((string) $key));
            if ($key === '' || !is_array($tab)) {
                continue;
            }

            $sauber[$key] = [
                'label'  => trim((string) ($tab['label'] ?? $key)) ?: $key,
                'order'  => (int) ($tab['order'] ?? 50),
                'render' => is_callable($tab['render'] ?? null) ? $tab['render'] : null,
            ];
        }

        uasort($sauber, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $sauber;
    }
}
