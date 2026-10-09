<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Polls;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Wer auf einer Umfrage-Seite abstimmt
 * ===================================================================
 *
 * Ein mit APP_KEY signiertes Cookie mit Twitch-Name und -Kennung -
 * mehr nicht. Der Zuschauer ist kein Benutzer dieses Systems, und eine
 * Sitzungszeile je Neugierigem waere eine Tabelle, die von aussen
 * wachsen kann.
 *
 * Wortgleich zur Musik, zur Spendenseite und zu den Raid-Anfragen, aus
 * denselben Gruenden. Der Unterschied steckt nur im Namen des Cookies
 * und im Salz der Unterschrift: ein Cookie der einen Seite soll auf der
 * anderen nichts bedeuten.
 *
 * Im alten System war es ein unsigniertes Cookie "user" mit der
 * Twitch-ID darin. Wer es im Browser aenderte, stimmte als jemand
 * anderes ab - beliebig oft, mit beliebigen IDs.
 */
final class Visitor
{
    public const COOKIE = 'tc_polls';

    /** Dreissig Tage, wie auf den anderen oeffentlichen Seiten. */
    public const COOKIE_SECONDS = 2592000;

    /**
     * Der Zweck, unter dem der Kern die Twitch-Rueckkehr zuordnet.
     * Siehe den Hook core.oauth.callback in plugin.php.
     */
    public const PURPOSE = 'polls_visitor';

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
        $id = trim((string) ($daten['id'] ?? ''));

        // Ohne Kennung keine Stimme: an ihr haengt, wer schon
        // abgestimmt hat - der Name kann sich aendern.
        if ($login === '' || $id === '') {
            return null;
        }

        return [
            'login'        => $login,
            'display_name' => (string) ($daten['name'] ?? $login),
            'user_id'      => $id,
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
        return hash_hmac('sha256', 'polls|' . $nutzlast, $app->env->require('APP_KEY'));
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
            'polls-csrf|' . (string) ($_COOKIE[self::COOKIE] ?? ''),
            $app->env->require('APP_KEY')
        );
    }

    public static function checkCsrf(App $app, string $token): bool
    {
        return $token !== '' && hash_equals(self::csrfToken($app), $token);
    }
}
