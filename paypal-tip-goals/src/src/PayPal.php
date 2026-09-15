<?php

declare(strict_types=1);

namespace TwitchController\Plugin\PaypalTipGoals;

use RuntimeException;
use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Support\Http;

/**
 * ===================================================================
 *  Der Weg zu PayPal
 * ===================================================================
 *
 * Zwei Schritte, und zwischen ihnen verlaesst der Spender diese Seite:
 *
 *   1. Order anlegen   -> PayPal antwortet mit einer Adresse
 *   2. Weiterleiten    -> der Spender bezahlt bei PayPal
 *   3. Rueckkehr       -> Capture, und erst JETZT fliesst Geld
 *
 * Bis zum Capture ist nichts passiert. Bricht jemand bei PayPal ab
 * oder schliesst den Tab, bleibt die Order genehmigt und unbezahlt -
 * das ist unschoen, aber nicht gefaehrlich. Gefaehrlich waere der
 * umgekehrte Fall.
 *
 * Aus der Legacy uebernommen, Aufruf fuer Aufruf. Zwei Dinge sind
 * anders:
 *
 *   Die Zugangsdaten liegen VERSCHLUESSELT in den Einstellungen und
 *   nicht im Klartext in einer .env - es sind Schluessel zu einem
 *   Geldkonto.
 *
 *   Die Rueckadressen werden aus APP_URL gebaut und stehen nicht fest
 *   im Code. Im alten System stand dort spenden.talutah.de.
 */
final class PayPal
{
    /** Wie lange ein Zugangstoken von PayPal gilt, abzueglich Sicherheit. */
    private const TOKEN_SLACK = 30;

    /**
     * Mehr nimmt diese Seite nicht an.
     *
     * Nicht aus Vorsicht gegen PayPal, sondern gegen Vertipper: wer
     * 5 statt 5,00 meint, hat sich nicht um den Faktor tausend
     * vergriffen - wer 5000 tippt, vielleicht schon.
     */
    public const MAX_AMOUNT = 10000.0;

    // -----------------------------------------------------------------
    //  Zugangsdaten
    // -----------------------------------------------------------------

    public static function setCredentials(App $app, string $clientId, string $secret): void
    {
        $clientId = trim($clientId);

        if ($clientId !== '') {
            $app->settings->setSecret('paypal_client_id', $clientId, TipGoals::scope());
        }

        if (trim($secret) !== '') {
            $app->settings->setSecret('paypal_secret', trim($secret), TipGoals::scope());
        }
    }

    public static function forget(App $app): void
    {
        $app->settings->setMany([
            'paypal_client_id' => '',
            'paypal_secret'    => '',
        ], TipGoals::scope());
    }

    public static function hasCredentials(App $app): bool
    {
        return $app->settings->hasSecret('paypal_client_id', TipGoals::scope())
            && $app->settings->hasSecret('paypal_secret', TipGoals::scope());
    }

    /** Echtbetrieb oder Testkonto? */
    public static function live(App $app): bool
    {
        return $app->settings->bool('paypal_live', false, TipGoals::scope());
    }

    public static function setLive(App $app, bool $live): void
    {
        $app->settings->set('paypal_live', $live, TipGoals::scope());
    }

    private static function baseUrl(App $app): string
    {
        return self::live($app)
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    // -----------------------------------------------------------------
    //  Anmelden
    // -----------------------------------------------------------------

    /**
     * Ein Zugangstoken, gemerkt fuer die Dauer des Requests.
     *
     * Laenger zu merken brachte nichts: Web und Worker sind getrennte
     * Prozesse, und eine Spende erzeugt hoechstens zwei Aufrufe.
     */
    private static ?string $token = null;
    private static int $tokenBis = 0;

    private static function accessToken(App $app): string
    {
        if (self::$token !== null && time() < self::$tokenBis - self::TOKEN_SLACK) {
            return self::$token;
        }

        if (!self::hasCredentials($app)) {
            throw new RuntimeException(translate('pp_tip.error.no_credentials'));
        }

        $antwort = Http::form(
            self::baseUrl($app) . '/v1/oauth2/token',
            ['grant_type' => 'client_credentials'],
            [
                'Authorization' => 'Basic ' . base64_encode(
                    $app->settings->secret('paypal_client_id', TipGoals::scope())
                    . ':' . $app->settings->secret('paypal_secret', TipGoals::scope())
                ),
                'Accept' => 'application/json',
            ]
        );

        $token = (string) ($antwort->json['access_token'] ?? '');

        if (!$antwort->ok() || $token === '') {
            throw new RuntimeException(self::explain($antwort->status, $antwort->error()));
        }

        self::$token = $token;
        self::$tokenBis = time() + (int) ($antwort->json['expires_in'] ?? 3600);

        return $token;
    }

    // -----------------------------------------------------------------
    //  Order und Capture
    // -----------------------------------------------------------------

    /**
     * Eine Order anlegen und die Adresse zurueckgeben, zu der der
     * Spender geschickt wird.
     *
     * Der eigene Merker steckt als reference_id UND als custom_id in
     * der Order. Zurueck findet man sie ueber die Order-Nummer; die
     * beiden Felder sind fuer den Fall, dass jemand im PayPal-Konto
     * nachsehen muss, was zu welcher Spende gehoert.
     *
     * @return array{id: string, url: string}
     */
    public static function createOrder(App $app, string $merker, float $betrag, string $zweck): array
    {
        $antwort = Http::json(
            'POST',
            self::baseUrl($app) . '/v2/checkout/orders',
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $merker,
                    'custom_id'    => $merker,
                    'description'  => self::cut($zweck !== '' ? $zweck : translate('pp_tip.item_name'), 127),
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value'         => number_format($betrag, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name'          => self::cut(TipGoals::brand($app), 127),
                    'locale'              => 'de-DE',
                    'user_action'         => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    'return_url'          => $app->url('/tips/return'),
                    'cancel_url'          => $app->url('/tips/cancel') . '?merker=' . rawurlencode($merker),
                ],
            ],
            [
                'Authorization' => 'Bearer ' . self::accessToken($app),
                'Accept'        => 'application/json',
                // Derselbe Merker zweimal geschickt ergibt dieselbe
                // Order - ein Doppelklick legt also keine zweite an.
                'PayPal-Request-Id' => $merker,
            ]
        );

        $id = (string) ($antwort->json['id'] ?? '');

        if (!$antwort->ok() || $id === '') {
            throw new RuntimeException(self::explain($antwort->status, $antwort->error()));
        }

        $url = self::approveUrl($antwort->json);

        if ($url === '') {
            throw new RuntimeException(translate('pp_tip.error.no_approve_url'));
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * Die Order einziehen. JETZT fliesst Geld.
     *
     * @return array{ok: bool, status: string, capture_id: string, gross: float, fee: ?float, net: ?float, currency: string, at: string, error: string}
     */
    public static function capture(App $app, string $orderId, string $merker): array
    {
        $leer = [
            'ok' => false, 'status' => '', 'capture_id' => '',
            'gross' => 0.0, 'fee' => null, 'net' => null,
            'currency' => 'EUR', 'at' => '', 'error' => '',
        ];

        try {
            // Ein leeres OBJEKT, kein leeres Feld: json_encode([])
            // ergaebe "[]", und PayPal will hier "{}". Darum request()
            // statt json() - das alte System schickte es genauso.
            $antwort = Http::request(
                'POST',
                self::baseUrl($app) . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
                [
                    'Authorization' => 'Bearer ' . self::accessToken($app),
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    // Derselbe Merker wie beim Anlegen: ein zweiter
                    // Versuch zieht nicht zweimal ein.
                    'PayPal-Request-Id' => $merker,
                ],
                '{}'
            );
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()] + $leer;
        }

        if (!$antwort->ok()) {
            return ['error' => self::explain($antwort->status, $antwort->error())] + $leer;
        }

        return self::summary($antwort->json) + $leer;
    }

    /**
     * Aus der Antwort das herausholen, was gebraucht wird.
     *
     * Die Betraege kommen aus seller_receivable_breakdown, wenn PayPal
     * es mitschickt - das ist die Wahrheit ueber Gebuehr und
     * Nettobetrag. Fehlt es, bleibt nur der Bruttobetrag; gerechnet
     * wird dann anderswo.
     *
     * @param array<string, mixed> $antwort
     * @return array<string, mixed>
     */
    public static function summary(array $antwort): array
    {
        $einzug = $antwort['purchase_units'][0]['payments']['captures'][0] ?? null;

        if (!is_array($einzug)) {
            return ['ok' => false, 'status' => (string) ($antwort['status'] ?? '')];
        }

        $brutto = (float) ($einzug['amount']['value'] ?? 0);
        $waehrung = (string) ($einzug['amount']['currency_code'] ?? 'EUR');
        $gebuehr = null;
        $netto = null;

        $aufteilung = $einzug['seller_receivable_breakdown'] ?? null;
        if (is_array($aufteilung)) {
            if (isset($aufteilung['gross_amount']['value'])) {
                $brutto = (float) $aufteilung['gross_amount']['value'];
                $waehrung = (string) ($aufteilung['gross_amount']['currency_code'] ?? $waehrung);
            }
            if (isset($aufteilung['paypal_fee']['value'])) {
                $gebuehr = (float) $aufteilung['paypal_fee']['value'];
            }
            if (isset($aufteilung['net_amount']['value'])) {
                $netto = (float) $aufteilung['net_amount']['value'];
            }
        }

        $status = strtoupper((string) ($antwort['status'] ?? $einzug['status'] ?? ''));

        return [
            'ok'         => $status === 'COMPLETED' && (string) ($einzug['id'] ?? '') !== '',
            'status'     => $status,
            'capture_id' => (string) ($einzug['id'] ?? ''),
            'gross'      => $brutto,
            'fee'        => $gebuehr,
            'net'        => $netto,
            'currency'   => $waehrung !== '' ? $waehrung : 'EUR',
            'at'         => (string) ($einzug['create_time'] ?? ''),
        ];
    }

    /**
     * Die Adresse, zu der der Spender geschickt wird.
     *
     * payer-action zuerst, approve als Rueckfall: PayPal hat den Namen
     * gewechselt, und beide kommen in freier Wildbahn vor.
     *
     * @param array<string, mixed> $order
     */
    public static function approveUrl(array $order): string
    {
        foreach (['payer-action', 'approve'] as $gesucht) {
            foreach (($order['links'] ?? []) as $link) {
                if (is_array($link) && ($link['rel'] ?? '') === $gesucht) {
                    return (string) ($link['href'] ?? '');
                }
            }
        }

        return '';
    }

    private static function explain(int $status, string $roh): string
    {
        return match (true) {
            $status === 401 || $status === 403 => translate('pp_tip.error.unauthorized'),
            $status === 422 => translate('pp_tip.error.rejected'),
            $status === 429 => translate('pp_tip.error.rate_limit'),
            $status >= 500  => translate('pp_tip.error.paypal_down'),
            default => $roh !== '' ? $roh : translate('pp_tip.error.paypal_down'),
        };
    }

    /** Zeichenweise kuerzen - PayPal zaehlt Zeichen, nicht Bytes. */
    private static function cut(string $text, int $laenge): string
    {
        return preg_match('/^.{0,' . $laenge . '}/us', $text, $treffer) === 1 ? $treffer[0] : '';
    }
}
