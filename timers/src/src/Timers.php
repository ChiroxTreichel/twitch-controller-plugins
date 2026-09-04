<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Timers;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Die Timer und ihre Einstellungen.
 *
 * Ein Timer postet in festem Abstand eine Nachricht in den Chat -
 * reihum, wenn mehrere hinterlegt sind. Vier Bedingungen entscheiden,
 * ob er ueberhaupt drankommt:
 *
 *   Stream laeuft   ohne Stream keine Timer. Das ist die wichtigste:
 *                   ein Timer, der in einen leeren Chat postet, ist
 *                   nur Muell im Verlauf.
 *   Intervall       fruehestens so viele Minuten nach dem letzten Mal
 *   Min. Zeilen     so viele Chatzeilen muessen seitdem gekommen sein
 *   Titel und Spiel greift nur, wenn der Stream dazu passt
 *
 * Gespeichert wird im Scope "plugin:timers": die Timer unter "timers",
 * der Laufzeitstand unter "state". Den Scope loescht der Kern beim
 * Entfernen des Plugins mit.
 */
final class Timers
{
    public const SLUG = 'timers';

    /**
     * Grenzen des Intervalls.
     *
     * Unter fuenf Minuten wird jeder Timer zur Belaestigung, ueber zwei
     * Stunden feuert er in einem normalen Stream nie. Beides stand
     * schon im alten System so.
     */
    public const INTERVAL_MIN = 5;
    public const INTERVAL_MAX = 120;

    /**
     * Mindestabstand zwischen zwei Timer-Posts, ueber alle Timer.
     *
     * Ohne das feuern nach einer langen Pause mehrere Timer im selben
     * Moment, und im Chat stehen drei Werbeblöcke untereinander.
     */
    public const GLOBAL_THROTTLE = 30;

    /** Twitch nimmt 500 Zeichen; 400 war schon im alten System die Grenze. */
    public const MAX_MESSAGE = 400;

    /** Mehr wird unuebersichtlich. */
    public const MAX_MESSAGES = 50;
    public const MAX_TIMERS = 50;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    // -----------------------------------------------------------------
    //  Die Timer
    // -----------------------------------------------------------------

    /**
     * Alle Timer in der gespeicherten Reihenfolge.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(App $app): array
    {
        $gespeichert = $app->settings->get('timers', null, self::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $timer = [];
        foreach ($gespeichert as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }

            $sauber = self::normalize($eintrag);
            if ($sauber !== null) {
                $timer[] = $sauber;
            }
        }

        return $timer;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(App $app, string $id): ?array
    {
        foreach (self::all($app) as $timer) {
            if ($timer['id'] === $id) {
                return $timer;
            }
        }

        return null;
    }

    /**
     * Einen Timer anlegen oder aendern.
     *
     * @param array<string, mixed> $eingabe
     * @return array{ok: bool, error: string, id: string}
     */
    public static function save(App $app, array $eingabe): array
    {
        $sauber = self::normalize($eingabe);
        if ($sauber === null) {
            return ['ok' => false, 'error' => translate('timers.error.incomplete'), 'id' => ''];
        }

        $alle = self::all($app);
        $gefunden = false;

        foreach ($alle as $i => $timer) {
            if ($timer['id'] === $sauber['id']) {
                $alle[$i] = $sauber;
                $gefunden = true;
                break;
            }
        }

        if (!$gefunden) {
            if (count($alle) >= self::MAX_TIMERS) {
                return [
                    'ok'    => false,
                    'error' => translate('timers.error.too_many', ['max' => (string) self::MAX_TIMERS]),
                    'id'    => '',
                ];
            }

            $alle[] = $sauber;
        }

        $app->settings->set('timers', array_values($alle), self::scope());

        return ['ok' => true, 'error' => '', 'id' => $sauber['id']];
    }

    public static function delete(App $app, string $id): bool
    {
        $alle = self::all($app);
        $uebrig = array_values(array_filter(
            $alle,
            static fn (array $timer): bool => $timer['id'] !== $id
        ));

        if (count($uebrig) === count($alle)) {
            return false;
        }

        $app->settings->set('timers', $uebrig, self::scope());
        State::forget($app, $id);

        return true;
    }

    // -----------------------------------------------------------------
    //  Pruefen und Zurechtruecken
    // -----------------------------------------------------------------

    /**
     * Ein Timer, wie er gespeichert wird - oder null, wenn er unbrauchbar
     * ist.
     *
     * Unbrauchbar heisst: ohne Titel oder ohne eine einzige Nachricht.
     * Beides waere ein Timer, der nichts tun kann.
     *
     * @param array<string, mixed> $eingabe
     * @return array<string, mixed>|null
     */
    public static function normalize(array $eingabe): ?array
    {
        $titel = trim((string) ($eingabe['title'] ?? ''));
        if ($titel === '') {
            return null;
        }

        $nachrichten = self::normalizeMessages($eingabe['messages'] ?? null);

        // Leere Zeilen duerfen dabei sein, aber nicht nur leere: ein
        // Timer ohne Nachricht kann nichts tun.
        if (self::activeMessages(['messages' => $nachrichten]) === []) {
            return null;
        }

        $intervall = (int) ($eingabe['interval_minutes'] ?? self::INTERVAL_MIN);
        $intervall = max(self::INTERVAL_MIN, min(self::INTERVAL_MAX, $intervall));

        // Als Befehl geht nur, was auch ein Befehlsname sein kann.
        // Sonst stuende in der Oberflaeche ein Schalter, der nichts
        // bewirkt.
        $alsBefehl = !empty($eingabe['allow_as_command']) && self::commandName($titel) !== '';

        return [
            'id'               => self::normalizeId((string) ($eingabe['id'] ?? '')),
            'title'            => self::cut($titel, 80),
            'interval_minutes' => $intervall,
            'min_lines'        => max(0, (int) ($eingabe['min_lines'] ?? 0)),
            'title_keywords'   => self::cut(trim((string) ($eingabe['title_keywords'] ?? '')), 200),
            'game'             => self::cut(trim((string) ($eingabe['game'] ?? '')), 80),
            'messages'         => $nachrichten,
            'enabled'          => !empty($eingabe['enabled']),
            'allow_as_command' => $alsBefehl,
        ];
    }

    /**
     * Nachrichten aus einem Textfeld oder einer Liste.
     *
     * @return list<string>
     */
    public static function normalizeMessages(mixed $roh): array
    {
        $zeilen = is_array($roh)
            ? $roh
            : (preg_split('/\r\n|\r|\n/', (string) $roh) ?: []);

        // LEERE ZEILEN BLEIBEN STEHEN. Sie sind kein Versehen: der
        // Knopf "Neue Nachricht" legt genau so eine an, damit eine
        // leere Eingabe erscheint, in die man tippen kann. Wuerden sie
        // hier wegfallen, waere der Knopf wirkungslos - die Zeile
        // verschwaende beim Speichern sofort wieder.
        //
        // Fuer den Betrieb fallen sie ueber activeMessages() weg.
        $nachrichten = [];
        foreach ($zeilen as $zeile) {
            $nachrichten[] = self::cut(trim((string) $zeile), self::MAX_MESSAGE);

            if (count($nachrichten) >= self::MAX_MESSAGES) {
                break;
            }
        }

        return $nachrichten;
    }

    /**
     * Die Nachrichten, die wirklich gepostet werden.
     *
     * @param array<string, mixed> $timer
     * @return list<string>
     */
    public static function activeMessages(array $timer): array
    {
        $nachrichten = is_array($timer['messages'] ?? null) ? $timer['messages'] : [];

        return array_values(array_filter(
            array_map('strval', $nachrichten),
            static fn (string $text): bool => trim($text) !== ''
        ));
    }

    /**
     * Eine leere Nachrichtenzeile anhaengen.
     *
     * Fuer den Knopf "Neue Nachricht": die Zeile erscheint leer im
     * Formular, und getippt wird beim naechsten Speichern.
     *
     * @param list<string> $nachrichten
     * @return list<string>
     */
    public static function withEmptyMessage(array $nachrichten): array
    {
        if (count($nachrichten) >= self::MAX_MESSAGES) {
            return $nachrichten;
        }

        $nachrichten[] = '';

        return $nachrichten;
    }

    /**
     * Eine Nachrichtenzeile herausnehmen.
     *
     * Die letzte bleibt stehen: ein Timer ohne Nachricht kann nichts
     * tun, und ein Formular, das sich selbst unbrauchbar macht, waere
     * eine Falle. Die Oberflaeche bietet das Loeschen darum erst ab
     * zwei Zeilen an - das hier ist das Netz darunter.
     *
     * @param list<string> $nachrichten
     * @return list<string>
     */
    public static function withoutMessage(array $nachrichten, int $stelle): array
    {
        if (count($nachrichten) <= 1) {
            return $nachrichten;
        }

        unset($nachrichten[$stelle]);

        return array_values($nachrichten);
    }

    /**
     * Die Nachricht an dieser Stelle der Reihe.
     *
     * Der Zaehler laeuft immer weiter und wird hier umgerechnet - so
     * muss beim Loeschen einer Nachricht nichts nachgezogen werden.
     *
     * @param array<string, mixed> $timer
     */
    public static function messageAt(array $timer, int $index): string
    {
        // Ueber die nicht leeren: eine leere Platzhalterzeile wuerde
        // sonst gelegentlich einen Durchgang verschlucken, und niemand
        // verstuende, warum der Timer einmal ausgesetzt hat.
        $nachrichten = self::activeMessages($timer);
        if ($nachrichten === []) {
            return '';
        }

        $anzahl = count($nachrichten);

        return (string) ($nachrichten[(($index % $anzahl) + $anzahl) % $anzahl] ?? '');
    }

    /**
     * Der Befehlsname eines Timers, oder leer.
     *
     * Derselbe Zuschnitt wie bei den Chatbefehlen: Kleinbuchstaben,
     * Ziffern, Bindestrich, Unterstrich. "7dso" geht, "Neuer Timer"
     * nicht.
     */
    public static function commandName(string $titel): string
    {
        $name = strtolower(trim($titel));

        return preg_match('/^[a-z0-9_-]+$/', $name) === 1 ? $name : '';
    }

    /**
     * Alle Timer, die als Befehl erlaubt sind: Name => Timer.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function commands(App $app): array
    {
        $befehle = [];

        foreach (self::all($app) as $timer) {
            if (empty($timer['allow_as_command'])) {
                continue;
            }

            $name = self::commandName((string) $timer['title']);
            if ($name !== '') {
                $befehle[$name] = $timer;
            }
        }

        return $befehle;
    }

    // -----------------------------------------------------------------
    //  Bedingungen
    // -----------------------------------------------------------------

    /**
     * Passt der Stream-Titel zu den Stichwoertern?
     *
     * Leer heisst "egal". Sonst genuegt EIN Stichwort, das im Titel
     * vorkommt - nicht alle. Ein Timer fuer "Farming" soll auch bei
     * "Farming & Chill" greifen.
     */
    public static function titleMatches(string $stichwoerter, string $streamTitel): bool
    {
        $stichwoerter = trim($stichwoerter);
        if ($stichwoerter === '') {
            return true;
        }

        $titel = self::lower($streamTitel);

        foreach (explode(',', $stichwoerter) as $wort) {
            $wort = self::lower(trim($wort));
            if ($wort !== '' && str_contains($titel, $wort)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Passt die Kategorie?
     *
     * Anders als beim Titel exakt - nur Gross- und Kleinschreibung ist
     * egal. "Minecraft" darf nicht auf "Minecraft Dungeons" passen.
     */
    public static function gameMatches(string $erwartet, string $aktuell): bool
    {
        $erwartet = trim($erwartet);

        return $erwartet === '' || strcasecmp($erwartet, trim($aktuell)) === 0;
    }

    /**
     * Ist dieser Timer jetzt dran?
     *
     * @param array<string, mixed> $timer
     * @param array{lines: int, last_posted_at: int, message_index: int} $stand
     * @param array{live: bool, started_at: int, title: string, game: string} $stream
     */
    public static function isDue(array $timer, array $stand, array $stream, int $jetzt): bool
    {
        if (empty($timer['enabled']) || !$stream['live']) {
            return false;
        }

        if (!self::titleMatches((string) $timer['title_keywords'], $stream['title'])) {
            return false;
        }

        if (!self::gameMatches((string) $timer['game'], $stream['game'])) {
            return false;
        }

        if ($stand['lines'] < (int) $timer['min_lines']) {
            return false;
        }

        return $jetzt - self::reference($stand, $stream) >= self::intervalSeconds($timer);
    }

    /**
     * Ab wann zaehlt das Intervall?
     *
     * Der spaetere von beiden: letzter Post und Streamstart. Ohne den
     * Streamstart feuerte nach einer Pause von zwei Tagen jeder Timer
     * in der ersten Minute des naechsten Streams - genau dann, wenn
     * noch niemand da ist.
     *
     * @param array{lines: int, last_posted_at: int, message_index: int} $stand
     * @param array{live: bool, started_at: int, title: string, game: string} $stream
     */
    public static function reference(array $stand, array $stream): int
    {
        return max($stand['last_posted_at'], $stream['started_at']);
    }

    /**
     * @param array<string, mixed> $timer
     */
    public static function intervalSeconds(array $timer): int
    {
        return max(self::INTERVAL_MIN, (int) $timer['interval_minutes']) * 60;
    }

    /**
     * Wie weit ist dieser Timer - fuer die zwei Balken in der
     * Oberflaeche.
     *
     * @param array<string, mixed> $timer
     * @param array{lines: int, last_posted_at: int, message_index: int} $stand
     * @param array{live: bool, started_at: int, title: string, game: string} $stream
     * @return array{seconds: int, seconds_needed: int, time_ratio: float, lines: int, lines_needed: int, lines_ratio: float}
     */
    public static function progress(array $timer, array $stand, array $stream, int $jetzt): array
    {
        $noetig = self::intervalSeconds($timer);
        $ab = self::reference($stand, $stream);

        // Laeuft kein Stream, steht die Uhr - nicht nur der Timer.
        $vergangen = (!$stream['live'] || $ab <= 0) ? 0 : max(0, $jetzt - $ab);

        $zeilenNoetig = max(0, (int) $timer['min_lines']);

        return [
            'seconds'        => $vergangen,
            'seconds_needed' => $noetig,
            'time_ratio'     => $noetig > 0 ? min(1.0, $vergangen / $noetig) : 1.0,
            'lines'          => max(0, $stand['lines']),
            'lines_needed'   => $zeilenNoetig,
            'lines_ratio'    => $zeilenNoetig > 0
                ? min(1.0, max(0, $stand['lines']) / $zeilenNoetig)
                : 1.0,
        ];
    }

    // -----------------------------------------------------------------
    //  Kleinteile
    // -----------------------------------------------------------------

    /**
     * Eine Kennung, die als HTML-id und in einer Adresse taugt.
     */
    private static function normalizeId(string $id): string
    {
        $id = strtolower(trim($id));

        return preg_match('/^[a-f0-9]{12}$/', $id) === 1 ? $id : bin2hex(random_bytes(6));
    }

    private static function cut(string $text, int $laenge): string
    {
        return function_exists('mb_substr')
            ? mb_substr($text, 0, $laenge)
            : substr($text, 0, $laenge);
    }

    private static function lower(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }
}
