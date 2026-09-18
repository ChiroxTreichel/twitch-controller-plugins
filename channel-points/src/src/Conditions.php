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
     * Was EIN Satz Bedingungen sagt.
     *
     * Derselbe Satz Felder steckt in einer Belohnung und in einer
     * Gruppe - also dieselbe Rechnung. Wer sie stellt, entscheidet
     * decide().
     *
     * null heisst "keine Meinung": keine Bedingungen eingetragen oder
     * kein Stream. Ohne Stream wird bewusst nichts entschieden - Titel
     * und Kategorie stehen dann auf dem Stand des letzten Streams, und
     * daraus etwas abzuleiten hiesse raten.
     *
     * @param array<string, mixed> $felder
     * @param array{live: bool, title: string, game: string} $stream
     */
    public static function evaluate(array $felder, array $stream): ?bool
    {
        if (!self::automatic($felder)) {
            return null;
        }

        if (empty($stream['live'])) {
            return null;
        }

        $belohnung = $felder;
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
     * Mehrere Meinungen zu einer machen.
     *
     * WER AUS SAGT, GEWINNT. Eine einzige Aus-Bedingung - an der
     * Belohnung oder an irgendeiner ihrer Gruppen - schaltet ab, auch
     * wenn alles andere passt.
     *
     * Sagt niemand aus und wenigstens einer an, ist es an. Hat
     * niemand eine Meinung, bleibt es null: dann faesst dieses Plugin
     * die Belohnung nicht an.
     *
     * @param list<bool|null> $meinungen
     */
    public static function combine(array $meinungen): ?bool
    {
        $einAn = false;

        foreach ($meinungen as $meinung) {
            if ($meinung === false) {
                return false;
            }

            if ($meinung === true) {
                $einAn = true;
            }
        }

        return $einAn ? true : null;
    }

    /**
     * Soll die Belohnung jetzt an oder aus sein?
     *
     * Ihre eigenen Bedingungen und die aller Gruppen, in denen sie
     * steckt - zusammengelegt nach der Regel oben.
     *
     * Eine ausgeschaltete Gruppe zaehlt nicht mit. Ausgeschaltet
     * heisst "diese Regel gilt gerade nicht", nicht "alles darin aus"
     * - sonst waere der Schalter eine Falle.
     *
     * @param array<string, mixed> $belohnung
     * @param array{live: bool, title: string, game: string} $stream
     * @param list<array<string, mixed>> $gruppen alle Gruppen
     */
    public static function decide(array $belohnung, array $stream, array $gruppen = []): ?bool
    {
        // Was uns nicht gehoert, fassen wir nicht an - Twitch nimmt
        // den Aufruf ohnehin nicht an.
        if (empty($belohnung['manageable'])) {
            return null;
        }

        $meinungen = [self::evaluate($belohnung, $stream)];

        foreach (Groups::forReward($gruppen, (string) ($belohnung['id'] ?? '')) as $gruppe) {
            if (empty($gruppe['enabled'])) {
                continue;
            }

            $meinungen[] = self::evaluate($gruppe, $stream);
        }

        return self::combine($meinungen);
    }

    /**
     * Steckt hinter dem Aus eine Gruppe?
     *
     * Fuer die Oberflaeche: ein Schalter, der sich von selbst bewegt,
     * macht ratlos - und noch ratloser, wenn an der Belohnung selbst
     * gar nichts steht, was ihn erklaeren wuerde.
     *
     * @param array<string, mixed> $belohnung
     * @param array{live: bool, title: string, game: string} $stream
     * @param list<array<string, mixed>> $gruppen
     * @return array<string, mixed>|null die Gruppe, die abschaltet
     */
    public static function blockingGroup(array $belohnung, array $stream, array $gruppen): ?array
    {
        foreach (Groups::forReward($gruppen, (string) ($belohnung['id'] ?? '')) as $gruppe) {
            if (empty($gruppe['enabled'])) {
                continue;
            }

            if (self::evaluate($gruppe, $stream) === false) {
                return $gruppe;
            }
        }

        return null;
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
    public static function reason(array $belohnung, array $stream, array $gruppen = []): string
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

        // Ohne eigene Bedingungen UND ohne eine Gruppe, die etwas
        // sagt, passiert nichts.
        $mitgeredet = self::automatic($belohnung);

        foreach (Groups::forReward($gruppen, (string) ($belohnung['id'] ?? '')) as $gruppe) {
            if (!empty($gruppe['enabled']) && self::automatic($gruppe)) {
                $mitgeredet = true;
            }
        }

        if (!$mitgeredet) {
            return translate('channel_points.why.manual');
        }

        if (empty($stream['live'])) {
            return translate('channel_points.why.offline');
        }

        /*
         * Sagt eine Gruppe aus, wird sie beim Namen genannt. Sonst
         * sucht man die Bedingung an einer Belohnung, an der keine
         * steht.
         */
        $sperrt = self::blockingGroup($belohnung, $stream, $gruppen);

        if ($sperrt !== null) {
            return translate('channel_points.why.group_off', [
                'group' => (string) $sperrt['name'],
            ]);
        }

        return self::decide($belohnung, $stream, $gruppen) === true
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
