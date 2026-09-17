<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Subathon;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Subathon - die Zeit und was sie verlaengert
 * ===================================================================
 *
 * Uebernommen aus dem Windows-Programm (legacy/subathon-tool): ein
 * Stream, der laenger wird, wenn jemand abonniert, Bits schickt oder
 * spendet - bis zu einer Obergrenze.
 *
 * Der wichtigste Unterschied zum Original ist, dass hier NICHTS im
 * Sekundentakt laeuft.
 *
 * Dort zaehlte ein Timer jede Sekunde hoch und schrieb die Pausenzeit
 * in die config.json - eine Datei, jede Sekunde, damit ein Wert
 * stimmt, den man auch ausrechnen kann. Hier stehen drei Zahlen in den
 * Einstellungen (Start, Dauer, Pause), und woraus sich alles andere
 * ergibt, ergibt sich daraus:
 *
 *     Ende = Start + Dauer + Pause
 *
 * Die laufende Anzeige rechnet der Browser, und der kann das.
 */
final class Subathon
{
    public const SLUG = 'subathon';

    /** Vorgaben wie im Programm (config.cs). */
    public const DEFAULT_TIMER = 28800;         // 8 Stunden
    public const DEFAULT_MAX = 172800;          // 48 Stunden
    public const DEFAULT_SECONDS_PER_SUB = 3600;
    public const DEFAULT_BITS_PER_SUB = 300;
    public const DEFAULT_CENT_PER_SUB = 300;

    /**
     * Die Preise der Abostufen, wie Twitch sie berechnet.
     *
     * Das Programm rechnete tier1price / 4.99 * 7.99 - also den Preis
     * je Stufe in Vielfachen des ersten. Dieselbe Rechnung, nur
     * einmal aufgeschrieben.
     */
    public const TIER_PRICE = ['1000' => 4.99, '2000' => 7.99, '3000' => 19.99];

    public static function scope(): string
    {
        return 'plugin:' . self::SLUG;
    }

    // -----------------------------------------------------------------
    //  Die Einstellungen
    // -----------------------------------------------------------------

    public static function timer(App $app): int
    {
        return max(0, $app->settings->int('timer', self::DEFAULT_TIMER, self::scope()));
    }

    public static function maxDuration(App $app): int
    {
        return max(0, $app->settings->int('max_duration', self::DEFAULT_MAX, self::scope()));
    }

    /** Der Startzeitpunkt als Unixzeit - 0 heisst "noch keiner". */
    public static function start(App $app): int
    {
        return max(0, $app->settings->int('start', 0, self::scope()));
    }

    /** Gesammelte Pausenzeit in Sekunden. */
    public static function breakTime(App $app): int
    {
        return max(0, $app->settings->int('break_time', 0, self::scope()));
    }

    public static function isPaused(App $app): bool
    {
        return $app->settings->bool('pause', false, self::scope());
    }

    /** Seit wann pausiert wird - 0 heisst "wird nicht pausiert". */
    public static function pausedAt(App $app): int
    {
        return max(0, $app->settings->int('paused_at', 0, self::scope()));
    }

    public static function owner(App $app): string
    {
        return $app->settings->string('owner', '', self::scope());
    }

    public static function secondsPerSub(App $app): int
    {
        return max(1, $app->settings->int('seconds_per_sub', self::DEFAULT_SECONDS_PER_SUB, self::scope()));
    }

    public static function bitsPerSub(App $app): int
    {
        return max(1, $app->settings->int('bits_per_sub', self::DEFAULT_BITS_PER_SUB, self::scope()));
    }

    public static function centPerSub(App $app): int
    {
        return max(1, $app->settings->int('cent_per_sub', self::DEFAULT_CENT_PER_SUB, self::scope()));
    }

    /**
     * Soll der Reiter "Manuelles Buchen" da sein?
     *
     * Aus, solange niemand ihn anschaltet. Abos, Bits und Spenden
     * buchen sich von selbst; von Hand braucht man ihn nur, wenn
     * etwas auf einem Weg ankommt, den dieses System nicht sieht -
     * eine Ueberweisung, ein Geschenk im Chat.
     *
     * Ein Reiter, den man nie braucht, ist auch einer, in den man
     * sich vertippt: fuenf Knoepfe, die Zeit verschenken, direkt
     * neben den Einstellungen.
     */
    public static function showManual(App $app): bool
    {
        return $app->settings->bool('show_manual', false, self::scope());
    }

    /**
     * Die Nachrichten der Laufschrift, eine je Zeile.
     *
     * @return list<string>
     */
    public static function messages(App $app): array
    {
        $roh = json_decode($app->settings->string('messages', '[]', self::scope()), true);

        if (!is_array($roh)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $zeile): string => (string) $zeile, $roh));
    }

    /** @param list<string> $zeilen */
    public static function setMessages(App $app, array $zeilen): void
    {
        $app->settings->set('messages', json_encode(array_values($zeilen), JSON_UNESCAPED_UNICODE), self::scope());
    }

    // -----------------------------------------------------------------
    //  Die Farben des Overlays
    // -----------------------------------------------------------------

    /**
     * Sechs Farben, wie im Programm (config.RowNames): Hintergrund und
     * Schrift je Abschnitt.
     *
     * Die Vorgaben sind die von dort - lightgreen, #f79191, skyblue -
     * und nicht die des Systems: das Overlay liegt ueber dem Spiel und
     * hat mit der Verwaltung nichts zu tun.
     */
    public const COLORS = [
        'done'          => 'lightgreen',
        'done_text'     => 'Black',
        'todo'          => '#f79191',
        'todo_text'     => 'Black',
        'possible'      => 'skyblue',
        'possible_text' => 'Black',
    ];

    /** @return array<string, string> */
    public static function colors(App $app): array
    {
        $farben = [];

        foreach (self::COLORS as $name => $vorgabe) {
            $farben[$name] = self::normalizeColor(
                $app->settings->string('color_' . $name, '', self::scope()),
                $vorgabe
            );
        }

        return $farben;
    }

    /**
     * Eine Farbangabe, die im Stylesheet nichts anrichten kann.
     *
     * Sie landet in einem style-Attribut. Ein Semikolon oder eine
     * Klammer darin waere eine zweite Angabe - darum nur, was wie eine
     * Farbe aussieht: #abc, #aabbcc oder ein Name aus Buchstaben.
     */
    public static function normalizeColor(string $wert, string $vorgabe): string
    {
        $wert = trim($wert);

        if (preg_match('/^#[0-9a-fA-F]{3}$|^#[0-9a-fA-F]{6}$|^[a-zA-Z]{3,24}$/', $wert) === 1) {
            return $wert;
        }

        return $vorgabe;
    }

    // -----------------------------------------------------------------
    //  Die Zeit
    // -----------------------------------------------------------------

    /**
     * Die Pausenzeit einschliesslich der laufenden Pause.
     *
     * Das Programm zaehlte sie jede Sekunde hoch und schrieb sie
     * weg. Hier steht der Anfang der Pause, und der Rest ist eine
     * Subtraktion - dieselbe Zahl, ohne eine Schreiboperation je
     * Sekunde.
     */
    public static function effectiveBreak(int $break, bool $pause, int $pausedAt, int $jetzt): int
    {
        if (!$pause || $pausedAt <= 0) {
            return max(0, $break);
        }

        return max(0, $break) + max(0, $jetzt - $pausedAt);
    }

    /** Wann Schluss ist: Start + Dauer + Pause. */
    public static function endAt(int $start, int $timer, int $break): int
    {
        return $start + $timer + $break;
    }

    /**
     * Der Zustand, wie ihn die Uebersicht und das Overlay brauchen.
     *
     * Vier Faelle, genau wie im Programm (Form1.Tick):
     *
     *   idle     kein Start eingetragen, oder er liegt noch vor uns
     *   running  laeuft
     *   paused   pausiert
     *   ended    die Zeit ist abgelaufen
     */
    public static function statusOf(int $start, int $ende, bool $pause, int $jetzt): string
    {
        if ($start <= 0) {
            return 'idle';
        }

        if ($jetzt > $ende) {
            return 'ended';
        }

        if ($pause) {
            return 'paused';
        }

        return $jetzt < $start ? 'idle' : 'running';
    }

    /**
     * Die drei Balken des Overlays - 1:1 aus OverlayServer.ServeCalc.
     *
     *   done      wie lange schon gestreamt wird (ohne Pausen)
     *   todo      wie lange noch
     *   possible  wie viel sich noch dazukaufen laesst
     *   total     die Obergrenze; -1 heisst "keine"
     *
     * Vor dem Start gibt es nichts anzuzeigen: dort steht im Original
     * "[]", und das Overlay blendet sich aus.
     *
     * @return array{done: int, todo: int, possible: int, total: int}|null
     */
    public static function calc(int $start, int $timer, int $max, int $break, int $jetzt): ?array
    {
        if ($start <= 0 || $jetzt < $start) {
            return null;
        }

        $ende = self::endAt($start, $timer, $break);

        $done = $jetzt - $start - $break;
        $todo = $ende - $jetzt;

        if ($max <= 0) {
            return ['done' => $done, 'todo' => $todo, 'possible' => -1, 'total' => -1];
        }

        $maxEnde = $start + $max + $break;

        return [
            'done'     => $done,
            'todo'     => $todo,
            'possible' => $maxEnde - $ende,
            'total'    => $max,
        ];
    }

    // -----------------------------------------------------------------
    //  Was Zeit bringt
    // -----------------------------------------------------------------

    /**
     * Sekunden fuer ein Abo einer Stufe.
     *
     * Stufe 2 und 3 kosten mehr und bringen entsprechend mehr - die
     * Rechnung ist die des Programms, nur ohne die Kommastelle: dort
     * wurde auf int geschnitten, hier auch.
     */
    public static function secondsForSub(int $secondsPerSub, string $tier): int
    {
        $preis = self::TIER_PRICE[$tier] ?? self::TIER_PRICE['1000'];

        return (int) (($secondsPerSub / self::TIER_PRICE['1000']) * $preis);
    }

    public static function secondsForBits(int $secondsPerSub, int $bitsPerSub, int $bits): int
    {
        return (int) ($bits * ($secondsPerSub / max(1, $bitsPerSub)));
    }

    /** Spenden rechnen in CENT - so wie das Programm es tut. */
    public static function secondsForCents(int $secondsPerSub, int $centPerSub, int $cents): int
    {
        return (int) ($cents * ($secondsPerSub / max(1, $centPerSub)));
    }

    /**
     * Die neue Dauer nach einer Gutschrift, auf die Obergrenze
     * gedeckelt.
     *
     * Eine Obergrenze von 0 heisst "keine" - dann laeuft es weiter.
     */
    public static function capped(int $timer, int $plus, int $max): int
    {
        $neu = $timer + $plus;

        if ($max > 0 && $neu > $max) {
            return $max;
        }

        return $neu;
    }
}
