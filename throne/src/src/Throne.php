<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Throne;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * ===================================================================
 *  Throne
 * ===================================================================
 *
 * Throne ist eine Wunschliste: Zuschauer kaufen dem Streamer etwas
 * davon, geben Geld dazu oder sammeln gemeinsam auf einen Wunsch. Was
 * dabei passiert, schickt Throne als Webhook hierher.
 *
 * Drei Ereignisse, und sie sind nicht dasselbe:
 *
 *   gift_purchased          jemand kauft einen Gegenstand
 *   contribution_purchased  jemand gibt Geld dazu
 *   gift_crowdfunded        ein Sammelziel ist voll - ohne Kaeufer,
 *                           das ist der Abschluss von vielen
 *
 * Aus dem alten System uebernommen, einschliesslich der Pruefung der
 * Unterschrift und der Umrechnung der Betraege.
 */
final class Throne
{
    public const SLUG = 'throne';

    /** Die Adresse, die bei Throne eingetragen wird. */
    public const WEBHOOK_PATH = '/throne/webhook';

    /**
     * Wie alt eine Unterschrift sein darf.
     *
     * Ohne diese Grenze liesse sich eine mitgeschnittene Nachricht
     * spaeter erneut abschicken - die Unterschrift bliebe ja gueltig.
     * Fuenf Minuten sind der Wert des alten Systems.
     */
    public const MAX_AGE = 300;

    /**
     * Thrones oeffentlicher Schluessel.
     *
     * Der steht so in Thrones Dokumentation und ist fuer alle gleich -
     * es ist kein Wert, den sich jeder selbst holt. Darum steht er
     * hier und nicht als Pflichtfeld in den Einstellungen: ein Feld,
     * in das alle dasselbe eintippen, ist eine Fehlerquelle und keine
     * Einstellung.
     *
     * Dort steht er als PEM:
     *
     *   -----BEGIN PUBLIC KEY-----
     *   MCowBQYDK2VwAyEAPXbUfxh7XL4SYUVcfhmYMIbxvtR9E9LDd8gPJ1PwSD8=
     *   -----END PUBLIC KEY-----
     *
     * Das sind 44 Byte DER: zwoelf Byte Vorspann, der "Ed25519" sagt
     * (302a300506032b6570032100), und dahinter die 32 Byte, die
     * sodium haben will. Genau die stehen hier.
     */
    public const DEFAULT_PUBLIC_KEY = '3d76d47f187b5cbe1261455c7e19983086f1bed47d13d2c377c80f2753f0483f';

    /** Die drei Ereignisse, in der Reihenfolge des alten Systems. */
    public const CASES = ['gift', 'contribution', 'crowdfund'];

    /** Welcher Ereignistyp zu welchem Fall gehoert. */
    public const TYPES = [
        'gift_purchased'         => 'gift',
        'contribution_purchased' => 'contribution',
        'gift_crowdfunded'       => 'crowdfund',
    ];

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Der oeffentliche Schluessel
    // -----------------------------------------------------------------

    /**
     * Der Schluessel, mit dem Throne unterschreibt.
     *
     * Verschluesselt abgelegt wie jedes Geheimnis - obwohl ein
     * OEFFENTLICHER Schluessel keines ist. Der Grund ist ein anderer:
     * wer ihn austauschen kann, kann sich eigene Ereignisse
     * unterschreiben. Er gehoert also geschuetzt, nur nicht vor dem
     * Lesen.
     */
    public static function publicKey(App $app): string
    {
        $eigener = trim($app->settings->secret('public_key', '', self::scope()));

        // Der eigene gewinnt - aber nur, wenn einer da ist. Er ist die
        // Notluke fuer den Tag, an dem Throne den Schluessel wechselt
        // und dieses Plugin noch nicht nachgezogen hat.
        return $eigener !== '' ? $eigener : self::DEFAULT_PUBLIC_KEY;
    }

    /** Wird ein eigener Schluessel benutzt statt des mitgelieferten? */
    public static function hasOwnKey(App $app): bool
    {
        return trim($app->settings->secret('public_key', '', self::scope())) !== '';
    }

    public static function setPublicKey(App $app, string $hex): void
    {
        $app->settings->setSecret('public_key', trim($hex), self::scope());
    }

    /**
     * Laesst sich ueberhaupt pruefen?
     *
     * Seit der Schluessel mitgeliefert wird, ist das immer ja - die
     * Frage steht trotzdem hier, weil die Seiten sie stellen und ein
     * fest verdrahtetes "true" an drei Stellen schlechter zu aendern
     * waere als eine Methode an einer.
     */
    public static function hasKey(App $app): bool
    {
        return self::looksLikeKey(self::publicKey($app));
    }

    /**
     * Sieht das nach einem Ed25519-Schluessel aus?
     *
     * 32 Bytes, hexadezimal - also 64 Zeichen. Geprueft wird beim
     * Speichern und nicht erst beim ersten Webhook: ein vertippter
     * Schluessel faellt sonst dadurch auf, dass nichts ankommt, und
     * das sieht aus wie "Throne schickt nichts".
     */
    public static function looksLikeKey(string $hex): bool
    {
        return preg_match('/^[0-9a-fA-F]{64}$/', trim($hex)) === 1;
    }

    // -----------------------------------------------------------------
    //  Die Unterschrift
    // -----------------------------------------------------------------

    /**
     * Stimmt die Unterschrift zu diesem Koerper?
     *
     * Unterschrieben wird "<zeitstempel>.<koerper>" - der Zeitstempel
     * gehoert mit hinein, sonst liesse sich eine alte Nachricht mit
     * neuem Zeitstempel weiterreichen.
     *
     * Zurueck kommt ein KENNWORT und kein Satz: der Grund landet im
     * Log und nirgends vor Augen. Ein Satz muesste uebersetzt werden,
     * und ein uebersetzter Grund im Log ist schwerer zu suchen als
     * "bad_signature".
     *
     * @return string Leer, wenn alles stimmt - sonst der Grund
     */
    public static function verify(App $app, string $timestamp, string $signature, string $body): string
    {
        return self::verifyWith(self::publicKey($app), $timestamp, $signature, $body);
    }

    /**
     * Dasselbe, aber ohne App - nur mit dem Schluessel.
     *
     * Die Rechnung braucht die Anwendung nicht, nur einen Schluessel.
     * Getrennt steht sie hier, weil sie sich so mit ECHTEN
     * Schluesselpaaren pruefen laesst: ein nachgebautes $app-Objekt
     * erfuellt die Typangabe nicht, und der Krypto-Teil - der einzige,
     * auf den es wirklich ankommt - waere ungeprueft geblieben.
     *
     * @return string Leer, wenn alles stimmt - sonst der Grund
     */
    public static function verifyWith(string $schluessel, string $timestamp, string $signature, string $body): string
    {
        if (!extension_loaded('sodium')) {
            return 'sodium_missing';
        }

        if ($timestamp === '' || !ctype_digit($timestamp)) {
            return 'no_timestamp';
        }

        if (abs(time() - (int) $timestamp) > self::MAX_AGE) {
            return 'stale';
        }

        if (!self::looksLikeKey($schluessel)) {
            return 'no_key';
        }

        $unterschrift = @hex2bin(trim($signature));

        if ($signature === '' || $unterschrift === false
            || strlen($unterschrift) !== SODIUM_CRYPTO_SIGN_BYTES
        ) {
            return 'bad_signature_format';
        }

        $roh = @hex2bin($schluessel);

        if ($roh === false || strlen($roh) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return 'bad_key_format';
        }

        if (!sodium_crypto_sign_verify_detached($unterschrift, $timestamp . '.' . $body, $roh)) {
            return 'bad_signature';
        }

        return '';
    }

    // -----------------------------------------------------------------
    //  Ein Ereignis lesen
    // -----------------------------------------------------------------

    /**
     * Die gemeinsamen Felder aus einem Throne-Ereignis.
     *
     * Betraege kommen von Throne in der kleinsten Waehrungseinheit -
     * also in Cent. Ohne die Teilung durch 100 stuende im Stream das
     * Hundertfache, und das faellt bei kleinen Betraegen nicht einmal
     * sofort auf.
     *
     * Die Nachricht enthaelt NICHT den Namen des Gegenstands. Der
     * steckt unveraendert im Payload und ist im Alert ein eigener
     * Platzhalter - stuende er auch in der Nachricht, erschiene er
     * doppelt, sobald ein Text beide benutzt.
     *
     * @param array<string, mixed> $data
     * @return array{actor_name: ?string, actor_external_id: ?string, message: ?string, amount: ?string, currency: ?string}
     */
    public static function normalize(string $eventType, array $data): array
    {
        $text = static fn (mixed $wert): ?string => $wert === null || $wert === '' ? null : (string) $wert;

        $gegenstand = trim((string) ($data['item_name'] ?? ''));
        $nachricht = trim((string) ($data['message'] ?? ''));
        $waehrung = $text($data['currency'] ?? null);

        switch ($eventType) {
            case 'gift_purchased':
            case 'contribution_purchased':
                $roh = $data['price'] ?? $data['amount'] ?? null;

                return [
                    'actor_name'        => $text($data['gifter_username'] ?? null),
                    'actor_external_id' => null,
                    'message'           => $nachricht !== '' ? $nachricht : null,
                    'amount'            => self::money($roh),
                    'currency'          => $waehrung,
                ];

            case 'gift_crowdfunded':
                // Kein Kaeufer: das ist der Abschluss von vielen. In der
                // Nachricht steht darum der Gegenstand - sonst stuende
                // die Zeile im Feed ohne jede Auskunft da.
                return [
                    'actor_name'        => null,
                    'actor_external_id' => null,
                    'message'           => $gegenstand !== '' ? $gegenstand : null,
                    'amount'            => self::money($data['price'] ?? null),
                    'currency'          => $waehrung,
                ];

            default:
                return [
                    'actor_name'        => $text($data['gifter_username'] ?? null),
                    'actor_external_id' => null,
                    'message'           => $gegenstand !== '' ? $gegenstand : null,
                    'amount'            => null,
                    'currency'          => $waehrung,
                ];
        }
    }

    /** Cent zu Euro, als Zeichenkette fuer die NUMERIC-Spalte. */
    public static function money(mixed $cent): ?string
    {
        if ($cent === null || $cent === '') {
            return null;
        }

        return number_format(((float) $cent) / 100, 2, '.', '');
    }

    /**
     * Die gelaeufigsten Waehrungen als Zeichen.
     *
     * Throne schickt einen Code ("EUR"). Im Feed steht die Zeile in
     * einer Zeile neben vielen anderen - da liest sich "50.00EUR"
     * schlechter als "50.00€". Was hier fehlt, bleibt als Code
     * stehen: lieber "50.00 CHF" als ein falsches Zeichen.
     */
    private const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'JPY' => '¥',
    ];

    /**
     * Der Betrag, wie er im Feed hinter dem Namen steht.
     *
     * Leer, wenn es keinen gibt - dann steht dort nur der Name, und
     * das ist richtig: ein Ereignis ohne Preis (ein Abonnement, ein
     * blosser Hinweis) soll keine 0,00 vortaeuschen.
     *
     * Die Zahl bleibt, wie sie in der Spalte steht. Sie kommt aus
     * money() und hat dort schon zwei Nachkommastellen.
     */
    public static function amountLabel(mixed $betrag, mixed $waehrung): string
    {
        $betrag = trim((string) $betrag);

        if ($betrag === '' || (float) $betrag <= 0.0) {
            return '';
        }

        $code = strtoupper(trim((string) $waehrung));

        if ($code === '') {
            return $betrag;
        }

        // Ein bekanntes Zeichen klebt am Betrag, ein unbekannter Code
        // steht mit Abstand dahinter - "50.00 CHF" liest sich, "50.00CHF"
        // nicht.
        return isset(self::SYMBOLS[$code])
            ? $betrag . self::SYMBOLS[$code]
            : $betrag . ' ' . $code;
    }

    /**
     * Zu welchem Fall gehoert ein gespeicherter Ereignistyp?
     *
     * Hereinkommen kann beides: "throne.gift_purchased" aus der
     * Datenbank und "gift_purchased" aus dem Webhook.
     */
    public static function caseOf(string $eventType): string
    {
        $kurz = str_starts_with($eventType, 'throne.') ? substr($eventType, 7) : $eventType;

        return self::TYPES[$kurz] ?? '';
    }
}
