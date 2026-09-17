<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Subathon;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Was Zeit gutschreibt
 * ===================================================================
 *
 * Eine Stelle fuer alles, was den Zaehler bewegt - egal ob es von
 * Twitch kommt, von einer Spendenseite oder von Hand aus dem Reiter
 * "Manuelles Buchen".
 *
 * Im Programm stand dieselbe Rechnung viermal: in Subscription(), in
 * Cheer(), in CheckSE() und noch einmal in den vier Knoepfen des
 * Reiters. Beim Lesen faellt auf, dass sie sich unterscheiden - die
 * Bits-Buchung von Hand deckelt auf max_duration auch dann, wenn die
 * Obergrenze 0 ist, und dann steht der Zaehler auf 0. Hier ist es
 * eine Funktion, und sie hat diesen Fehler nicht.
 */
final class Booking
{
    /**
     * Gutschreiben - und zwar nur, wenn gerade gebucht werden darf.
     *
     * Die Bedingung ist die des Programms: es laeuft, es pausiert
     * nicht, und wir sind zwischen Start und Ende. Was ausserhalb
     * ankommt, wird NICHT gutgeschrieben - aber auch nicht
     * verschwiegen: es steht im Verlauf mit 0 Sekunden.
     *
     * @param bool $happyHour ob die Happy Hour hier gilt (nur Spenden)
     * @return int die gutgeschriebenen Sekunden
     */
    public static function credit(
        App $app,
        string $kind,
        string $who,
        string $amount,
        int $sekunden,
        bool $happyHour = false
    ): int {
        $jetzt = time();

        if (!self::isOpen($app, $jetzt)) {
            Log::add($app, $kind, $who, $amount, 0);

            return 0;
        }

        $extra = 0;

        if ($happyHour) {
            $ergebnis = HappyHour::apply(
                $sekunden,
                HappyHour::typeAt(HappyHour::all($app), (int) date('G', $jetzt))
            );

            $sekunden = $ergebnis['seconds'];
            $extra = $ergebnis['extra'];
        }

        if ($extra > 0) {
            $app->settings->set('max_duration', Subathon::maxDuration($app) + $extra, Subathon::scope());
        }

        $app->settings->set(
            'timer',
            Subathon::capped(Subathon::timer($app), $sekunden, Subathon::maxDuration($app)),
            Subathon::scope()
        );

        Log::add($app, $kind, $who, $amount, $sekunden);

        return $sekunden;
    }

    /**
     * Laeuft der Subathon gerade, und zwar wirklich?
     *
     * Vier Bedingungen, alle aus dem Programm (CheckSE): gestartet,
     * nicht pausiert, Startzeit erreicht, Ende noch nicht.
     */
    public static function isOpen(App $app, int $jetzt): bool
    {
        $start = Subathon::start($app);

        if ($start <= 0 || $jetzt < $start || Subathon::isPaused($app)) {
            return false;
        }

        $pause = Subathon::effectiveBreak(
            Subathon::breakTime($app),
            Subathon::isPaused($app),
            Subathon::pausedAt($app),
            $jetzt
        );

        return $jetzt < Subathon::endAt($start, Subathon::timer($app), $pause);
    }

    // -----------------------------------------------------------------
    //  Die vier Wege hinein
    // -----------------------------------------------------------------

    /** Ein Abo - eigenes oder geschenktes, mit Stufe und Anzahl. */
    public static function subscription(App $app, string $tier, string $who, bool $gift, int $anzahl = 1): int
    {
        $anzahl = max(1, $anzahl);
        $sekunden = Subathon::secondsForSub(
            Subathon::secondsPerSub($app),
            $tier,
            Subathon::tierPrices($app)
        ) * $anzahl;

        return self::credit($app, $gift ? 'gift' : 'sub', $who, self::tierName($tier), $sekunden);
    }

    public static function bits(App $app, int $bits, string $who): int
    {
        $bits = max(0, $bits);

        return self::credit(
            $app,
            'bits',
            $who,
            (string) $bits,
            Subathon::secondsForBits(Subathon::secondsPerSub($app), Subathon::bitsPerSub($app), $bits)
        );
    }

    /**
     * Eine Spende - der einzige Weg, fuer den die Happy Hour gilt.
     *
     * Gerechnet wird in Cent, wie im Programm: "Cent pro Sub" ist die
     * Einstellung, und Kommastellen in einer Multiplikation sind eine
     * Fehlerquelle mehr.
     */
    public static function donation(App $app, int $cents, string $who): int
    {
        $cents = max(0, $cents);

        return self::credit(
            $app,
            'donation',
            $who,
            number_format($cents / 100, 2, '.', ''),
            Subathon::secondsForCents(Subathon::secondsPerSub($app), Subathon::centPerSub($app), $cents),
            true
        );
    }

    /** Minuten von Hand - ohne Happy Hour, wie im Programm. */
    public static function minutes(App $app, int $minuten, string $who): int
    {
        return self::credit($app, 'manual', $who, (string) $minuten, max(0, $minuten) * 60);
    }

    /**
     * Der Name der Abostufe fuer den Verlauf.
     *
     * "Prime-", "Level2-", "Level3-" wie im Programm - dort stand es
     * so in der Logdatei.
     */
    public static function tierName(string $tier): string
    {
        switch ($tier) {
            case '1000':
                return 'Prime';

            case '2000':
                return 'Level2';

            case '3000':
                return 'Level3';
        }

        return $tier;
    }
}
