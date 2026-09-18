<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Die Kanalpunkt-Belohnungen, wie dieses System sie kennt.
 *
 * Gespeichert wird im Scope "plugin:channel-points": die Belohnungen
 * unter "rewards", der Stream-Zustand unter "stream". Den Scope
 * loescht der Kern beim Entfernen des Plugins mit.
 *
 * Zwei Dinge muss man ueber Twitch wissen, sonst ergibt der Aufbau
 * hier keinen Sinn:
 *
 * 1. LESEN darf jede App alle Belohnungen. AENDERN darf sie nur die,
 *    die sie selbst angelegt hat - Twitch prueft die Client-ID. Was
 *    im Creator-Dashboard entstanden ist, gehoert dem Dashboard, und
 *    ein PATCH darauf gibt 403. Darum traegt jede Belohnung hier ein
 *    "manageable": nur diese lassen sich schalten.
 *
 * 2. Ein SYMBOL laesst sich ueber die Schnittstelle weder setzen noch
 *    aendern - es gibt schlicht kein Feld dafuer. Eine von uns
 *    angelegte Belohnung traegt darum immer das Vorgabe-Symbol.
 *
 * Die Belohnung lebt bei Twitch, nicht hier. Was hier steht, ist eine
 * Kopie - plus die Bedingungen, die Twitch nicht kennt.
 */
final class Rewards
{
    public const SLUG = 'channel-points';

    /**
     * Ohne diese Freigabe geht gar nichts.
     *
     * Sie deckt Lesen und Schreiben ab. Ein reines
     * "channel:read:redemptions" wuerde fuer die Liste genuegen, aber
     * nicht fuer den Zweck des Plugins - und zwei Freigaben fuer eine
     * Seite waeren nur eine Fehlerquelle mehr.
     */
    public const SCOPE = 'channel:manage:redemptions';

    /** Grenzen, die Twitch setzt. */
    public const MAX_TITLE = 45;
    public const MAX_PROMPT = 200;
    public const MIN_COST = 1;

    /** Bis zu sieben Tage - so steht es auch im Dialog von Twitch. */
    public const MAX_COOLDOWN = 604800;

    /** Twitch liefert hoechstens 50 eigene Belohnungen je Kanal. */
    public const MAX_REWARDS = 50;

    /** Die Einheiten der Abklingzeit, wie im Dialog von Twitch. */
    public const UNITS = [
        'seconds' => 1,
        'minutes' => 60,
        'hours'   => 3600,
        'days'    => 86400,
    ];

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    /**
     * Steuert nur das automatische Schalten.
     *
     * Aus heisst: die Seite bleibt bedienbar, Belohnungen lassen sich
     * weiter von Hand anlegen und aendern - nur die Bedingungen
     * greifen nicht mehr. Wer den Schalter umlegt, will in der Regel
     * "heute mal nicht automatisch", nicht "alles weg".
     */
    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    // -----------------------------------------------------------------
    //  Die Liste
    // -----------------------------------------------------------------

    /**
     * Alle bekannten Belohnungen.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(App $app): array
    {
        $gespeichert = $app->settings->get('rewards', null, self::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $liste = [];
        foreach ($gespeichert as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }

            $sauber = self::normalize($eintrag);
            if ($sauber !== null) {
                $liste[] = $sauber;
            }
        }

        return $liste;
    }

    /**
     * @param list<array<string, mixed>> $liste
     */
    public static function store(App $app, array $liste): void
    {
        $app->settings->set('rewards', array_values($liste), self::scope());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(App $app, string $id): ?array
    {
        foreach (self::all($app) as $eintrag) {
            if ((string) $eintrag['id'] === $id && $id !== '') {
                return $eintrag;
            }
        }

        return null;
    }

    /**
     * Eine Belohnung ablegen oder ersetzen - erkannt an der Twitch-ID.
     *
     * @param array<string, mixed> $belohnung
     */
    public static function put(App $app, array $belohnung): void
    {
        $sauber = self::normalize($belohnung);
        if ($sauber === null) {
            return;
        }

        $liste = self::all($app);

        foreach ($liste as $stelle => $vorhanden) {
            if ((string) $vorhanden['id'] === (string) $sauber['id']) {
                $liste[$stelle] = $sauber;
                self::store($app, $liste);

                return;
            }
        }

        $liste[] = $sauber;
        self::store($app, $liste);
    }

    public static function forget(App $app, string $id): bool
    {
        $alle = self::all($app);

        $uebrig = array_values(array_filter(
            $alle,
            static fn (array $eintrag): bool => (string) $eintrag['id'] !== $id
        ));

        if (count($uebrig) === count($alle)) {
            return false;
        }

        self::store($app, $uebrig);

        return true;
    }

    // -----------------------------------------------------------------
    //  Pruefen und Zurechtruecken
    // -----------------------------------------------------------------

    /**
     * Eine Belohnung, wie sie gespeichert wird - oder null, wenn sie
     * unbrauchbar ist.
     *
     * Unbrauchbar heisst: ohne Namen. Twitch verlangt ihn, und ohne
     * ihn stuende in der Liste eine leere Zeile, die sich nicht
     * zuordnen laesst.
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

        $einheit = (string) ($eingabe['cooldown_unit'] ?? 'minutes');
        if (!isset(self::UNITS[$einheit])) {
            $einheit = 'minutes';
        }

        return [
            'id'            => trim((string) ($eingabe['id'] ?? '')),
            'title'         => self::cut($titel, self::MAX_TITLE),
            'prompt'        => self::cut(trim((string) ($eingabe['prompt'] ?? '')), self::MAX_PROMPT),
            'cost'          => max(self::MIN_COST, (int) ($eingabe['cost'] ?? self::MIN_COST)),
            'user_input'    => !empty($eingabe['user_input']),
            'color'         => self::normalizeColor((string) ($eingabe['color'] ?? '')),
            'skip_queue'    => !empty($eingabe['skip_queue']),

            // Der Sammelschalter aus dem Dialog. Steht er aus, sind
            // alle drei Begrenzungen aus - egal, was in den Feldern
            // steht. So bleiben die Zahlen beim Abschalten erhalten.
            'limits'        => !empty($eingabe['limits']),
            'cooldown'      => self::clamp((int) ($eingabe['cooldown'] ?? 0), 0, self::MAX_COOLDOWN),
            'cooldown_unit' => $einheit,
            'per_stream'    => max(0, (int) ($eingabe['per_stream'] ?? 0)),
            'per_user'      => max(0, (int) ($eingabe['per_user'] ?? 0)),

            // Der Stand, wie wir ihn zuletzt gesetzt oder gelesen
            // haben.
            'is_enabled'    => !empty($eingabe['is_enabled']),

            // Haben WIR sie angelegt? Nur dann laesst sie sich
            // schalten - siehe Klassenkommentar.
            'manageable'    => !empty($eingabe['manageable']),

            // Die Bedingungen. Alle vier sind Listen, mit Komma
            // getrennt.
            'title_on'      => self::cut(trim((string) ($eingabe['title_on'] ?? '')), 200),
            'game_on'       => self::cut(trim((string) ($eingabe['game_on'] ?? '')), 200),
            'title_off'     => self::cut(trim((string) ($eingabe['title_off'] ?? '')), 200),
            'game_off'      => self::cut(trim((string) ($eingabe['game_off'] ?? '')), 200),
        ];
    }

    /**
     * Die Abklingzeit in Sekunden, wie Twitch sie will.
     *
     * Im Dialog steht eine Zahl und daneben eine Einheit - "30" und
     * "Minuten". Twitch kennt nur Sekunden.
     *
     * @param array<string, mixed> $belohnung
     */
    public static function cooldownSeconds(array $belohnung): int
    {
        $einheit = (string) ($belohnung['cooldown_unit'] ?? 'minutes');
        $faktor = self::UNITS[$einheit] ?? 60;

        $sekunden = (int) ($belohnung['cooldown'] ?? 0) * $faktor;

        return self::clamp($sekunden, 0, self::MAX_COOLDOWN);
    }

    /**
     * Eine Farbe als #RRGGBB - oder leer.
     *
     * Leer heisst bei Twitch "nimm die Vorgabe". Eine halb getippte
     * Farbe waere schlechter als keine.
     */
    public static function normalizeColor(string $roh): string
    {
        $roh = trim($roh);
        if ($roh === '') {
            return '';
        }

        if ($roh[0] !== '#') {
            $roh = '#' . $roh;
        }

        // Die Kurzform #abc schreibt Twitch nicht, also ausschreiben.
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $roh, $treffer) === 1) {
            $roh = '#' . $treffer[1][0] . $treffer[1][0]
                . $treffer[1][1] . $treffer[1][1]
                . $treffer[1][2] . $treffer[1][2];
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $roh) === 1 ? strtoupper($roh) : '';
    }

    /**
     * Eine Kennung fuer eine Belohnung, die es bei Twitch (noch)
     * nicht gibt.
     *
     * Zwei Faelle: eine gerade angelegte, bei der Twitch nicht
     * antwortete, und eine, die beim Uebernehmen zwischen Loeschen
     * und Neuanlegen steckenblieb. Beide sollen nicht verloren gehen,
     * brauchen aber eine Kennung, die sich von einer echten
     * unterscheidet.
     *
     * Twitch vergibt UUIDs - ein Praefix mit Doppelpunkt kann dort
     * nicht vorkommen.
     */
    public static function localId(): string
    {
        return 'local:' . bin2hex(random_bytes(8));
    }

    /** Kennt Twitch diese Belohnung? */
    public static function isRemote(string $id): bool
    {
        return $id !== '' && !str_starts_with($id, 'local:');
    }

    /** Kuerzen - mit Rueckfall, falls mbstring fehlt. */
    public static function cut(string $text, int $laenge): string
    {
        return function_exists('mb_substr')
            ? mb_substr($text, 0, $laenge)
            : substr($text, 0, $laenge);
    }

    private static function clamp(int $wert, int $min, int $max): int
    {
        return max($min, min($max, $wert));
    }

    // -----------------------------------------------------------------
    //  Uebersetzung in die Sprache von Twitch
    // -----------------------------------------------------------------

    /**
     * Der Rumpf fuer Anlegen und Aendern.
     *
     * Steht der Sammelschalter aus, gehen alle drei Begrenzungen als
     * "aus" mit - aber die Zahlen bleiben hier stehen. Twitch nimmt
     * eine 0 bei max_per_stream nicht an (Minimum 1), also wird das
     * Feld dann gar nicht erst mitgeschickt.
     *
     * @param array<string, mixed> $belohnung
     * @return array<string, mixed>
     */
    public static function payload(array $belohnung): array
    {
        $abkling = self::cooldownSeconds($belohnung);
        $limits = !empty($belohnung['limits']);

        $proStream = $limits ? max(0, (int) ($belohnung['per_stream'] ?? 0)) : 0;
        $proNutzer = $limits ? max(0, (int) ($belohnung['per_user'] ?? 0)) : 0;
        $abkling = $limits ? $abkling : 0;

        $rumpf = [
            'title'                                 => (string) $belohnung['title'],
            'cost'                                  => max(self::MIN_COST, (int) $belohnung['cost']),
            'prompt'                                => (string) $belohnung['prompt'],
            'is_enabled'                            => !empty($belohnung['is_enabled']),
            'is_user_input_required'                => !empty($belohnung['user_input']),
            'should_redemptions_skip_request_queue' => !empty($belohnung['skip_queue']),
            'is_global_cooldown_enabled'            => $abkling > 0,
            'is_max_per_stream_enabled'             => $proStream > 0,
            'is_max_per_user_per_stream_enabled'    => $proNutzer > 0,
        ];

        // Nur mitschicken, was Twitch auch annimmt: die Minima sind 1.
        if ($abkling > 0) {
            $rumpf['global_cooldown_seconds'] = $abkling;
        }

        if ($proStream > 0) {
            $rumpf['max_per_stream'] = $proStream;
        }

        if ($proNutzer > 0) {
            $rumpf['max_per_user_per_stream'] = $proNutzer;
        }

        // Eine leere Farbe heisst "Vorgabe" - das Feld darf dann nicht
        // mit, sonst lehnt Twitch den ganzen Aufruf ab.
        $farbe = self::normalizeColor((string) ($belohnung['color'] ?? ''));
        if ($farbe !== '') {
            $rumpf['background_color'] = $farbe;
        }

        return $rumpf;
    }

    /**
     * Eine Antwort von Twitch in unsere Form bringen.
     *
     * Die Bedingungen kennt Twitch nicht - wer schon hier steht,
     * behaelt seine. Das ist der Grund, warum diese Funktion die alte
     * Fassung mitnimmt: sonst loescht jedes Laden die Einstellungen,
     * um derentwillen das Plugin ueberhaupt existiert.
     *
     * @param array<string, mixed> $roh    wie Twitch es liefert
     * @param array<string, mixed> $bisher unsere bisherige Fassung
     * @return array<string, mixed>|null
     */
    public static function fromTwitch(array $roh, array $bisher = [], bool $manageable = false): ?array
    {
        $abkling = (int) ($roh['global_cooldown_setting']['global_cooldown_seconds'] ?? 0);
        $anAbkling = !empty($roh['global_cooldown_setting']['is_enabled']);

        $proStream = (int) ($roh['max_per_stream_setting']['max_per_stream'] ?? 0);
        $anStream = !empty($roh['max_per_stream_setting']['is_enabled']);

        $proNutzer = (int) ($roh['max_per_user_per_stream_setting']['max_per_user_per_stream'] ?? 0);
        $anNutzer = !empty($roh['max_per_user_per_stream_setting']['is_enabled']);

        [$zahl, $einheit] = self::splitCooldown($anAbkling ? $abkling : 0);

        return self::normalize([
            'id'            => (string) ($roh['id'] ?? ''),
            'title'         => (string) ($roh['title'] ?? ''),
            'prompt'        => (string) ($roh['prompt'] ?? ''),
            'cost'          => (int) ($roh['cost'] ?? self::MIN_COST),
            'user_input'    => !empty($roh['is_user_input_required']),
            'color'         => (string) ($roh['background_color'] ?? ''),
            'skip_queue'    => !empty($roh['should_redemptions_skip_request_queue']),
            'limits'        => $anAbkling || $anStream || $anNutzer,
            'cooldown'      => $zahl,
            'cooldown_unit' => $einheit,
            'per_stream'    => $anStream ? $proStream : 0,
            'per_user'      => $anNutzer ? $proNutzer : 0,
            'is_enabled'    => !empty($roh['is_enabled']),
            'manageable'    => $manageable,

            // Was Twitch nicht kennt, bleibt unseres.
            'title_on'      => (string) ($bisher['title_on'] ?? ''),
            'game_on'       => (string) ($bisher['game_on'] ?? ''),
            'title_off'     => (string) ($bisher['title_off'] ?? ''),
            'game_off'      => (string) ($bisher['game_off'] ?? ''),
        ]);
    }

    /**
     * Sekunden in Zahl und Einheit zerlegen - die groesste, die
     * glatt aufgeht.
     *
     * 1800 wird zu "30 Minuten" und nicht zu "1800 Sekunden": im
     * Dialog von Twitch stand es auch so, und wer es dort auf 30
     * Minuten gestellt hat, will hier keine vierstellige Zahl sehen.
     *
     * @return array{0: int, 1: string}
     */
    public static function splitCooldown(int $sekunden): array
    {
        if ($sekunden <= 0) {
            return [0, 'minutes'];
        }

        foreach (['days', 'hours', 'minutes'] as $einheit) {
            $faktor = self::UNITS[$einheit];

            if ($sekunden % $faktor === 0) {
                return [intdiv($sekunden, $faktor), $einheit];
            }
        }

        return [$sekunden, 'seconds'];
    }
}
