<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Subathon;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Der Verlauf
 * ===================================================================
 *
 * Wer wann wie viel Zeit gebracht hat. Im Programm war das eine Datei
 * namens "logs" mit Zeilen der Form
 *
 *     Name;bits;120;360;1740000000
 *
 * die beim Oeffnen des Reiters komplett gelesen und rueckwaerts
 * angezeigt wurde. Hier ist es eine Tabelle - aus demselben Grund wie
 * ueberall: zwei Schreiber auf einer Datei verlieren einen von beiden,
 * und ein Abo, das im selben Moment wie eine Spende kommt, ist kein
 * seltener Fall.
 */
final class Log
{
    public const TABLE = 'subathon_log';

    /** Die vier Arten, die das Programm kannte. */
    public const KINDS = ['sub', 'gift', 'bits', 'donation', 'manual'];

    /**
     * Einen Eintrag schreiben.
     *
     * $amount ist die Menge in der Einheit der Art: Bits, Cent,
     * Abostufe. $seconds ist, was es gebracht hat.
     */
    public static function add(
        App $app,
        string $kind,
        string $who,
        string $amount,
        int $seconds
    ): void {
        $app->db->run(
            'INSERT INTO ' . self::TABLE . ' (kind, who, amount, seconds) VALUES (:kind, :who, :amount, :seconds)',
            [
                'kind'    => in_array($kind, self::KINDS, true) ? $kind : 'manual',
                'who'     => mb_substr(trim($who), 0, 120),
                'amount'  => mb_substr(trim($amount), 0, 40),
                'seconds' => $seconds,
            ]
        );
    }

    /**
     * Die letzten Eintraege, neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(App $app, int $limit = 200): array
    {
        return $app->db->all(
            'SELECT kind, who, amount, seconds, created_at
               FROM ' . self::TABLE . '
              ORDER BY id DESC
              LIMIT ' . max(1, min(1000, $limit))
        );
    }

    public static function clear(App $app): void
    {
        $app->db->run('DELETE FROM ' . self::TABLE);
    }

    /**
     * Sekunden als Text: "1 Stunde, 20 Minuten und 5 Sekunden".
     *
     * 1:1 aus Form1.ReloadHistoryView - mit demselben "und" vor dem
     * letzten Teil. Was 0 ist, faellt weg; ist alles 0, bleibt "0
     * Sekunden" stehen, sonst stuende dort nichts.
     */
    public static function duration(int $sekunden): string
    {
        $sekunden = max(0, $sekunden);

        $h = intdiv($sekunden, 3600);
        $m = intdiv($sekunden % 3600, 60);
        $s = $sekunden % 60;

        $teile = [];

        if ($h > 0) {
            $teile[] = $h . ' ' . ($h > 1 ? translate('subathon.hours') : translate('subathon.hour'));
        }

        if ($m > 0) {
            $teile[] = $m . ' ' . ($m > 1 ? translate('subathon.minutes') : translate('subathon.minute'));
        }

        if ($s > 0 || $teile === []) {
            $teile[] = $s . ' ' . ($s === 1 ? translate('subathon.second') : translate('subathon.seconds'));
        }

        if (count($teile) === 1) {
            return $teile[0];
        }

        $letztes = array_pop($teile);

        return implode(', ', $teile) . ' ' . translate('subathon.and') . ' ' . $letztes;
    }
}
