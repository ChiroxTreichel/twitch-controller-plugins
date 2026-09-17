<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Subathon;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Die Platzhalter der Laufschrift
 * ===================================================================
 *
 * Die Nachrichten im Overlay duerfen rechnen: "{{Conf.MIN_PER_SUB}}
 * Minuten pro Abo" steht in der Einstellung, im Stream steht dort die
 * Zahl. 1:1 aus OverlayServer.replacePlaceholders.
 *
 * Eine Sache ist dabei anders, und sie ist ein Fehler von damals:
 * dort wurden {{Conf.SECONDS_PER_BIT}} und {{Conf.HAPPY_HOUR_START}}
 * mit dem Praefix "Conf." ersetzt, die Geschwister daneben aber ohne
 * ({{SECONDS_PER_CENT}}, {{HAPPY_HOUR_END}}). In der mitgelieferten
 * config.json stehen sie alle MIT Praefix - zwei davon blieben im
 * Stream also als geschweifte Klammern stehen.
 *
 * Hier gelten beide Schreibweisen. Wer die alten Texte uebernimmt,
 * sieht endlich, was dort stehen sollte.
 */
final class Texts
{
    /**
     * Der Name einer Happy-Hour-Art.
     *
     * Ausgeschrieben und nicht zusammengesetzt: ein Schluessel, der
     * aus einer Variablen entsteht, ist fuer bin/lang.php unsichtbar -
     * er faellt erst auf, wenn er in der Oberflaeche nackt dasteht.
     */
    public static function happyType(int $art): string
    {
        switch ($art) {
            case HappyHour::DOUBLE_TIME:
                return translate('subathon.happy.type.1');

            case HappyHour::EXTEND_ONLY:
                return translate('subathon.happy.type.2');

            case HappyHour::EXTEND:
                return translate('subathon.happy.type.3');
        }

        return translate('subathon.happy.type.0');
    }

    /**
     * Die Art einer Buchung als Wort - fuer die Spalte "Was".
     *
     * Auch hier ausgeschrieben: zusammengesetzte Schluessel sieht
     * bin/lang.php nicht.
     */
    public static function kind(string $art): string
    {
        switch ($art) {
            case 'sub':
                return translate('subathon.kind.sub');

            case 'gift':
                return translate('subathon.kind.gift');

            case 'bits':
                return translate('subathon.kind.bits');

            case 'donation':
                return translate('subathon.kind.donation');
        }

        return translate('subathon.kind.manual');
    }

    /**
     * Die Menge mit ihrer Einheit.
     *
     * Dieselbe Zahl heisst je nach Art etwas anderes: bei Bits sind
     * es Bits, bei einer Spende Euro, bei einem Abo die Stufe. Ohne
     * Einheit stuenden in der Spalte Zahlen, die nichts miteinander
     * zu tun haben.
     */
    public static function amount(string $art, string $menge): string
    {
        if ($menge === '') {
            return '';
        }

        switch ($art) {
            case 'bits':
                return $menge . ' Bits';

            case 'donation':
                return number_format((float) $menge, 2, ',', '.') . ' €';

            case 'manual':
                return $menge . ' min';
        }

        return $menge;
    }

    /** Dasselbe fuer den Zustand. */
    public static function status(string $status): string
    {
        switch ($status) {
            case 'running':
                return translate('subathon.status.running');

            case 'paused':
                return translate('subathon.status.paused');

            case 'ended':
                return translate('subathon.status.ended');
        }

        return translate('subathon.status.idle');
    }

    /**
     * Alle Platzhalter eines Textes ersetzen.
     */
    public static function render(App $app, string $text): string
    {
        $werte = self::values($app);

        foreach ($werte as $name => $wert) {
            $text = str_replace(['{{' . $name . '}}', '{{Conf.' . $name . '}}', '{{Color.' . $name . '}}'], $wert, $text);
        }

        return $text;
    }

    /**
     * Was sich einsetzen laesst - Name ohne Klammern.
     *
     * @return array<string, string>
     */
    public static function values(App $app): array
    {
        $proSub = Subathon::secondsPerSub($app);
        $bits = Subathon::bitsPerSub($app);
        $cent = Subathon::centPerSub($app);

        $farben = Subathon::colors($app);

        /*
         * Was eine Stunde kostet und was bis zur Obergrenze fehlt.
         * Gerundet wird nach oben: wer den fehlenden Betrag spendet,
         * soll die Zeit auch wirklich vollmachen - bei einer
         * Abrundung fehlten am Ende ein paar Sekunden.
         */
        $centProStunde = 3600 * $cent / $proSub;
        $euroProStunde = ceil($centProStunde) / 100;

        $rest = max(0, Subathon::maxDuration($app) - Subathon::timer($app));
        $euroBisVoll = Subathon::maxDuration($app) <= 0
            ? '∞'
            : number_format(ceil($rest * ($cent / $proSub)) / 100, 2, '.', '');

        $naechste = HappyHour::next(HappyHour::all($app), (int) date('G'));

        return [
            'DONE'          => $farben['done'],
            'DONE_TEXT'     => $farben['done_text'],
            'TODO'          => $farben['todo'],
            'TODO_TEXT'     => $farben['todo_text'],
            'POSSIBLE'      => $farben['possible'],
            'POSSIBLE_TEXT' => $farben['possible_text'],

            'MIN_PER_SUB'      => (string) intdiv($proSub, 60),
            'BITS_PER_SUB'     => (string) $bits,
            'SECONDS_PER_BIT'  => self::number($proSub / $bits),
            'CENT_PER_SUB'     => (string) $cent,
            'SECONDS_PER_CENT' => self::number($proSub / $cent),
            'CENT_PER_HOUR'    => self::number($centProStunde),
            'EURO_PER_HOUR'    => number_format($euroProStunde, 2, '.', ''),
            'EURO_UNTIL_FULL'  => $euroBisVoll,
            'OWNER'            => Subathon::owner($app),

            'HAPPY_HOUR_START' => $naechste === null ? '--:--' : sprintf('%02d:00', $naechste['start_hour']),
            'HAPPY_HOUR_END'   => $naechste === null ? '--:--' : sprintf('%02d:00', ($naechste['start_hour'] + 1) % 24),
        ];
    }

    /**
     * Eine Zahl, wie sie ein Mensch schreibt: ohne Nachkommastellen,
     * wenn keine noetig sind.
     *
     * Das Programm rechnete mit ToString("n") und schnitt danach
     * ",00" weg - dieselbe Absicht, hier ohne den Umweg ueber die
     * Textform.
     */
    public static function number(float $wert): string
    {
        if (abs($wert - round($wert)) < 0.005) {
            return (string) (int) round($wert);
        }

        return rtrim(rtrim(number_format($wert, 2, ',', ''), '0'), ',');
    }
}
