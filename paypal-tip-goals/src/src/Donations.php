<?php

declare(strict_types=1);

namespace TwitchController\Plugin\PaypalTipGoals;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Spenden: von der Absicht bis zur Buchung
 * ===================================================================
 *
 * Eine Spende hat vier Zustaende, und zwischen zweien davon liegt eine
 * fremde Seite:
 *
 *   offen        angelegt, PayPal weiss noch nichts
 *   genehmigt    Order liegt bei PayPal, der Spender ist dort
 *   gebucht      eingezogen, Geld ist geflossen
 *   abgebrochen  nie zustande gekommen
 *
 * Der Uebergang nach "gebucht" ist der einzige, bei dem Geld bewegt
 * wird - und er passiert genau einmal. Dafuer sorgen zwei Dinge: der
 * Merker geht als PayPal-Request-Id mit (PayPal zieht dann auch bei
 * einem zweiten Versuch nur einmal ein), und die Buchung hier prueft
 * den Zustand, bevor sie das Ziel erhoeht.
 *
 * Was eine offene Spende NICHT ist: eine Zusage. Wer bei PayPal
 * abbricht oder den Tab schliesst, laesst eine genehmigte Order
 * stehen - dann ist nichts geflossen. Solche Zeilen laufen ab und
 * werden weggeraeumt.
 *
 * Wie bei den Raid-Anfragen bekommt der Besucher KEIN Konto in dieser
 * Verwaltung, sondern ein signiertes Cookie mit seinem Twitch-Namen.
 * Das Token aus seiner Anmeldung wird weggeworfen: gebraucht wird die
 * Auskunft, wer da ist.
 */
final class Donations
{
    /** Der Zweck der OAuth-Runde von der oeffentlichen Seite. */
    public const PURPOSE = 'tip_donation';

    /** Das Cookie mit der Kennung des Spenders. */
    public const COOKIE = 'tc_tips';

    /** Wie lange dieses Cookie gilt: 30 Tage. */
    public const COOKIE_SECONDS = 2592000;

    /**
     * Wie lange eine offene Spende gilt.
     *
     * Danach ist sie abgelaufen: der Spender hat den Weg zu PayPal
     * nicht zu Ende gegangen. Eine halbe Stunde ist grosszuegig - wer
     * dort so lange braucht, kommt nicht mehr wieder.
     */
    public const TTL_SECONDS = 1800;

    /** Wie lange abgeschlossene Zeilen stehen bleiben. */
    public const KEEP_DAYS = 90;

    public const TABLE = 'pp_donation_intents';

    // -----------------------------------------------------------------
    //  Anlegen und nachschlagen
    // -----------------------------------------------------------------

    /**
     * Eine Spende vormerken und ihren Merker zurueckgeben.
     *
     * Der Merker ist zufaellig und nicht die Zeilennummer: er steht
     * gleich in einer Adresse bei PayPal, und eine fortlaufende Nummer
     * dort verriete, wie viele Spenden es gibt.
     */
    public static function create(
        App $app,
        array $wer,
        float $betrag,
        ?string $nachricht,
        bool $anonym,
        ?int $zielId
    ): string {
        $merker = bin2hex(random_bytes(16));

        $app->db->run(
            'INSERT INTO ' . self::TABLE . '
                 (token, twitch_user_id, twitch_login, twitch_display_name,
                  message, anonymous, amount_eur, goal_id, status, expires_at)
             VALUES (:token, :id, :login, :name, :nachricht, :anonym,
                     CAST(:betrag AS NUMERIC), :ziel, \'open\',
                     now() + (:ttl || \' seconds\')::interval)',
            [
                'token'     => $merker,
                'id'        => (string) ($wer['user_id'] ?? ''),
                'login'     => (string) ($wer['login'] ?? ''),
                'name'      => (string) ($wer['display_name'] ?? ''),
                'nachricht' => $nachricht,
                'anonym'    => $anonym,
                'betrag'    => TipGoals::money($betrag),
                'ziel'      => $zielId === null ? null : (string) $zielId,
                'ttl'       => (string) self::TTL_SECONDS,
            ]
        );

        return $merker;
    }

    /** @return array<string, mixed>|null */
    public static function byOrder(App $app, string $orderId): ?array
    {
        if (trim($orderId) === '') {
            return null;
        }

        return $app->db->first(
            'SELECT * FROM ' . self::TABLE . ' WHERE paypal_order_id = :id',
            ['id' => $orderId]
        );
    }

    public static function markApproved(App $app, string $merker, string $orderId): void
    {
        $app->db->run(
            'UPDATE ' . self::TABLE . '
                SET paypal_order_id = :order, status = \'approved\'
              WHERE token = :token AND status = \'open\'',
            ['order' => $orderId, 'token' => $merker]
        );
    }

    public static function markCancelled(App $app, string $merker): void
    {
        // Nur was noch nicht gebucht ist: eine gebuchte Spende ist
        // Geld, das geflossen ist, und die faellt nicht zurueck, weil
        // jemand auf "zurueck" drueckt.
        $app->db->run(
            'UPDATE ' . self::TABLE . '
                SET status = \'cancelled\'
              WHERE token = :token AND status IN (\'open\', \'approved\')',
            ['token' => $merker]
        );
    }

    /**
     * Eine Spende als gebucht eintragen - genau einmal.
     *
     * Die Bedingung im WHERE ist das Schloss: zwei gleichzeitige
     * Rueckkehrer (Nachladen, zwei Tabs) lassen nur einen durch, und
     * nur der erhoeht danach das Ziel.
     *
     * @return bool true, wenn DIESER Aufruf die Buchung war
     */
    public static function markCaptured(App $app, string $merker, string $captureId): bool
    {
        return $app->db->run(
            'UPDATE ' . self::TABLE . '
                SET status = \'captured\', captured_at = now(), paypal_capture_id = :capture
              WHERE token = :token AND status <> \'captured\'',
            ['capture' => $captureId, 'token' => $merker]
        )->rowCount() > 0;
    }

    /**
     * Abgelaufene und alte Zeilen wegraeumen.
     *
     * Offene und genehmigte laufen ab - dort ist nichts geflossen.
     * Gebuchte bleiben 90 Tage: sie sind der Beleg, wenn jemand fragt,
     * wo seine Spende geblieben ist.
     *
     * Hoechstens einmal am Tag, wie ueberall: der Worker tickt alle 15
     * Sekunden.
     */
    public static function cleanup(App $app): void
    {
        $zuletzt = $app->settings->int('cleaned_at', 0, TipGoals::scope());
        if (time() - $zuletzt < 86400) {
            return;
        }

        $app->settings->set('cleaned_at', time(), TipGoals::scope());

        $app->db->run(
            'UPDATE ' . self::TABLE . '
                SET status = \'expired\'
              WHERE status IN (\'open\', \'approved\') AND expires_at < now()'
        );

        $app->db->run(
            'DELETE FROM ' . self::TABLE . '
              WHERE status <> \'captured\'
                AND created_at < now() - (:tage || \' days\')::interval',
            ['tage' => (string) self::KEEP_DAYS]
        );
    }

    /**
     * Die letzten Spenden - fuer den Reiter in der Verwaltung.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(App $app, int $anzahl = 25): array
    {
        return $app->db->all(
            'SELECT token, twitch_login, twitch_display_name, anonymous, amount_eur,
                    message, goal_id, status, created_at, captured_at
               FROM ' . self::TABLE . '
              ORDER BY created_at DESC
              LIMIT ' . max(1, min(200, $anzahl))
        );
    }

    // -----------------------------------------------------------------
    //  Wer ist auf der oeffentlichen Seite?
    // -----------------------------------------------------------------
    //
    //  Wortgleich zu den Raid-Anfragen, und aus denselben Gruenden: ein
    //  signiertes Cookie statt einer Sitzung in der Datenbank. Der
    //  Spender ist kein Benutzer dieses Systems, und eine Zeile je
    //  Neugierigem waere eine Tabelle, die von aussen wachsen kann.

    /** @return array{login: string, display_name: string, user_id: string}|null */
    public static function identity(App $app): ?array
    {
        $roh = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($roh === '') {
            return null;
        }

        $teile = explode('.', $roh, 2);
        if (count($teile) !== 2) {
            return null;
        }

        $nutzlast = strtr($teile[0], '-_', '+/');
        $nutzlast .= str_repeat('=', (4 - strlen($nutzlast) % 4) % 4);

        if (!hash_equals(self::sign($app, $nutzlast), $teile[1])) {
            return null;
        }

        $daten = json_decode((string) base64_decode($nutzlast, true), true);
        if (!is_array($daten)) {
            return null;
        }

        // Das Ablaufdatum wird HIER geprueft und nicht dem Browser
        // ueberlassen: eines, das der Browser verwaltet, verlaengert
        // man, indem man den Wert abschreibt.
        if (time() - (int) ($daten['ts'] ?? 0) > self::COOKIE_SECONDS) {
            return null;
        }

        $login = strtolower(trim((string) ($daten['login'] ?? '')));
        if ($login === '') {
            return null;
        }

        return [
            'login'        => $login,
            'display_name' => (string) ($daten['name'] ?? $login),
            'user_id'      => (string) ($daten['id'] ?? ''),
        ];
    }

    public static function remember(App $app, string $login, string $name, string $userId): void
    {
        $nutzlast = base64_encode((string) json_encode([
            'login' => strtolower(trim($login)),
            'name'  => $name,
            'id'    => $userId,
            'ts'    => time(),
        ]));

        self::cookie(
            $app,
            rtrim(strtr($nutzlast, '+/', '-_'), '=') . '.' . self::sign($app, $nutzlast),
            time() + self::COOKIE_SECONDS
        );
    }

    public static function forget(App $app): void
    {
        self::cookie($app, '', time() - 3600);
    }

    private static function cookie(App $app, string $wert, int $bis): void
    {
        setcookie(self::COOKIE, $wert, [
            'expires'  => $bis,
            'path'     => '/',
            'secure'   => str_starts_with($app->url(), 'https://'),
            'httponly' => true,
            // Lax und nicht Strict: der Spender kommt von Twitch und
            // von PayPal zurueck, und bei Strict waere das Cookie beim
            // ersten Aufruf danach nicht dabei.
            'samesite' => 'Lax',
        ]);
    }

    private static function sign(App $app, string $nutzlast): string
    {
        return hash_hmac('sha256', 'tips|' . $nutzlast, $app->env->require('APP_KEY'));
    }

    // -----------------------------------------------------------------
    //  CSRF ohne Sitzung
    // -----------------------------------------------------------------
    //
    //  Der Kern leitet sein Formular-Merkmal aus dem Sitzungscookie ab.
    //  Auf dieser Seite gibt es keine Sitzung - waere es dasselbe
    //  Merkmal, waere es fuer jeden Besucher gleich und damit keines.

    public static function csrfToken(App $app): string
    {
        return hash_hmac(
            'sha256',
            'tips-csrf|' . (string) ($_COOKIE[self::COOKIE] ?? ''),
            $app->env->require('APP_KEY')
        );
    }

    public static function checkCsrf(App $app, string $kandidat): bool
    {
        return $kandidat !== '' && hash_equals(self::csrfToken($app), $kandidat);
    }
}
