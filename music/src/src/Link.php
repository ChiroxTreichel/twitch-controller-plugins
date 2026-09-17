<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

/**
 * Aus dem, was ein Zuschauer einfuegt, eine Spotify-Adresse machen.
 *
 * Eingefuegt wird alles Moegliche: der Teilen-Link aus der App mit
 * "?si=..." dahinter, der Link aus dem Browser, einer mit
 * Sprachkennung ("/intl-de/track/..."), oder gleich die URI. Das alte
 * System kannte nur die eine Form mit optionalem intl-Teil.
 *
 * Rein statisch und ohne App: damit sich genau das pruefen laesst -
 * hier entscheidet sich, ob ein Wunsch ankommt oder mit "ungueltiger
 * Link" abgewiesen wird, und das ist die haeufigste Enttaeuschung auf
 * so einer Seite.
 */
final class Link
{
    /**
     * Die URI zu einem eingefuegten Text - null, wenn nichts darin
     * steht, was nach einem Titel aussieht.
     */
    public static function toUri(string $eingabe): ?string
    {
        $eingabe = trim($eingabe);

        if ($eingabe === '') {
            return null;
        }

        // Schon eine URI.
        if (preg_match('~^spotify:track:([a-zA-Z0-9]+)$~', $eingabe, $treffer) === 1) {
            return 'spotify:track:' . $treffer[1];
        }

        /*
         * Ein Link. Der Sprachteil ("intl-de") steht optional davor,
         * und alles ab "?" gehoert nicht dazu - die App haengt dort
         * eine Kennung an, mit der Spotify zaehlt, wer geteilt hat.
         */
        if (preg_match('~open\.spotify\.com/(?:intl-[a-z]{2}/)?track/([a-zA-Z0-9]+)~', $eingabe, $treffer) === 1) {
            return 'spotify:track:' . $treffer[1];
        }

        return null;
    }

    /** Die Kennung aus einer URI. */
    public static function trackId(string $uri): ?string
    {
        return preg_match('~^spotify:track:([a-zA-Z0-9]+)$~', $uri, $treffer) === 1 ? $treffer[1] : null;
    }
}
