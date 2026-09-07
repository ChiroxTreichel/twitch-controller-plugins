<?php

declare(strict_types=1);

namespace TwitchController\Plugin\RaidsRequests;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Twitch\TokenStore;

/**
 * ===================================================================
 *  Raid-Anfragen
 * ===================================================================
 *
 * Ein Streamer oeffnet /raidme, meldet sich mit Twitch an und sagt
 * "raide mich". Der Kanalinhaber nimmt an oder lehnt ab; wer
 * angenommen ist, taucht im Live-Reiter von Raids auf, sobald er
 * streamt.
 *
 * Zwei Dinge sind hier anders als im Rest des Systems:
 *
 * 1. Die Seite ist OEFFENTLICH. Sie hat keinen Benutzer und darf
 *    keinen anlegen - wer sich dort anmeldet, bekommt kein Konto in
 *    dieser Verwaltung, sondern nur ein signiertes Cookie mit seinem
 *    Namen. Siehe identity() und remember().
 *
 * 2. Das Twitch-Token aus dieser Anmeldung wird WEGGEWORFEN. Gebraucht
 *    wird nur die Auskunft, wer da ist; ein gespeichertes Token eines
 *    fremden Kanals waere ein Schluessel, fuer den es kein Schloss
 *    gibt.
 */
final class Requests
{
    public const SLUG = 'raids-requests';

    /** Der Zweck der OAuth-Runde von der oeffentlichen Seite. */
    public const PURPOSE = 'raid_request';

    /** Das Cookie mit der Kennung des Besuchers. */
    public const COOKIE = 'tc_raidme';

    /** Wie lange dieses Cookie gilt: 30 Tage. */
    public const COOKIE_SECONDS = 2592000;

    /**
     * Wie lange entschiedene Anfragen stehen bleiben.
     *
     * Angenommene braucht man, solange geraidet wird; abgelehnte, damit
     * der Anfrager auf seiner Seite sieht, woran er ist. Nach zwei
     * Wochen weiss das niemand mehr, und die Tabelle soll nicht
     * mitwachsen.
     */
    public const KEEP_DAYS = 14;

    /** Wie viele offene Anfragen ueberhaupt Sinn haben - eine Notbremse. */
    public const MAX_OPEN = 200;

    private static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Offen oder geschlossen
    // -----------------------------------------------------------------

    /**
     * Nimmt die oeffentliche Seite gerade Anfragen an?
     *
     * Vorgabe ja: wer das Plugin installiert, will Anfragen. Der
     * Schalter ist fuer die Abende, an denen man keine will - und
     * dafuer soll man nicht deinstallieren muessen.
     */
    public static function open(App $app): bool
    {
        return $app->settings->bool('open', true, self::scope());
    }

    public static function setOpen(App $app, bool $offen): void
    {
        $app->settings->set('open', $offen, self::scope());
    }

    // -----------------------------------------------------------------
    //  Die Liste
    // -----------------------------------------------------------------

    /**
     * Was der Kanalinhaber sieht: offene und angenommene Anfragen.
     *
     * Abgelehnte stehen NICHT hier. Sie bleiben in der Tabelle, damit
     * der Anfrager auf seiner Seite sieht, woran er ist - aber auf der
     * Verwaltungsseite waeren sie eine Liste von Entscheidungen, die
     * man schon getroffen hat.
     *
     * Offene zuerst, darin die aelteste oben: wer zuerst gefragt hat,
     * wartet am laengsten.
     *
     * @return list<array{login: string, display_name: string, status: string, requested_at: string}>
     */
    public static function visible(App $app): array
    {
        $rows = $app->db->all(
            "SELECT login, display_name, status, requested_at
               FROM raid_requests
              WHERE status IN ('pending', 'accepted')
              ORDER BY status = 'accepted', requested_at"
        );

        return array_map(static fn (array $row): array => [
            'login'        => (string) $row['login'],
            'display_name' => (string) ($row['display_name'] ?: $row['login']),
            'status'       => (string) $row['status'],
            'requested_at' => (string) $row['requested_at'],
        ], $rows);
    }

    /** Wie viele noch auf eine Entscheidung warten. */
    public static function pendingCount(App $app): int
    {
        return (int) $app->db->value("SELECT count(*) FROM raid_requests WHERE status = 'pending'");
    }

    /**
     * Die Logins der angenommenen Anfragen - fuer den Live-Reiter.
     *
     * @return list<string>
     */
    public static function acceptedLogins(App $app): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['login'],
            $app->db->all("SELECT login FROM raid_requests WHERE status = 'accepted'")
        );
    }

    /** @return array{login: string, display_name: string, status: string}|null */
    public static function find(App $app, string $login): ?array
    {
        $login = self::normalizeLogin($login);
        if ($login === '') {
            return null;
        }

        $row = $app->db->first(
            'SELECT login, display_name, status FROM raid_requests WHERE login = :login',
            ['login' => $login]
        );

        if ($row === null) {
            return null;
        }

        return [
            'login'        => (string) $row['login'],
            'display_name' => (string) ($row['display_name'] ?: $row['login']),
            'status'       => (string) $row['status'],
        ];
    }

    // -----------------------------------------------------------------
    //  Anmelden und entscheiden
    // -----------------------------------------------------------------

    /**
     * Eine Anfrage anlegen.
     *
     * Eine noch offene Anfrage blockiert eine neue - sonst waere der
     * Knopf ein Weg, die Liste vollzuschreiben, waehrend der
     * Kanalinhaber noch ueberlegt. Eine entschiedene wird ersetzt: wer
     * beim letzten Mal abgelehnt wurde, darf sich morgen wieder
     * melden.
     *
     * @return array{ok: bool, error: string}
     */
    public static function submit(App $app, string $login, string $name, string $userId): array
    {
        $login = self::normalizeLogin($login);
        if ($login === '') {
            return ['ok' => false, 'error' => translate('raids_req.error.unknown')];
        }

        if (!self::open($app)) {
            return ['ok' => false, 'error' => translate('raids_req.closed')];
        }

        if ($login === self::ownLogin($app)) {
            return ['ok' => false, 'error' => translate('raids_req.error.self')];
        }

        $vorhanden = self::find($app, $login);
        if ($vorhanden !== null && $vorhanden['status'] === 'pending') {
            // Kein Fehler: es ist schon so, wie er es haben will.
            return ['ok' => true, 'error' => ''];
        }

        // Notbremse. Nicht gegen den einzelnen Anfrager - dagegen hilft
        // die offene Anfrage oben -, sondern gegen den Fall, dass die
        // Adresse irgendwo landet, wo sie tausend Leute oeffnen.
        if ($vorhanden === null && self::pendingCount($app) >= self::MAX_OPEN) {
            return ['ok' => false, 'error' => translate('raids_req.error.too_many')];
        }

        $app->db->run(
            "INSERT INTO raid_requests (login, user_id, display_name, status, requested_at, decided_at)
                  VALUES (:login, :id, :name, 'pending', now(), NULL)
             ON CONFLICT (login) DO UPDATE
                SET user_id      = EXCLUDED.user_id,
                    display_name = EXCLUDED.display_name,
                    status       = 'pending',
                    requested_at = now(),
                    decided_at   = NULL",
            ['login' => $login, 'id' => $userId, 'name' => $name !== '' ? $name : $login]
        );

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Annehmen oder ablehnen.
     *
     * Abgelehnt wird nicht geloescht: der Anfrager soll auf seiner
     * Seite lesen koennen, woran er ist. Weggeraeumt wird spaeter, von
     * cleanup().
     */
    public static function setStatus(App $app, string $login, string $status): bool
    {
        $login = self::normalizeLogin($login);
        if ($login === '' || !in_array($status, ['accepted', 'declined'], true)) {
            return false;
        }

        // rowCount() und nicht "danach nachsehen": ob es die Zeile gab,
        // sagt das UPDATE selbst, und eine zweite Abfrage koennte etwas
        // anderes sehen als die erste.
        return $app->db->run(
            'UPDATE raid_requests
                SET status = :status, decided_at = now()
              WHERE login = :login',
            ['status' => $status, 'login' => $login]
        )->rowCount() > 0;
    }

    /**
     * Entschiedene Anfragen wegraeumen, die alt genug sind.
     *
     * Hoechstens einmal am Tag: der Worker tickt alle 15 Sekunden, und
     * eine Aufraeumabfrage je Tick waere viertausend am Tag fuer eine
     * Tabelle, in der sich meistens nichts geaendert hat.
     */
    public static function cleanup(App $app): void
    {
        $zuletzt = $app->settings->int('cleaned_at', 0, self::scope());
        if (time() - $zuletzt < 86400) {
            return;
        }

        $app->settings->set('cleaned_at', time(), self::scope());

        $app->db->run(
            "DELETE FROM raid_requests
              WHERE status <> 'pending'
                AND decided_at IS NOT NULL
                AND decided_at < now() - (:tage || ' days')::interval",
            ['tage' => (string) self::KEEP_DAYS]
        );
    }

    // -----------------------------------------------------------------
    //  Die Bilder
    // -----------------------------------------------------------------

    /**
     * Profilbilder frisch von Twitch, in EINEM Aufruf.
     *
     * Nicht gespeichert: gespeichert veraltet es, sobald jemand sein
     * Bild wechselt - und dann braeuchte es Code, der es nachzieht.
     * Die Liste ist kurz, und die Seite oeffnet man selten.
     *
     * Antwortet Twitch nicht, kommt zurueck, was da ist: eine Seite
     * ohne Bilder ist besser als eine mit einer Fehlermeldung.
     *
     * @param list<string> $logins
     * @return array<string, string> Login => Bildadresse
     */
    public static function profiles(App $app, array $logins): array
    {
        $logins = array_values(array_unique(array_filter(array_map(
            static fn (string $l): string => self::normalizeLogin($l),
            $logins
        ))));

        if ($logins === []) {
            return [];
        }

        $bilder = [];

        // Twitch nimmt hundert Namen je Aufruf. Mehr als hundert offene
        // Anfragen sind ein anderes Problem als ein fehlendes Bild.
        foreach (array_chunk($logins, 100) as $haeufchen) {
            try {
                $antwort = $app->twitch->api()
                    ->as(TokenStore::BROADCASTER)
                    ->get('users', ['login' => $haeufchen]);
            } catch (Throwable) {
                return $bilder;
            }

            if (!$antwort->ok()) {
                return $bilder;
            }

            foreach (($antwort->json['data'] ?? []) as $zeile) {
                if (!is_array($zeile)) {
                    continue;
                }

                $login = self::normalizeLogin((string) ($zeile['login'] ?? ''));
                if ($login !== '') {
                    $bilder[$login] = (string) ($zeile['profile_image_url'] ?? '');
                }
            }
        }

        return $bilder;
    }

    // -----------------------------------------------------------------
    //  Wer ist auf der oeffentlichen Seite?
    // -----------------------------------------------------------------

    /**
     * Die Kennung des Besuchers aus seinem Cookie.
     *
     * Ein signiertes Cookie und keine Sitzung in der Datenbank: der
     * Besucher ist kein Benutzer dieses Systems, und eine Zeile je
     * Neugierigem waere eine Tabelle, die von aussen wachsen kann.
     * Was drinsteht, ist ohnehin oeffentlich - sein Twitch-Name -, es
     * darf nur niemand FREMDES hineinschreiben. Dafuer die Signatur.
     *
     * @return array{login: string, display_name: string, user_id: string}|null
     */
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

        // Das Cookie laeuft ab, und zwar HIER und nicht nur im Browser:
        // ein Ablaufdatum, das der Browser verwaltet, verlaengert man,
        // indem man den Wert abschreibt und neu setzt.
        if (time() - (int) ($daten['ts'] ?? 0) > self::COOKIE_SECONDS) {
            return null;
        }

        $login = self::normalizeLogin((string) ($daten['login'] ?? ''));
        if ($login === '') {
            return null;
        }

        return [
            'login'        => $login,
            'display_name' => (string) ($daten['name'] ?? $login),
            'user_id'      => (string) ($daten['id'] ?? ''),
        ];
    }

    /** Die Kennung ins Cookie schreiben. */
    public static function remember(App $app, string $login, string $name, string $userId): void
    {
        $nutzlast = base64_encode((string) json_encode([
            'login' => self::normalizeLogin($login),
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
            // Lax und nicht Strict: der Besucher kommt von Twitch
            // zurueck, und bei Strict waere das Cookie beim ersten
            // Aufruf danach nicht dabei.
            'samesite' => 'Lax',
        ]);
    }

    private static function sign(App $app, string $nutzlast): string
    {
        return hash_hmac('sha256', 'raidme|' . $nutzlast, $app->env->require('APP_KEY'));
    }

    // -----------------------------------------------------------------
    //  CSRF auf der oeffentlichen Seite
    // -----------------------------------------------------------------
    //
    //  Der Kern leitet sein Formular-Merkmal aus dem Sitzungscookie
    //  ab. Auf dieser Seite gibt es keine Sitzung - waere es dasselbe
    //  Merkmal, waere es fuer jeden Besucher gleich und damit keines.
    //
    //  Also aus dem Besucher-Cookie. Dasselbe Verfahren, anderes
    //  Geheimnis: wer kein Cookie hat, hat auch keinen Knopf.

    public static function csrfToken(App $app): string
    {
        return hash_hmac(
            'sha256',
            'raidme-csrf|' . (string) ($_COOKIE[self::COOKIE] ?? ''),
            $app->env->require('APP_KEY')
        );
    }

    public static function checkCsrf(App $app, string $kandidat): bool
    {
        return $kandidat !== '' && hash_equals(self::csrfToken($app), $kandidat);
    }

    // -----------------------------------------------------------------
    //  Kleinigkeiten
    // -----------------------------------------------------------------

    /** Der Login des eigenen Kanals - den kann man nicht anfragen. */
    public static function ownLogin(App $app): string
    {
        return self::normalizeLogin($app->settings->string('twitch_broadcaster_login'));
    }

    /** Der Anzeigename des eigenen Kanals, fuer die oeffentliche Seite. */
    public static function ownName(App $app): string
    {
        $name = $app->settings->string('twitch_broadcaster_name');

        return $name !== '' ? $name : $app->settings->string('twitch_broadcaster_login');
    }

    public static function normalizeLogin(string $login): string
    {
        return strtolower(trim($login));
    }
}
