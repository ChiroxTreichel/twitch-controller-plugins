<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TipGoals;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Spenden: von der Absicht bis zur Buchung
 * ===================================================================
 *
 * Eine Spende hat fuenf Zustaende, und zwischen zweien davon liegt eine
 * fremde Seite:
 *
 *   open       angelegt, der Anbieter weiss noch nichts
 *   pending    beim Anbieter angelegt, der Spender ist dort
 *   captured   eingezogen, Geld ist geflossen
 *   cancelled  nie zustande gekommen
 *   expired    der Spender kam nicht zurueck
 *
 * Der Uebergang nach "captured" ist der einzige, bei dem Geld bewegt
 * wird - und er passiert genau einmal, in complete(). Die Bedingung im
 * UPDATE laesst von zwei gleichzeitigen Rueckkehrern nur einen durch;
 * dass der Anbieter selbst nicht zweimal einzieht, ist Sache des
 * Anbieter-Plugins (bei PayPal: der Merker als PayPal-Request-Id).
 *
 * Was eine offene Spende NICHT ist: eine Zusage. Wer beim Anbieter
 * abbricht oder den Tab schliesst, laesst eine Zeile stehen, bei der
 * nichts geflossen ist. Solche Zeilen laufen ab und werden
 * weggeraeumt.
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
     * Danach ist sie abgelaufen: der Spender hat den Weg beim Anbieter
     * nicht zu Ende gegangen. Eine halbe Stunde ist grosszuegig - wer
     * dort so lange braucht, kommt nicht mehr wieder.
     */
    public const TTL_SECONDS = 1800;

    /** Wie lange abgeschlossene Zeilen stehen bleiben. */
    public const KEEP_DAYS = 90;

    public const TABLE = 'tip_donations';

    // -----------------------------------------------------------------
    //  Anlegen und nachschlagen
    // -----------------------------------------------------------------

    /**
     * Eine Spende vormerken und ihren Merker zurueckgeben.
     *
     * Der Merker ist zufaellig und nicht die Zeilennummer: er steht
     * gleich in einer Adresse beim Anbieter, und eine fortlaufende
     * Nummer dort verriete, wie viele Spenden es gibt.
     */
    public static function create(
        App $app,
        string $anbieter,
        array $wer,
        float $betrag,
        ?string $nachricht,
        bool $anonym,
        ?int $zielId
    ): string {
        $merker = bin2hex(random_bytes(16));

        $app->db->run(
            'INSERT INTO ' . self::TABLE . '
                 (token, provider, twitch_user_id, twitch_login, twitch_display_name,
                  message, anonymous, amount_eur, goal_id, status, expires_at)
             VALUES (:token, :anbieter, :id, :login, :name, :nachricht, :anonym,
                     CAST(:betrag AS NUMERIC), :ziel, \'open\',
                     now() + (:ttl || \' seconds\')::interval)',
            [
                'token'     => $merker,
                'anbieter'  => $anbieter,
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
    public static function byToken(App $app, string $merker): ?array
    {
        if (trim($merker) === '') {
            return null;
        }

        return $app->db->first('SELECT * FROM ' . self::TABLE . ' WHERE token = :token', ['token' => $merker]);
    }

    /**
     * Eine Spende ueber die Nummer des Anbieters finden.
     *
     * Der Rueckweg bringt oft nur DIE mit - PayPal etwa haengt seine
     * Order-Nummer an und nicht unseren Merker.
     *
     * @return array<string, mixed>|null
     */
    public static function byReference(App $app, string $anbieter, string $nummer): ?array
    {
        if (trim($nummer) === '') {
            return null;
        }

        return $app->db->first(
            'SELECT * FROM ' . self::TABLE . ' WHERE provider = :anbieter AND provider_ref = :nummer',
            ['anbieter' => $anbieter, 'nummer' => $nummer]
        );
    }

    /** Der Spender ist beim Anbieter; der hat ihr diese Nummer gegeben. */
    public static function markPending(App $app, string $merker, string $nummer): void
    {
        $app->db->run(
            'UPDATE ' . self::TABLE . '
                SET provider_ref = :nummer, status = \'pending\'
              WHERE token = :token AND status = \'open\'',
            ['nummer' => $nummer, 'token' => $merker]
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
              WHERE token = :token AND status IN (\'open\', \'pending\')',
            ['token' => $merker]
        );
    }

    /**
     * Eine Spende buchen - genau einmal.
     *
     * Das ist der eine Weg, auf dem Geld in einen Balken kommt, und
     * jeder Anbieter geht ihn: eintragen, Ziel erhoehen, tips.donation
     * ausloesen. Der Anbieter meldet nur, WAS eingezogen wurde.
     *
     * Die Bedingung im WHERE ist das Schloss: zwei gleichzeitige
     * Rueckkehrer (Nachladen, zwei Tabs) lassen nur einen durch, und
     * nur der erhoeht danach das Ziel.
     *
     * Auf das Ziel kommt der NETTObetrag, wenn der Anbieter ihn nennt -
     * der Balken soll nicht mehr zeigen, als ankommt.
     *
     * @return bool true, wenn DIESER Aufruf die Buchung war
     */
    public static function complete(App $app, string $merker, string $einzug, float $brutto, ?float $netto): bool
    {
        $spende = self::byToken($app, $merker);
        if ($spende === null) {
            return false;
        }

        $netto ??= $brutto;

        $gebucht = $app->db->run(
            'UPDATE ' . self::TABLE . '
                SET status = \'captured\', captured_at = now(), provider_tx = :einzug,
                    net_eur = CAST(:netto AS NUMERIC)
              WHERE token = :token AND status <> \'captured\'',
            ['einzug' => $einzug, 'netto' => TipGoals::money($netto), 'token' => $merker]
        )->rowCount() > 0;

        if (!$gebucht) {
            return false;
        }

        TipGoals::applyDonation($app, $netto, $spende['goal_id'] === null ? null : (int) $spende['goal_id']);

        $app->hooks->dispatch('tips.donation', [
            'provider' => (string) $spende['provider'],
            'login'    => (string) $spende['twitch_login'],
            'name'     => ((bool) $spende['anonymous'])
                ? translate('tips.anonymous')
                : (string) $spende['twitch_display_name'],
            'amount'   => $brutto,
            'net'      => $netto,
            'message'  => (string) ($spende['message'] ?? ''),
            'goal_id'  => $spende['goal_id'],
        ]);

        $app->log(TipGoals::SLUG . ': Spende ueber ' . number_format($brutto, 2, '.', '')
            . ' von ' . $spende['twitch_login'] . ' via ' . $spende['provider'] . ' gebucht.');

        return true;
    }

    /**
     * Abgelaufene und alte Zeilen wegraeumen.
     *
     * Offene und schwebende laufen ab - dort ist nichts geflossen.
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
              WHERE status IN (\'open\', \'pending\') AND expires_at < now()'
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
            'SELECT token, provider, twitch_login, twitch_display_name, anonymous, amount_eur,
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
            // vom Zahlungsanbieter zurueck, und bei Strict waere das Cookie beim
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
