<?php

declare(strict_types=1);

namespace TwitchController\Plugin\StreamlabsTipGoals;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Support\Http;

/**
 * ===================================================================
 *  Die Spenden von Streamlabs
 * ===================================================================
 *
 * Gefragt wird die Spendenliste:
 *
 *   GET streamlabs.com/api/v2.0/donations?access_token=…&limit=100
 *
 * Antwort ist ein Objekt mit "data" - den Spenden, neueste zuerst.
 * Jede bringt eine donation_id, einen Zeitpunkt (created_at) und einen
 * Betrag mit.
 *
 * Das ist die EINZIGE Datei, die sich vom StreamElements-Plugin
 * unterscheidet. Alles andere - Zielliste, Balken, Oberflaeche - ist
 * dort wortgleich. Das ist Absicht: die beiden schliessen sich aus,
 * also gibt es nie beide gleichzeitig, und ein gemeinsames Basis-
 * Plugin waere ein drittes Stueck, das man installieren und pflegen
 * muesste, damit zwei Zeilen nicht doppelt dastehen.
 *
 * Unterschied zu StreamElements: Streamlabs braucht KEINE Kanal-ID.
 * Das Token gehoert schon zu einem Konto - eine zweite Angabe waere
 * ein Feld, das man ausfuellen muss, ohne dass es etwas entscheidet.
 *
 * Der Zugriff laeuft ueber den Adressteil und nicht ueber einen
 * Authorization-Kopf: so verlangt es diese Schnittstelle.
 */
final class Source
{
    /** Wie viele Spenden je Abfrage hoechstens verarbeitet werden. */
    private const MAX_PER_ROUND = 50;

    /** Wie viele Streamlabs je Abfrage liefern soll. */
    private const LIMIT = 100;

    // -----------------------------------------------------------------
    //  Zugangsdaten
    // -----------------------------------------------------------------

    /**
     * Das Token wird VERSCHLUESSELT abgelegt.
     *
     * Es ist ein Schluessel zum Spendenkonto, kein Einstellwert - und
     * wer die Datenbank liest, soll damit nichts anfangen koennen.
     */
    public static function setToken(App $app, string $token): void
    {
        $token = trim($token);

        if ($token === '') {
            $app->settings->set('token', '', TipGoals::scope());

            return;
        }

        $app->settings->setSecret('token', $token, TipGoals::scope());
    }

    public static function token(App $app): string
    {
        return $app->settings->secret('token', TipGoals::scope());
    }

    public static function hasToken(App $app): bool
    {
        return $app->settings->hasSecret('token', TipGoals::scope());
    }

    /** Kann ueberhaupt gefragt werden? */
    public static function ready(App $app): bool
    {
        return self::hasToken($app);
    }

    // -----------------------------------------------------------------
    //  Abfragen
    // -----------------------------------------------------------------

    /**
     * Neue Spenden holen und ihre Summe zurueckgeben.
     *
     * Was schon gezaehlt wurde, merkt sich das Plugin am Zeitpunkt der
     * zuletzt verbuchten Spende. Beim ERSTEN Lauf wird nichts
     * nachgetragen, sondern nur der Zeitpunkt gesetzt - sonst buchte
     * das Einrichten des Plugins die Spendenhistorie eines ganzen
     * Jahres auf das erste Ziel.
     *
     * @return array{amount: float, count: int, donations: list<array{name: string, amount: float, message: string}>, error: string}
     */
    public static function collect(App $app): array
    {
        $leer = ['amount' => 0.0, 'count' => 0, 'donations' => [], 'error' => ''];

        if (!self::ready($app)) {
            return $leer;
        }

        try {
            $antwort = Http::get(
                'https://streamlabs.com/api/v2.0/donations?'
                    . http_build_query([
                        'access_token' => self::token($app),
                        'limit'        => self::LIMIT,
                        'currency'     => 'EUR',
                    ]),
                ['Accept' => 'application/json']
            );
        } catch (Throwable $e) {
            return ['amount' => 0.0, 'count' => 0, 'donations' => [], 'error' => $e->getMessage()];
        }

        if (!$antwort->ok()) {
            return ['amount' => 0.0, 'count' => 0, 'donations' => [], 'error' => self::explain($antwort->status, $antwort->error())];
        }

        $daten = $antwort->json['data'] ?? [];
        if (!is_array($daten)) {
            return $leer;
        }

        $seit = $app->settings->int('last_donation_at', 0, TipGoals::scope());

        // Erster Lauf: die Uhr stellen und nichts buchen.
        if ($seit === 0) {
            $app->settings->set('last_donation_at', time(), TipGoals::scope());

            return $leer;
        }

        $summe = 0.0;
        $anzahl = 0;
        $spenden = [];
        $neuester = $seit;

        // Von hinten nach vorn: die Liste kommt neueste zuerst, und
        // gebucht wird in der Reihenfolge des Eingangs.
        foreach (array_slice(array_reverse($daten), 0, self::MAX_PER_ROUND) as $zeile) {
            if (!is_array($zeile)) {
                continue;
            }

            $wann = self::timestamp((string) ($zeile['created_at'] ?? ''));
            if ($wann <= $seit) {
                continue;
            }

            $betrag = (float) ($zeile['amount'] ?? 0);
            if ($betrag <= 0) {
                continue;
            }

            $summe += $betrag;
            $anzahl++;
            $neuester = max($neuester, $wann);

            // Die einzelne Spende, nicht nur ihr Betrag.
            //
            // Vorher wurde hier nur summiert - Name und Nachricht
            // fielen weg. Fuer den Balken reicht das, fuer einen Alert
            // nicht: "5,00 EUR" ohne den, der sie geschickt hat, ist
            // keine Danksagung.
            $spenden[] = [
                'name'    => (string) ($zeile['name'] ?? ''),
                'amount'  => $betrag,
                'message' => trim((string) ($zeile['message'] ?? '')),
            ];

            $app->log(sprintf(
                '%s: Spende von %s ueber %.2f',
                TipGoals::SLUG,
                (string) ($zeile['name'] ?? '?'),
                $betrag
            ));
        }

        // Der Zeiger rueckt nur vor, wenn auch gebucht wurde.
        if ($anzahl > 0) {
            $app->settings->set('last_donation_at', $neuester, TipGoals::scope());
        }

        return ['amount' => $summe, 'count' => $anzahl, 'donations' => $spenden, 'error' => ''];
    }

    /**
     * Ein Zeitstempel aus dem, was die Schnittstelle liefert.
     *
     * Streamlabs schickt eine Zahl (Sekunden seit 1970), andere Wege
     * eine Zeichenkette - beides wird genommen. Was hier nicht lesbar
     * ist, ergibt 0, und 0 ist aelter als jeder gemerkte Stand, wird
     * also uebersprungen. Lieber eine Spende verpassen als eine
     * doppelt buchen.
     */
    public static function timestamp(string $roh): int
    {
        $roh = trim($roh);
        if ($roh === '') {
            return 0;
        }

        if (ctype_digit($roh)) {
            // Millisekunden kommen auch vor.
            $zahl = (int) $roh;

            return $zahl > 99999999999 ? intdiv($zahl, 1000) : $zahl;
        }

        $zeit = strtotime($roh);

        return $zeit === false ? 0 : $zeit;
    }

    private static function explain(int $status, string $roh): string
    {
        return match ($status) {
            401, 403 => translate('sl_tip.error.unauthorized'),
            429 => translate('sl_tip.error.rate_limit'),
            default => $roh,
        };
    }
}
