<?php

declare(strict_types=1);

namespace TwitchController\Plugin\StreaminfoTags;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * ===================================================================
 *  Tags vor dem Titel
 * ===================================================================
 *
 * Aus einer Liste eigener Tags und einem Titel wird
 *
 *     [VTuber][German] Das ist mein Titel
 *
 * Die Tags stehen also IM Titel und nicht in Twitchs eigenem Tag-Feld.
 * Das ist so gewollt: es ist das, was man im Verzeichnis und im Chat
 * liest, und es funktioniert, ohne dass Twitch die Tags kennt.
 *
 * Der empfindliche Teil ist das Zurueckrechnen. Der Titel bei Twitch
 * ist die einzige Wahrheit darueber, welche Tags gerade an sind - ein
 * zweiter Ort dafuer waere sofort falsch, sobald jemand den Titel
 * anderswo aendert. Also wird beim Anzeigen aus
 *
 *     [VTuber][German] Das ist mein Titel
 *
 * wieder "Das ist mein Titel" plus die Haken bei VTuber und German.
 * Bliebe ein Vorsatz stehen, wuerde ihn das naechste Speichern ein
 * zweites Mal davorsetzen - und beim naechsten Mal wieder eins mehr.
 */
final class Tags
{
    public const SLUG = 'streaminfo-tags';

    /**
     * Wie lang ein Tag sein darf.
     *
     * Kurz gehalten: was in eckigen Klammern vor dem Titel steht, nimmt
     * Platz weg, den der Titel braucht. Zwei lange Tags, und von 140
     * Zeichen bleibt nicht viel.
     */
    public const MAX_TAG = 24;

    /** Wie viele Tags hoechstens in der Liste stehen. */
    public const MAX_TAGS = 30;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    /**
     * Die festgelegten Tags, in der Reihenfolge des Betreibers.
     *
     * Diese Reihenfolge ist eine Angabe und wird nicht sortiert: sie
     * bestimmt, in welcher Folge die Tags vor dem Titel stehen.
     *
     * @return list<string>
     */
    public static function all(App $app): array
    {
        $roh = $app->settings->get('tags', [], self::scope());

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

            // Doppelte weg - auch in unterschiedlicher Schreibweise:
            // "German" und "german" wuerden beide auf denselben Vorsatz
            // im Titel passen, und dann waere nicht zu sagen, welcher
            // Haken gemeint ist.
            if (self::find($sauber, $eintrag) === null) {
                $sauber[] = $eintrag;
            }

            if (count($sauber) >= self::MAX_TAGS) {
                break;
            }
        }

        return $sauber;
    }

    /**
     * @param array<int|string, mixed> $tags
     * @return list<string> die Liste, wie sie danach dasteht
     */
    public static function save(App $app, array $tags): array
    {
        $sauber = self::clean($tags);
        $app->settings->set('tags', $sauber, self::scope());

        return $sauber;
    }

    /**
     * Ein Tag, wie er in der Liste stehen darf.
     *
     * Eckige Klammern fliegen heraus, und das ist keine Kosmetik: der
     * Vorsatz im Titel ist "[" + Name + "]". Ein Name, der selbst eine
     * Klammer enthaelt, waere hinterher nicht mehr eindeutig
     * zurueckzulesen - aus "[a]b]" liesse sich "a" oder "a]b" machen.
     */
    public static function normalize(string $tag): string
    {
        $tag = str_replace(['[', ']'], '', $tag);
        $tag = trim(preg_replace('/\s+/u', ' ', $tag) ?? '');

        // Zeichenweise gekuerzt, nicht byteweise: der uebliche Rueckfall
        // auf substr() ohne mbstring schneidet einen Umlaut in der
        // Mitte durch.
        return preg_match('/^.{0,' . self::MAX_TAG . '}/us', $tag, $treffer) === 1
            ? $treffer[0]
            : '';
    }

    // -----------------------------------------------------------------
    //  Zusammensetzen und zurueckrechnen
    // -----------------------------------------------------------------

    /**
     * Der Titel ohne die Vorsaetze der eigenen Tags.
     *
     * Abgebaut wird nur, was in der Liste steht. Was ein Betreiber
     * selbst in eckige Klammern geschrieben hat - "[Achtung] Umbau" -
     * gehoert zum Titel und bleibt stehen; sonst frisst dieses Plugin
     * einen Teil des Titels, den es nie angebaut hat.
     */
    public static function strip(App $app, string $titel): string
    {
        return self::stripList(self::all($app), $titel);
    }

    /**
     * Welche der eigenen Tags stehen gerade vor dem Titel?
     *
     * @return list<string> in der Reihenfolge der Liste, nicht der des
     *                      Titels - das ist die Reihenfolge, in der die
     *                      Haken auf der Seite stehen
     */
    public static function active(App $app, string $titel): array
    {
        return self::activeList(self::all($app), $titel);
    }

    /**
     * Titel mit Vorsaetzen.
     *
     * Die Reihenfolge ist die der Liste und nicht die, in der die Haken
     * gesetzt wurden: sonst haengt das Ergebnis daran, in welcher Folge
     * jemand geklickt hat, und zweimal dasselbe Anhaken ergibt zwei
     * verschiedene Titel.
     *
     * @param array<int|string, mixed> $gewaehlt
     */
    public static function prefix(App $app, string $blank, array $gewaehlt): string
    {
        return self::prefixList(self::all($app), $blank, $gewaehlt);
    }

    // -----------------------------------------------------------------
    //  Dasselbe ohne Einstellungen
    // -----------------------------------------------------------------
    //
    //  Die Liste kommt als Argument statt aus $app->settings. Das ist
    //  nicht Bequemlichkeit, sondern der Grund, warum das Zurueckrechnen
    //  ueberhaupt pruefbar ist: die Regeln stehen hier als reine
    //  Funktionen, ohne Datenbank. Ein Test dafuer braucht dann keine
    //  laufende Anwendung - und genau dieser Teil ist der empfindliche.

    /**
     * Der Titel ohne die Vorsaetze der Liste.
     *
     * @param list<string> $liste
     */
    public static function stripList(array $liste, string $titel): string
    {
        $rest = $titel;

        while (preg_match('/^\s*\[([^\[\]]*)\]\s*/u', $rest, $treffer) === 1) {
            if (self::find($liste, trim($treffer[1])) === null) {
                break;
            }

            $rest = substr($rest, strlen($treffer[0]));
        }

        return trim($rest);
    }

    /**
     * Welche der Tags stehen vor dem Titel?
     *
     * @param list<string> $liste
     * @return list<string>
     */
    public static function activeList(array $liste, string $titel): array
    {
        $gefunden = self::found($liste, $titel);

        return array_values(array_filter(
            $liste,
            static fn (string $tag): bool => in_array($tag, $gefunden, true)
        ));
    }

    /**
     * Titel mit Vorsaetzen, in der Reihenfolge der Liste.
     *
     * Die Reihenfolge ist die der Liste und nicht die, in der die Haken
     * gesetzt wurden: sonst haengt das Ergebnis daran, in welcher Folge
     * jemand geklickt hat, und zweimal dasselbe Anhaken ergaebe zwei
     * verschiedene Titel.
     *
     * @param list<string> $liste
     * @param array<int|string, mixed> $gewaehlt
     */
    public static function prefixList(array $liste, string $blank, array $gewaehlt): string
    {
        $namen = [];

        foreach ($gewaehlt as $wunsch) {
            if (!is_string($wunsch)) {
                continue;
            }

            // Nur was in der Liste steht. Das Formular kommt vom
            // Browser, und ein Tag, der nirgends festgelegt ist, hat im
            // Titel nichts zu suchen.
            $treffer = self::find($liste, $wunsch);
            if ($treffer !== null && !in_array($treffer, $namen, true)) {
                $namen[] = $treffer;
            }
        }

        $namen = array_values(array_filter(
            $liste,
            static fn (string $tag): bool => in_array($tag, $namen, true)
        ));

        if ($namen === []) {
            return trim($blank);
        }

        // Die Tags stehen dicht aneinander, ein Leerzeichen erst vor
        // dem Titel: [VTuber][German] Das ist mein Titel
        //
        // Gelesen wird dagegen beides - stripList() erlaubt zwischen den
        // Klammern beliebig viel Leerraum. Ein Titel, der schon mit
        // Luecken dasteht, wird also weiter erkannt und beim naechsten
        // Speichern stillschweigend geradegezogen.
        $vorsatz = '';
        foreach ($namen as $name) {
            $vorsatz .= '[' . $name . ']';
        }

        // Der blanke Titel darf leer sein - dann stehen nur die Tags da.
        // Ohne trim() blieb das Leerzeichen dahinter stehen.
        return trim($vorsatz . ' ' . $blank);
    }

    // -----------------------------------------------------------------
    //  Das Lesen der Vorsaetze
    // -----------------------------------------------------------------

    /**
     * Die Vorsaetze am Anfang, solange sie zur Liste gehoeren.
     *
     * @param list<string> $liste
     * @return list<string> die gefundenen Tags in der Schreibweise der
     *                      Liste, in der Reihenfolge des Titels
     */
    private static function found(array $liste, string $titel): array
    {
        $gefunden = [];
        $rest = $titel;

        while (preg_match('/^\s*\[([^\[\]]*)\]\s*/u', $rest, $treffer) === 1) {
            $name = self::find($liste, trim($treffer[1]));

            // Eine Klammer, die keinen bekannten Tag enthaelt, beendet
            // das Lesen. Alles danach gehoert zum Titel - auch wenn
            // spaeter noch ein bekannter Tag folgt: "[Achtung] [German]"
            // ist ein Titel, der mit "[Achtung]" beginnt, und nicht eine
            // Liste, aus der man sich das Passende heraussucht.
            if ($name === null) {
                break;
            }

            $gefunden[] = $name;
            $rest = substr($rest, strlen($treffer[0]));
        }

        return $gefunden;
    }

    /**
     * Der Eintrag der Liste, der so heisst - ohne auf Gross- und
     * Kleinschreibung zu achten.
     *
     * Damit passt "[german]" im Titel auf den Tag "German". Wer den
     * Titel im Twitch-Dashboard von Hand tippt, schreibt nicht immer
     * gleich - und ein Haken, der deswegen fehlt, waere nicht zu
     * erklaeren.
     *
     * @param list<string> $liste
     */
    private static function find(array $liste, string $name): ?string
    {
        $gesucht = self::fold($name);

        foreach ($liste as $tag) {
            if (self::fold($tag) === $gesucht) {
                return $tag;
            }
        }

        return null;
    }

    private static function fold(string $text): string
    {
        // strtolower() reicht hier: es faltet ASCII, und fuer alles
        // andere bleibt der Vergleich streng. Ein Tag "Über" passt also
        // nur auf "Über" und nicht auf "über" - lieber ein Haken, der
        // eine genaue Schreibweise verlangt, als einer, der bei zwei
        // aehnlichen Tags den falschen trifft.
        return strtolower(trim($text));
    }
}
