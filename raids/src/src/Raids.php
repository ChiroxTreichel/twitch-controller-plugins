<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Raids;

use TwitchController\Core\App;

/**
 * Die Reiter der Raid-Seite.
 *
 * Raids bringt zwei mit: wer gerade live ist, und wem der Kanal folgt.
 * Was daran haengt, kommt als eigenes Plugin - die Anfragen als eigener
 * Reiter, das Roulette und der Raid-Knopf dagegen IN den Live-Reiter,
 * ueber liveActions() und tileActions() weiter unten.
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

    // -----------------------------------------------------------------
    //  Der Live-Reiter als Einhaengepunkt
    // -----------------------------------------------------------------
    //
    //  Das Roulette und der Raid-Knopf gehoeren NICHT in einen eigenen
    //  Reiter. Sie arbeiten an genau der Liste, die im Live-Reiter
    //  steht: wer jetzt streamt. Ein zweiter Reiter mit derselben
    //  Liste waere dieselbe Seite zweimal - einmal zum Ansehen und
    //  einmal zum Draufdruecken.
    //
    //  Darum zwei Stellen zum Einhaengen: ueber dem Gitter und in der
    //  Kachel.

    /**
     * Knoepfe ueber dem Live-Gitter. Hier haengt das Roulette.
     *
     * @return list<array{order: int, render: callable}>
     */
    public static function liveActions(App $app): array
    {
        return self::actions($app->hooks->filter('raids.live_actions', []));
    }

    /**
     * Knoepfe IN einer Live-Kachel. Hier haengt der Raid-Knopf.
     *
     * render bekommt die Kachel und gibt fertiges HTML zurueck - anders
     * als bei den Reitern, weil es je Kachel einmal aufgerufen wird
     * und den Login braucht.
     *
     * @return list<array{order: int, render: callable}>
     */
    public static function tileActions(App $app): array
    {
        return self::actions($app->hooks->filter('raids.tile_actions', []));
    }

    /**
     * Zusaetzliche Logins fuer die Live-Abfrage.
     *
     * Der Live-Reiter zeigt die Favoriten. Wer sich per Raid-Anfrage
     * gemeldet hat, ist kein Favorit und soll trotzdem auftauchen -
     * darum darf ein Plugin Logins dazulegen.
     *
     * @return list<string>
     */
    public static function extraLogins(App $app): array
    {
        $logins = $app->hooks->filter('raids.live_logins', []);
        if (!is_array($logins)) {
            return [];
        }

        $sauber = [];

        foreach ($logins as $login) {
            $login = Channels::normalizeLogin((string) $login);
            if ($login !== '') {
                $sauber[$login] = true;
            }
        }

        return array_keys($sauber);
    }

    /**
     * Die gemeinsame Form der beiden Knopf-Hooks: nur Eintraege mit
     * einer aufrufbaren render-Funktion, nach order sortiert.
     *
     * @param mixed $roh
     * @return list<array{order: int, render: callable}>
     */
    private static function actions(mixed $roh): array
    {
        if (!is_array($roh)) {
            return [];
        }

        $sauber = [];

        foreach ($roh as $eintrag) {
            if (!is_array($eintrag) || !is_callable($eintrag['render'] ?? null)) {
                continue;
            }

            $sauber[] = [
                'order'  => (int) ($eintrag['order'] ?? 50),
                'render' => $eintrag['render'],
            ];
        }

        usort($sauber, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $sauber;
    }
}
