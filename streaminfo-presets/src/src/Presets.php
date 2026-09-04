<?php

declare(strict_types=1);

namespace TwitchController\Plugin\StreaminfoPresets;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Die gespeicherten Stream-Titel.
 *
 * Eine Liste von Zeichenketten, nicht mehr. Die Reihenfolge ist die des
 * Betreibers - sortiert wird nicht, denn die Reihenfolge in der Liste
 * ist selbst eine Angabe: was man oft braucht, stellt man nach oben.
 */
final class Presets
{
    public const SLUG = 'streaminfo-presets';

    /**
     * Wie lang ein Titel sein darf.
     *
     * Dieselbe Grenze wie bei Twitch. Eine Vorlage, die laenger ist als
     * der Titel sein darf, waere beim Auswaehlen sofort abgeschnitten -
     * und man saehe nicht, wo.
     */
    public const MAX_TITLE = 140;

    /**
     * Wie viele Vorlagen hoechstens.
     *
     * Nicht wegen Speicherplatz, sondern wegen der Auswahlliste: was
     * darin steht, muss man im Stream in zwei Sekunden finden.
     */
    public const MAX_PRESETS = 50;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    /**
     * @return list<string>
     */
    public static function all(App $app): array
    {
        $roh = $app->settings->get('presets', [], self::scope());

        // Geputzt beim LESEN und nicht nur beim Speichern: die Liste
        // kann aus einer aelteren Fassung kommen oder von Hand in der
        // Datenbank stehen, und eine Auswahlliste im laufenden Stream
        // ist der falsche Ort fuer eine Ueberraschung.
        return self::clean(is_array($roh) ? $roh : []);
    }

    /**
     * @param array<int|string, mixed> $roh
     * @return list<string>
     */
    public static function clean(array $roh): array
    {
        $sauber = [];

        foreach ($roh as $eintrag) {
            if (!is_string($eintrag)) {
                continue;
            }

            $eintrag = self::normalize($eintrag);
            if ($eintrag === '') {
                continue;
            }

            // Doppelte weg: zwei gleiche Zeilen in der Auswahlliste sind
            // nicht zu unterscheiden, und wer eine davon nimmt, weiss
            // nicht, welche er erwischt hat.
            if (!in_array($eintrag, $sauber, true)) {
                $sauber[] = $eintrag;
            }

            if (count($sauber) >= self::MAX_PRESETS) {
                break;
            }
        }

        return $sauber;
    }

    /**
     * @param array<int|string, mixed> $presets
     * @return list<string> die Liste, wie sie danach dasteht
     */
    public static function save(App $app, array $presets): array
    {
        $sauber = self::clean($presets);
        $app->settings->set('presets', $sauber, self::scope());

        return $sauber;
    }

    /**
     * Ein Titel, wie er in der Liste stehen darf.
     *
     * Umbrueche und doppelte Leerzeichen werden zu einem Leerzeichen -
     * ein Titel ist eine Zeile, und wer aus einem Textfeld einfuegt, hat
     * leicht einen Umbruch darin.
     */
    public static function normalize(string $titel): string
    {
        $titel = trim(preg_replace('/\s+/u', ' ', $titel) ?? '');

        /*
         * Gekuerzt mit einem Muster und nicht mit mb_substr().
         *
         * Der uebliche Rueckfall auf substr(), wenn mbstring fehlt,
         * schneidet nach Bytes: eine Vorlage, deren letztes erlaubtes
         * Zeichen ein Umlaut ist, endete dann mit einem halben Zeichen.
         * .{0,140} mit /u zaehlt Zeichen und braucht mbstring nicht.
         */
        return preg_match('/^.{0,' . self::MAX_TITLE . '}/us', $titel, $treffer) === 1
            ? $treffer[0]
            : '';
    }
}
