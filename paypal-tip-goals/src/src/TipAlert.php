<?php

declare(strict_types=1);

namespace TwitchController\Plugin\PaypalTipGoals;

use TwitchController\Core\App;
use TwitchController\Plugin\Alerts\Alerts;

/**
 * ===================================================================
 *  Der Alert zur Spende
 * ===================================================================
 *
 * Bis hierher konnte eine Spende zwar einen Alert ausloesen, aber
 * niemand konnte ihn einstellen: es gab keinen Reiter. Der Text stand
 * als Vorgabe im Code, Video, Ton und Dauer gab es gar nicht.
 *
 * Im alten System hiess dieser Reiter "Spende" und kannte die
 * Platzhalter {{ amount }} und {{ message }}. Hier kommt {{ name }}
 * dazu - anonym gespendet wird ueber ein Haekchen, und dann steht dort
 * ohnehin kein Name.
 *
 * Alles liegt unter EINEM Einstellungsschluessel und nicht unter
 * fuenfen: es ist eine Sache, die man zusammen einstellt, und ein
 * halber Alert - Text ohne Dauer - ergibt keinen Sinn.
 *
 * -------------------------------------------------------------------
 *  Stufen
 * -------------------------------------------------------------------
 *
 * Eine Spende von 50 Euro soll nicht dasselbe ausloesen wie eine von
 * einem. Darum gibt es Stufen: jede hat einen Mindestbetrag und ihren
 * eigenen Text, ihr eigenes Video, ihren eigenen Ton. Genau so war es
 * im alten System (alert_donation_tiers).
 *
 * Wer nichts davon will, laesst es bei einer Stufe - dann ist es
 * derselbe Reiter wie vorher.
 */
final class TipAlert
{
    /** Der Schluessel in den Einstellungen. */
    public const KEY = 'alert';

    /**
     * Was im Text vorkommen darf.
     *
     * Steht hier und nicht in der Vorlage: die Pruefsammlung liest es
     * mit, und der Reiter zeigt genau das an, was auch wirklich
     * eingesetzt wird.
     *
     * @var list<string>
     */
    public const PLACEHOLDERS = ['name', 'amount', 'message'];

    /** Laenger als das passt in keinen Alert. */
    public const MAX_TEXT = 200;

    /**
     * Mehr Stufen als das sind keine Stufen mehr, sondern eine Liste,
     * die niemand mehr ueberblickt.
     */
    public const MAX_TIERS = 10;

    /**
     * Die Einstellung, auf feste Form gebracht.
     *
     * @return array{enabled: bool, tiers: list<array{min_amount: int, text: string, video: string, audio: string, duration: int}>}
     */
    public static function config(App $app): array
    {
        $gespeichert = $app->settings->get(self::KEY, null, TipGoals::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        return [
            // Eingeschaltet, solange niemand es abgeschaltet hat: wer
            // das Plugin installiert, will den Alert.
            'enabled' => (bool) ($gespeichert['enabled'] ?? true),
            'tiers'   => self::tiersFrom($gespeichert),
        ];
    }

    /**
     * Die Stufen aus dem, was gespeichert ist.
     *
     * Hier steckt der Uebergang vom alten Stand: bis eben lagen Text,
     * Video, Ton und Dauer FLACH unter demselben Schluessel. Wer schon
     * einen Alert eingestellt hat, soll ihn behalten - also wird daraus
     * die erste und einzige Stufe. Umgeschrieben wird dabei nichts;
     * gespeichert wird die neue Form erst beim naechsten Speichern.
     *
     * @param array<string, mixed> $gespeichert
     * @return list<array{min_amount: int, text: string, video: string, audio: string, duration: int}>
     */
    public static function tiersFrom(array $gespeichert): array
    {
        $roh = $gespeichert['tiers'] ?? null;

        if (!is_array($roh) || $roh === []) {
            $roh = [[
                'min_amount' => 1,
                'text'       => $gespeichert['text'] ?? '',
                'video'      => $gespeichert['video'] ?? '',
                'audio'      => $gespeichert['audio'] ?? '',
                'duration'   => $gespeichert['duration'] ?? 0,
            ]];
        }

        /*
         * Nach Mindestbetrag geordnet, und jeder Betrag nur einmal.
         *
         * Beides macht das alte System ueber den Schluessel des Feldes,
         * und beides ist noetig: die Auswahl weiter unten laeuft die
         * Liste von unten nach oben durch und verlaesst sich darauf,
         * dass sie steigt. Zwei Stufen mit demselben Betrag waeren eine
         * Frage ohne Antwort - welche gilt?
         */
        $stufen = [];

        foreach ($roh as $eine) {
            if (!is_array($eine)) {
                continue;
            }

            $sauber = self::normalizeTier($eine);
            $stufen[$sauber['min_amount']] = $sauber;
        }

        if ($stufen === []) {
            $stufen[1] = self::normalizeTier([]);
        }

        ksort($stufen);

        return array_values(array_slice($stufen, 0, self::MAX_TIERS));
    }

    /**
     * Eine Stufe auf feste Form bringen.
     *
     * @param array<string, mixed> $stufe
     * @return array{min_amount: int, text: string, video: string, audio: string, duration: int}
     */
    public static function normalizeTier(array $stufe): array
    {
        $text = trim((string) ($stufe['text'] ?? ''));

        if (preg_match('/^.{0,' . self::MAX_TEXT . '}/us', $text, $treffer) === 1) {
            $text = $treffer[0];
        }

        return [
            // Mindestens 1, wie im alten System. Ein Betrag darunter
            // faellt auf die unterste Stufe zurueck - siehe tierFor().
            'min_amount' => max(1, (int) ($stufe['min_amount'] ?? 1)),
            'text'       => $text !== '' ? $text : translate('pp_tip.alert_default'),
            'video'      => trim((string) ($stufe['video'] ?? '')),
            'audio'      => trim((string) ($stufe['audio'] ?? '')),
            'duration'   => self::duration($stufe['duration'] ?? null),
        ];
    }

    /**
     * Welche Stufe fuer diesen Betrag gilt.
     *
     * Die hoechste, deren Mindestbetrag erreicht ist. Liegt der Betrag
     * unter der untersten Stufe, gilt trotzdem die unterste: ein Alert
     * soll kommen. Genau so entscheidet es das alte System
     * (twitch_events_select_donation_tier).
     *
     * Ohne App und ohne Einstellungen, damit sich genau das pruefen
     * laesst - hier steckt die ganze Regel.
     *
     * @param list<array{min_amount: int, text: string, video: string, audio: string, duration: int}> $stufen
     * @return array{min_amount: int, text: string, video: string, audio: string, duration: int}
     */
    public static function tierFor(array $stufen, float $betrag): array
    {
        $gewaehlt = null;

        foreach ($stufen as $stufe) {
            if ($betrag >= (float) $stufe['min_amount']) {
                $gewaehlt = $stufe;

                continue;
            }

            break;
        }

        return $gewaehlt ?? $stufen[0] ?? self::normalizeTier([]);
    }

    /**
     * Eine Stufe anhaengen.
     *
     * Ihr Mindestbetrag liegt ueber dem hoechsten bisherigen - sonst
     * fiele sie mit einer vorhandenen zusammen und verschwaende beim
     * Speichern wieder, weil jeder Betrag nur einmal vorkommt. Wo
     * genau, entscheidet ohnehin der Benutzer; das hier ist nur ein
     * Anfang, den er nicht erst korrigieren muss.
     *
     * @param list<array<string, mixed>> $stufen
     * @return list<array<string, mixed>>
     */
    public static function withNewTier(array $stufen): array
    {
        if (count($stufen) >= self::MAX_TIERS) {
            return $stufen;
        }

        $hoechster = 0;
        foreach ($stufen as $eine) {
            $hoechster = max($hoechster, (int) ($eine['min_amount'] ?? 0));
        }

        $stufen[] = ['min_amount' => $hoechster + 1];

        return $stufen;
    }

    /**
     * Eine Stufe herausnehmen.
     *
     * Die letzte bleibt stehen: ohne sie gaebe es fuer kleine Betraege
     * keinen Alert, und der Reiter waere ein Formular, das nichts tut.
     *
     * @param list<array<string, mixed>> $stufen
     * @return list<array<string, mixed>>
     */
    public static function withoutTier(array $stufen, int $stelle): array
    {
        if (count($stufen) <= 1 || !isset($stufen[$stelle])) {
            return $stufen;
        }

        unset($stufen[$stelle]);

        return array_values($stufen);
    }

    /**
     * @param array<string, mixed> $eingaben
     */
    public static function save(App $app, array $eingaben): void
    {
        $roh = $eingaben['tiers'] ?? [];
        $roh = is_array($roh) ? $roh : [];

        $app->settings->set(self::KEY, [
            // Der Schalter kommt NICHT aus diesem Formular - er hat
            // seinen eigenen Knopf. Wuerde er hier mitgeschrieben,
            // schaltete ein Speichern ihn nebenbei aus, weil ein nicht
            // angehaktes Kaestchen gar nicht erst mitgeschickt wird.
            'enabled' => self::config($app)['enabled'],
            'tiers'   => self::tiersFrom(['tiers' => $roh]),
        ], TipGoals::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set(self::KEY, ['enabled' => $an] + self::config($app), TipGoals::scope());
    }

    /**
     * Den Alert ausloesen.
     *
     * Text und Werte bleiben getrennt: Alerts setzt sie selbst ein und
     * maskiert dabei, was der Spender geschrieben hat. Fertig
     * zusammengebautes HTML hineinzureichen waere ein Weg, ueber eine
     * Spendennachricht Markup ins Overlay zu bekommen.
     *
     * @param array<string, mixed> $spende
     */
    public static function fire(App $app, array $spende): bool
    {
        if (!$app->plugins->isEnabled('alerts')) {
            return false;
        }

        $config = self::config($app);

        if (!$config['enabled']) {
            return false;
        }

        /*
         * Nach demselben Betrag, der auch im Text steht.
         *
         * Nicht nach dem Netto: auf dem Ziel landet, was ankommt, aber
         * die Stufe ist ein Versprechen an den Spender - "ab 5 Euro
         * kommt das grosse Video". Wer fuenf gibt, hat fuenf gegeben,
         * auch wenn PayPal davon etwas einbehaelt.
         */
        $stufe = self::tierFor($config['tiers'], (float) ($spende['amount'] ?? 0));

        return Alerts::send($app, [
            'kind'     => 'tip',
            'text'     => $stufe['text'],
            'video'    => $stufe['video'],
            'audio'    => $stufe['audio'],
            'duration' => $stufe['duration'],
            'values'   => self::values($spende),
        ]);
    }

    /**
     * Aus "12,50 €" die Zahl.
     *
     * Fuer den Test: dort ist der Betrag ein Textfeld, in das jemand
     * schreibt, was er will - mit Komma, mit Punkt, mit Waehrung oder
     * ganz ohne. Damit die Vorschau DIESELBE Stufe zeigt, die eine
     * echte Spende dieses Betrags ausloest, muss daraus eine Zahl
     * werden.
     *
     * Was nicht zu deuten ist, ergibt 0 - und 0 landet auf der
     * untersten Stufe. Eine Vorschau, die gar nichts zeigt, waere die
     * schlechtere Antwort auf einen Tippfehler.
     */
    public static function amountFrom(string $text): float
    {
        $text = (string) preg_replace('/[^0-9,.\-]/', '', $text);

        // Das letzte Trennzeichen ist das Komma der Nachkommastellen -
        // alles davor sind Tausenderpunkte. "1.234,50" und "1,234.50"
        // meinen dasselbe.
        $letzter = max((int) strrpos($text, ',') - 1, (int) strrpos($text, '.') - 1);

        if ($letzter >= 0) {
            $text = str_replace([',', '.'], '', substr($text, 0, $letzter + 1))
                . '.' . substr($text, $letzter + 2);
        }

        return is_numeric($text) ? max(0.0, (float) $text) : 0.0;
    }

    /**
     * Die Werte fuer die Platzhalter.
     *
     * @param array<string, mixed> $spende
     * @return array<string, string>
     */
    public static function values(array $spende): array
    {
        return [
            'name'    => (string) ($spende['name'] ?? ''),
            'amount'  => number_format((float) ($spende['amount'] ?? 0), 2, ',', '.') . ' €',
            'message' => (string) ($spende['message'] ?? ''),
        ];
    }

    /**
     * Die Dauer in Sekunden - 0 heisst "die Vorgabe von Alerts".
     *
     * Begrenzt wird hier UND in Alerts::send(). Doppelt ist hier
     * richtig: der Wert steht danach in den Einstellungen und wird im
     * Formular wieder angezeigt.
     */
    private static function duration(mixed $wert): int
    {
        $zahl = (int) $wert;

        if ($zahl <= 0) {
            return 0;
        }

        return max(1, min(Alerts::MAX_DURATION, $zahl));
    }
}
