<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Streaminfo;

use TwitchController\Core\Config\Settings;

/**
 * Die Grenzen und das Putzen des Titels.
 *
 * Das Plugin speichert nichts: Titel und Kategorie stehen bei Twitch
 * und werden dort gelesen und geschrieben. Siehe install.php, warum
 * nichts davon zwischengespeichert wird.
 *
 * Der Scope steht trotzdem hier - kommt spaeter eine Einstellung dazu,
 * ist der Ort schon bestimmt.
 */
final class Config
{
    public const SLUG = 'streaminfo';

    /**
     * Twitchs Grenze fuer den Stream-Titel.
     *
     * Laenger nimmt die API nicht an, und ein Feld, das mehr erlaubt
     * als hinterher durchgeht, zeigt den Fehler erst beim Speichern -
     * im Stream also zum unpassendsten Zeitpunkt.
     */
    public const MAX_TITLE = 140;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    /**
     * Ein Titel, wie er zu Twitch gehen darf.
     *
     * Zeilenumbrueche und doppelte Leerzeichen werden zu einem
     * Leerzeichen: ein Titel ist eine Zeile, und wer aus einem
     * Textfeld einfuegt, hat leicht einen Umbruch darin.
     */
    public static function normalizeTitle(string $titel): string
    {
        $titel = trim(preg_replace('/\s+/u', ' ', $titel) ?? '');

        /*
         * Gekuerzt mit einem Muster und nicht mit mb_substr().
         *
         * Der uebliche Weg dafuer waere mb_substr() mit substr() als
         * Rueckfall, wenn mbstring fehlt. Genau dieser Rueckfall
         * schneidet aber nach Bytes: ein Titel, dessen 140. Zeichen ein
         * Umlaut ist, endet dann mit einem halben Zeichen - Twitch
         * lehnt das ab, und die Fehlermeldung nennt den Grund nicht.
         *
         * .{0,140} mit /u zaehlt Zeichen, nicht Bytes, und braucht
         * mbstring nicht.
         */
        return preg_match('/^.{0,' . self::MAX_TITLE . '}/us', $titel, $treffer) === 1
            ? $treffer[0]
            : '';
    }
}
