<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Wer auf /music ist
 * ===================================================================
 *
 * Ein mit APP_KEY signiertes Cookie mit Twitch-Name und -Kennung -
 * mehr nicht. Der Zuschauer ist kein Benutzer dieses Systems, und eine
 * Sitzungszeile je Neugierigem waere eine Tabelle, die von aussen
 * wachsen kann.
 *
 * Wortgleich zur Spendenseite und zu den Raid-Anfragen, aus denselben
 * Gruenden. Der Unterschied steckt nur im Namen des Cookies und im
 * Salz der Unterschrift: ein Cookie der einen Seite soll auf der
 * anderen nichts bedeuten.
 *
 * Im alten System waren es ZWEI unsignierte Cookies, twitchid und
 * twitchname, gesetzt von einer eigenen Twitch-App. Unsigniert heisst:
 * wer sie im Browser aendert, ist jemand anderes - und die Bannliste
 * fuer Zuschauer war damit eine Bitte.
 */
final class Visitor
{
    public const COOKIE = 'tc_music';

    /** Dreissig Tage, wie auf der Spendenseite. */
    public const COOKIE_SECONDS = 2592000;

    /**
     * Der Zweck, unter dem der Kern die Twitch-Rueckkehr zuordnet.
     * Siehe den Hook core.oauth.callback in plugin.php.
     */
    public const PURPOSE = 'music_visitor';

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
            // Lax und nicht Strict: der Zuschauer kommt von Twitch
            // zurueck, und bei Strict waere das Cookie beim ersten
            // Aufruf danach nicht dabei.
            'samesite' => 'Lax',
        ]);
    }

    private static function sign(App $app, string $nutzlast): string
    {
        return hash_hmac('sha256', 'music|' . $nutzlast, $app->env->require('APP_KEY'));
    }

    // -----------------------------------------------------------------
    //  Formularmerkmal ohne Sitzung
    // -----------------------------------------------------------------
    //
    //  Der Kern leitet seines aus dem Sitzungscookie ab. Hier gibt es
    //  keine Sitzung - waere es dasselbe Merkmal, waere es fuer jeden
    //  Besucher gleich und damit keines.

    public static function csrfToken(App $app): string
    {
        return hash_hmac(
            'sha256',
            'music-csrf|' . (string) ($_COOKIE[self::COOKIE] ?? ''),
            $app->env->require('APP_KEY')
        );
    }

    public static function checkCsrf(App $app, string $token): bool
    {
        return $token !== '' && hash_equals(self::csrfToken($app), $token);
    }

    /**
     * Hat der Zuschauer die Regeln angenommen?
     *
     * Im alten System ein eigenes Cookie mit der Twitch-ID darin. Das
     * bleibt so: es ist eine Zusage des Besuchers an sich selbst, kein
     * Zustand des Systems - und eine Tabellenzeile je Zuschauer, der
     * einmal auf "gelesen" geklickt hat, waere wieder eine, die von
     * aussen waechst.
     *
     * Die ID steht darin, damit ein Geraet, auf dem sich zwei
     * abwechseln, nicht die Zusage des einen fuer den anderen
     * mitbringt.
     */
    public const RULES_COOKIE = 'tc_music_rules';

    public static function hasAcceptedRules(App $app, ?array $identity): bool
    {
        $id = trim((string) ($identity['user_id'] ?? ''));

        if ($id === '') {
            return false;
        }

        return hash_equals(
            self::rulesValue($app, $id),
            (string) ($_COOKIE[self::RULES_COOKIE] ?? '')
        );
    }

    public static function acceptRules(App $app, string $userId): void
    {
        setcookie(self::RULES_COOKIE, self::rulesValue($app, $userId), [
            'expires'  => time() + 365 * 24 * 3600,
            'path'     => '/',
            'secure'   => str_starts_with($app->url(), 'https://'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Der Wert des Regel-Cookies.
     *
     * Unterschrieben und nicht die blanke ID: sonst traegt man eine
     * fremde ein und hat die Regeln nie gesehen. Das ist kein grosser
     * Schaden - aber wenn man es unterschreiben kann, unterschreibt
     * man es.
     */
    private static function rulesValue(App $app, string $userId): string
    {
        return hash_hmac('sha256', 'music-rules|' . $userId, $app->env->require('APP_KEY'));
    }
}
