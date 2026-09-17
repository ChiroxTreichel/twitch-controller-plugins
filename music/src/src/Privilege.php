<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;
use Throwable;

/**
 * ===================================================================
 *  Wer auf /music mehr darf als die anderen
 * ===================================================================
 *
 * Im alten System stand dafuer eine Liste von zwei Twitch-IDs im Code:
 *
 *   $isAdmin = in_array($_COOKIE['twitchid'], ['59374507', '39023214']);
 *
 * Wer dazukommen sollte, brauchte eine Codeaenderung, und wer ging,
 * blieb drin - es hat ja niemand nachgesehen.
 *
 * Hier ist es ein Recht wie jedes andere. Die Bruecke dazwischen ist
 * die Twitch-Kennung: der Besucher auf /music ist kein angemeldeter
 * Benutzer dieses Systems, aber wenn es zu seiner Kennung EINEN gibt,
 * gelten dessen Rechte.
 *
 * Das ist der springende Punkt und der Grund fuer diese Klasse: die
 * oeffentliche Seite fragt nicht "bist du angemeldet", sondern "gibt
 * es dich hier drin, und darfst du das".
 */
final class Privilege
{
    /**
     * "Grenzen ignorieren": darf wuenschen, auch wenn die
     * Songwuensche ausgeschaltet sind, und ohne Wartezeit dazwischen.
     *
     * Beides zusammen in EINEM Recht und nicht in zweien: es ist eine
     * Rolle, nicht zwei. Wer Musik einbauen darf, waehrend die
     * Wuensche fuer die Zuschauer zu sind, soll nicht daneben noch
     * fuenfzehn Minuten warten.
     *
     * Was es NICHT aufhebt: die Bannliste. Ein gesperrter Titel ist
     * eine Entscheidung ueber den Inhalt und keine Grenze fuer den
     * Andrang - und wer sie aufheben will, nimmt ihn von der Liste.
     */
    public const BYPASS = 'Music.Limits.Ignore';

    /**
     * Hat der Besucher dieses Recht?
     *
     * Ohne Kennung nein - und zwar ohne Abfrage: eine leere Kennung
     * gegen die Benutzertabelle zu halten, hiesse, dass ein Besucher
     * ohne Anmeldung zufaellig einen Treffer haben koennte.
     */
    public static function has(App $app, ?array $identity, string $recht): bool
    {
        $id = trim((string) ($identity['user_id'] ?? ''));
        $login = strtolower(trim((string) ($identity['login'] ?? '')));

        if ($id === '' && $login === '') {
            return false;
        }

        try {
            /*
             * Ueber die Twitch-ID UND den Namen: die ID ist der
             * Primaerschluessel und aendert sich nie, aber sie steht
             * erst im Cookie, seit es das gibt. Der Name ist der
             * Rueckfall und wird kleingeschrieben verglichen - Twitch
             * unterscheidet dort nicht.
             */
            $benutzer = $app->db->first(
                'SELECT twitch_id, login, role, permissions, permission_role
                   FROM users
                  WHERE twitch_id = :id OR lower(login) = :login
                  LIMIT 1',
                ['id' => $id, 'login' => $login]
            );
        } catch (Throwable $e) {
            // Die oeffentliche Seite darf an einer Rechtefrage nicht
            // scheitern. Im Zweifel hat er das Recht nicht - das ist
            // die Antwort, die nichts kaputtmacht.
            $app->log('Musik: Rechtepruefung fehlgeschlagen - ' . $e->getMessage());

            return false;
        }

        if ($benutzer === null) {
            return false;
        }

        // Der Kanalinhaber darf alles, hier wie ueberall.
        if ((string) ($benutzer['role'] ?? '') === 'superadmin') {
            return true;
        }

        $rechte = $app->auth->permissionsOf([
            'permissions'     => json_decode((string) ($benutzer['permissions'] ?? '[]'), true) ?: [],
            'permission_role' => (string) ($benutzer['permission_role'] ?? ''),
        ]);

        return in_array($recht, $rechte, true);
    }

    /** Kurzform fuer den einen Fall, um den es hier geht. */
    public static function mayBypass(App $app, ?array $identity): bool
    {
        return self::has($app, $identity, self::BYPASS);
    }
}
