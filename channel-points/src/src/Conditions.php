<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

/**
 * Wann soll eine Belohnung an sein, wann aus?
 *
 * Vier Felder je Belohnung, alle mit Komma getrennt:
 *
 *   title_on    Stream-Titel enthaelt eines dieser Stichwoerter
 *   game_on     Kategorie ist genau eine von diesen
 *   title_off   ... und diese schalten sie ab
 *   game_off
 *
 * Der Titel wird wie bei den Timern als Teilwort gesucht: ein
 * Stichwort "Farming" greift auch bei "Farming & Chill". Die
 * Kategorie dagegen genau - "Minecraft" darf nicht auf "Minecraft
 * Dungeons" passen, sonst schaltet eine Belohnung im falschen Spiel.
 *
 * Zwei Regeln, die man kennen muss:
 *
 *   AUS schlaegt AN. Wer eine Sperrliste pflegt, will sie durchsetzen
 *   - auch dann, wenn die Anschaltliste zufaellig auch passt.
 *
 *   Ist KEIN Feld gefuellt, entscheidet dieses Plugin gar nichts. Die
 *   Belohnung bleibt, wie sie ist. Alles andere hiesse, dass die
 *   blosse Installation dieses Plugins jede Belohnung im Kanal
 *   umlegt.
 */
final class Conditions
{
    /**
     * Hat diese Belohnung ueberhaupt Bedingungen?
     *
     * @param array<string, mixed> $belohnung
     */
    public static function automatic(array $belohnung): bool
    {
        foreach (['title_on', 'game_on', 'title_off', 'game_off'] as $feld) {
            if (self::items($belohnung[$feld] ?? null) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Die einzelnen Eintraege einer Bedingung.
     *
     * Zwei Formen kommen an: die An-Felder sind EINE Zeichenkette mit
     * Kommas, die Aus-Felder eine Liste aus je einem Feld. Beides
     * wird hier gleich behandelt, damit die Treffer-Funktionen nur
     * eine Form kennen muessen.
     *
     * @return list<string>
     */
    public static function items(mixed $liste): array
    {
        $zeilen = is_array($liste)
            ? $liste
            : explode(',', (string) $liste);

        $teile = [];

        foreach ($zeilen as $eintrag) {
            $eintrag = trim((string) $eintrag);

            if ($eintrag !== '') {
                $teile[] = $eintrag;
            }
        }

        return $teile;
    }

    /**
     * Kommt eines der Stichwoerter im Titel vor?
     *
     * Leer heisst "kein Treffer" - nicht "alles passt". Diese Funktion
     * beantwortet nur die Frage nach dem Treffer; was leere Felder
     * bedeuten, entscheidet decide().
     */
    public static function titleHits(mixed $stichwoerter, string $streamTitel): bool
    {
        $titel = self::lower($streamTitel);

        foreach (self::items($stichwoerter) as $wort) {
            if (str_contains($titel, self::lower($wort))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ist die Kategorie genau eine der genannten?
     *
     * Gross- und Kleinschreibung ist egal, sonst nichts.
     */
    public static function gameHits(mixed $liste, string $aktuell): bool
    {
        $aktuell = trim($aktuell);

        foreach (self::items($liste) as $name) {
            if (strcasecmp($name, $aktuell) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Soll die Belohnung jetzt an oder aus sein?
     *
     * null heisst "nicht anfassen" - und das ist der haeufigste Fall:
     * ohne Bedingungen, ohne laufenden Stream oder bei einer
     * Belohnung, die uns nicht gehoert.
     *
     * Ohne Stream wird bewusst nichts entschieden. Titel und Kategorie
     * stehen dann auf dem Stand des letzten Streams, und daraus eine
     * Entscheidung abzuleiten hiesse raten.
     *
     * @param array<string, mixed> $belohnung
     * @param array{live: bool, title: string, game: string} $stream
     */
    public static function decide(array $belohnung, array $stream): ?bool
    {
        if (empty($belohnung['manageable']) || !self::automatic($belohnung)) {
            return null;
        }

        if (empty($stream['live'])) {
            return null;
        }

        $titel = (string) $stream['title'];
        $spiel = (string) $stream['game'];

        // Die Sperrliste zuerst: sie schlaegt alles andere.
        if (self::titleHits($belohnung['title_off'] ?? null, $titel)
            || self::gameHits($belohnung['game_off'] ?? null, $spiel)
        ) {
            return false;
        }

        $titelAn = trim((string) ($belohnung['title_on'] ?? ''));
        $spielAn = trim((string) ($belohnung['game_on'] ?? ''));

        // Nur eine Sperrliste, keine Anschaltliste: dann heisst "nicht
        // gesperrt" eben "an".
        if ($titelAn === '' && $spielAn === '') {
            return true;
        }

        /*
         * Beide gefuellt heisst UND - genau wie bei den Timern. Wer
         * "Farming" und "Minecraft" eintraegt, meint Farming IN
         * Minecraft und nicht "irgendetwas davon".
         *
         * Ein leeres Feld ist dabei kein Hindernis, sondern schlicht
         * keine Bedingung.
         */
        $titelPasst = $titelAn === '' || self::titleHits($titelAn, $titel);
        $spielPasst = $spielAn === '' || self::gameHits($spielAn, $spiel);

        return $titelPasst && $spielPasst;
    }

    /**
     * Warum steht die Belohnung gerade so? - fuer die Oberflaeche.
     *
     * Ein Schalter, der sich von selbst bewegt, macht ratlos, wenn
     * nirgends steht, warum.
     *
     * Der fertige Satz und nicht sein Schluessel: ein
     * translate($schluessel) waere fuer bin/lang.php unsichtbar, und
     * dann faellt ein fehlender Text erst dem Benutzer auf.
     *
     * @param array<string, mixed> $belohnung
     * @param array{live: bool, title: string, game: string} $stream
     */
    public static function reason(array $belohnung, array $stream): string
    {
        /*
         * Erst die Frage, ob es sie bei Twitch ueberhaupt gibt. Eine
         * nur hier stehende Belohnung traegt zwar auch manageable =
         * false, ist aber nicht fremd - sie wurde von hier angelegt
         * und ist nur nie angekommen.
         */
        if (!Rewards::isRemote((string) ($belohnung['id'] ?? ''))) {
            return translate('channel_points.why.local');
        }

        if (empty($belohnung['manageable'])) {
            return translate('channel_points.why.foreign');
        }

        if (!self::automatic($belohnung)) {
            return translate('channel_points.why.manual');
        }

        if (empty($stream['live'])) {
            return translate('channel_points.why.offline');
        }

        return self::decide($belohnung, $stream) === true
            ? translate('channel_points.why.on')
            : translate('channel_points.why.off');
    }

    /**
     * Kleinschreibung - mit Rueckfall.
     *
     * mbstring ist kein Pflichtteil von PHP. Ohne den Rueckfall
     * stuerzt hier alles ab, sobald jemand das System auf einer
     * Installation ohne die Erweiterung betreibt.
     */
    private static function lower(string $text): string
    {
        $text = trim($text);

        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }
}
