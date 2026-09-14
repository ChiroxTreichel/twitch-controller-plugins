<?php

declare(strict_types=1);

namespace TwitchController\Plugin\StreamelementsTipGoals;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Support\Http;

/**
 * ===================================================================
 *  Die Spenden von StreamElements
 * ===================================================================
 *
 * Gefragt wird die Liste der letzten Tips:
 *
 *   GET api.streamelements.com/kappa/v2/tips/{channelId}/moderation
 *   Authorization: Bearer <JWT>
 *
 * Antwort ist ein Objekt mit "recent" - den zuletzt eingegangenen
 * Spenden, neueste zuerst.
 *
 * Das ist die EINZIGE Datei, die sich vom Streamlabs-Plugin
 * unterscheidet. Alles andere - Zielliste, Balken, Oberflaeche - ist
 * dort wortgleich. Das ist Absicht: die beiden schliessen sich aus,
 * also gibt es nie beide gleichzeitig, und ein gemeinsames Basis-
 * Plugin waere ein drittes Stueck, das man installieren und pflegen
 * muesste, damit zwei Zeilen nicht doppelt dastehen.
 *
 * Was NICHT gezaehlt wird: geloeschte, nicht bestaetigte und noch
 * nicht freigegebene Spenden. Der Balken soll zeigen, was wirklich
 * angekommen ist.
 */
final class Source
{
    /** Wie viele Spenden je Abfrage hoechstens verarbeitet werden. */
    private const MAX_PER_ROUND = 50;

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

    public static function setChannelId(App $app, string $id): void
    {
        // Keine Geheimhaltung: die Kanal-ID steht in jeder Adresse von
        // StreamElements. Sie zu verschluesseln waere eine Zusage, die
        // sie nicht einloest.
        $app->settings->set('channel_id', trim($id), TipGoals::scope());
    }

    public static function channelId(App $app): string
    {
        return trim($app->settings->string('channel_id', '', TipGoals::scope()));
    }

    /** Kann ueberhaupt gefragt werden? */
    public static function ready(App $app): bool
    {
        return self::hasToken($app) && self::channelId($app) !== '';
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
     * @return array{amount: float, count: int, error: string}
     */
    public static function collect(App $app): array
    {
        $leer = ['amount' => 0.0, 'count' => 0, 'error' => ''];

        if (!self::ready($app)) {
            return $leer;
        }

        try {
            $antwort = Http::get(
                'https://api.streamelements.com/kappa/v2/tips/'
                    . rawurlencode(self::channelId($app)) . '/moderation',
                [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . self::token($app),
                ]
            );
        } catch (Throwable $e) {
            return ['amount' => 0.0, 'count' => 0, 'error' => $e->getMessage()];
        }

        if (!$antwort->ok()) {
            return ['amount' => 0.0, 'count' => 0, 'error' => self::explain($antwort->status, $antwort->error())];
        }

        $recent = $antwort->json['recent'] ?? [];
        if (!is_array($recent)) {
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
        $neuester = $seit;

        // Von hinten nach vorn: die Liste kommt neueste zuerst, und
        // gebucht wird in der Reihenfolge des Eingangs.
        foreach (array_slice(array_reverse($recent), 0, self::MAX_PER_ROUND) as $zeile) {
            if (!is_array($zeile)) {
                continue;
            }

            $wann = self::timestamp((string) ($zeile['createdAt'] ?? ''));
            if ($wann <= $seit) {
                continue;
            }

            // Nur, was wirklich angekommen und freigegeben ist.
            if (($zeile['deleted'] ?? false) === true
                || (string) ($zeile['status'] ?? '') !== 'success'
                || (string) ($zeile['approved'] ?? '') !== 'allowed'
            ) {
                continue;
            }

            $betrag = (float) ($zeile['donation']['amount'] ?? 0);
            if ($betrag <= 0) {
                continue;
            }

            $summe += $betrag;
            $anzahl++;
            $neuester = max($neuester, $wann);

            $app->log(sprintf(
                '%s: Spende von %s ueber %.2f',
                TipGoals::SLUG,
                (string) ($zeile['donation']['user']['username'] ?? '?'),
                $betrag
            ));
        }

        // Der Zeiger rueckt nur vor, wenn auch gebucht wurde. Sonst
        // liefe er an einer Spende vorbei, die in derselben Runde
        // ankam, aber noch auf ihre Freigabe wartete.
        if ($anzahl > 0) {
            $app->settings->set('last_donation_at', $neuester, TipGoals::scope());
        }

        return ['amount' => $summe, 'count' => $anzahl, 'error' => ''];
    }

    /**
     * Ein Zeitstempel aus dem, was die Schnittstelle liefert.
     *
     * Erlaubt ist beides: eine Zeichenkette nach ISO und eine Zahl.
     * Was hier nicht lesbar ist, ergibt 0 - und 0 ist aelter als jeder
     * gemerkte Stand, wird also uebersprungen. Lieber eine Spende
     * verpassen als eine doppelt buchen.
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
            401, 403 => translate('se_tip.error.unauthorized'),
            404 => translate('se_tip.error.unknown_channel'),
            429 => translate('se_tip.error.rate_limit'),
            default => $roh,
        };
    }
}
