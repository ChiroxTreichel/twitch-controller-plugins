<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;
use TwitchController\Core\Http\Response;

/**
 * Die Steuer-API.
 *
 * Aus dem alten System uebernommen (public/api.php), und zwar die
 * Haelfte, die nicht an einem Menschen haengt: Lautstaerke, Titel
 * vor und zurueck, "laeuft gerade etwas", den laufenden Titel in die
 * eigene Bibliothek legen.
 *
 * Sie haengt an einem GERAET, nicht an einer Sitzung - ein eigenes
 * Bediengeraet, eine Tastenkombination, ein Skript. Darum gibt es keinen
 * Login, sondern einen Token, und darum sind die Aufrufe 1:1 wie
 * frueher aufgebaut:
 *
 *   /music/api?a=currentVolume&token=...
 *
 * So muss an den Knoepfen nur der Rechnername getauscht werden.
 *
 * Anders als frueher braucht JEDE Aktion den Token - auch "running".
 * Ohne ihn verriete es Fremden, ob gerade Musik laeuft; Nutzen null,
 * Auskunft umsonst.
 *
 * Die Entscheidungen stehen hier als reine Funktionen (knows,
 * tokenOk, volumeTarget, volumeOf), damit sie sich ohne Spotify und
 * ohne Netz pruefen lassen. handle() ist die Klammer darum.
 */
final class Api
{
    /** Um so viel Prozent geht es je Schritt - wie im alten System. */
    public const STEP = 10;

    /** @var list<string> */
    public const ACTIONS = [
        'running',
        'currentVolume',
        'raiseVolume',
        'lowerVolume',
        'nextTrack',
        'previousTrack',
        'addToFavorites',
    ];

    public static function knows(string $aktion): bool
    {
        return in_array($aktion, self::ACTIONS, true);
    }

    /**
     * Stimmt der Token?
     *
     * hash_equals vergleicht in gleichbleibender Zeit - ein
     * gewoehnlicher Vergleich bricht beim ersten falschen Zeichen ab
     * und verraet damit, wie weit man schon richtig lag.
     *
     * Ein leerer erwarteter Token heisst NEIN, nicht "egal". Sonst
     * stuende die Anlage nach einem misslungenen Einrichten offen.
     */
    public static function tokenOk(string $erwartet, string $gegeben): bool
    {
        if ($erwartet === '' || strlen($gegeben) !== strlen($erwartet)) {
            return false;
        }

        return hash_equals($erwartet, $gegeben);
    }

    /**
     * Die neue Lautstaerke - in Schritten von zehn, innerhalb von 0
     * bis 100.
     */
    public static function volumeTarget(string $aktion, int $aktuell): int
    {
        $aktuell = max(0, min(100, $aktuell));

        return $aktion === 'lowerVolume'
            ? max(0, $aktuell - self::STEP)
            : min(100, $aktuell + self::STEP);
    }

    /**
     * Die Lautstaerke aus dem Zustand des Abspielers.
     *
     * null heisst "kein aktives Geraet" - und das ist etwas anderes
     * als 0. Bei 0 laeuft Spotify und ist stumm; bei null laeuft
     * nichts, und ein Lauter waere ins Leere gesprochen.
     */
    public static function volumeOf(mixed $zustand): ?int
    {
        if (!is_array($zustand)) {
            return null;
        }

        $geraet = $zustand['device'] ?? null;

        if (!is_array($geraet) || !isset($geraet['volume_percent'])) {
            return null;
        }

        return max(0, min(100, (int) $geraet['volume_percent']));
    }

    /**
     * Laeuft gerade etwas?
     *
     * Im alten System war das isSpotifyRunning(): eine nicht leere
     * Antwort von "currently playing". Dieselbe Frage, dieselbe
     * Antwort.
     */
    public static function runningFrom(mixed $laeuft): bool
    {
        return is_array($laeuft) && $laeuft !== [];
    }

    // -----------------------------------------------------------------
    //  Ausfuehren
    // -----------------------------------------------------------------

    /**
     * Eine Aktion ausfuehren - der Token ist an dieser Stelle schon
     * geprueft.
     *
     * Zu den Statuscodes: ein Fehler ist nur, was WIRKLICH einer
     * ist. Dass gerade nichts laeuft, gehoert nicht dazu - der
     * Aufruf ist dann durchgegangen, die Verbindung stand, es gab
     * nur nichts zu holen oder zu aendern. Das antwortet mit 200 und
     * einem leeren Wert.
     *
     * 502 bleibt fuer "Spotify lehnt ab", 503 fuer "gar nicht
     * verbunden", 404 fuer addToFavorites ohne laufenden Titel - da
     * gibt es wirklich nichts zu speichern.
     */
    public static function handle(App $app, string $aktion): Response
    {
        if (!Music::isConnected($app)) {
            return Response::json(['error' => 'Spotify not connected'], 503);
        }

        $spotify = new Spotify($app);

        switch ($aktion) {
            case 'running':
                return Response::json(['running' => self::runningFrom($spotify->currentlyPlaying())]);

            case 'currentVolume':
                $laut = self::volumeOf($spotify->playerState());

                /*
                 * Reiner Text und nicht JSON - so war es frueher, und
                 * ein Geraet, das die Zahl in eine Beschriftung
                 * schreibt, will genau das.
                 *
                 * Ohne aktives Geraet kommt ein Leerstring - und
                 * zwar mit 200, nicht mit 404.
                 *
                 * Das alte System schickte dort einen leeren 404
                 * ("http_response_code(404); exit"). Hinter nginx kam
                 * damit wirklich nichts an; hinter Apache nicht. Der
                 * ersetzt einen leeren Koerper bei einem FEHLERstatus
                 * durch seine eigene Seite "404 Not Found - The
                 * requested URL was not found on this server", und
                 * die ist von "diese Route gibt es nicht" nicht zu
                 * unterscheiden. Genau danach haben wir eine halbe
                 * Stunde gesucht.
                 *
                 * Bei 200 fasst er nichts an. Das Geraet am anderen
                 * Ende liest dann wirklich nichts - und "nichts"
                 * heisst hier "gerade keine Lautstaerke", was kein
                 * Fehler ist, sondern ein Zustand.
                 */
                return $laut === null
                    ? Response::text('')
                    : Response::text((string) $laut);

            case 'raiseVolume':
            case 'lowerVolume':
                $laut = self::volumeOf($spotify->playerState());

                /*
                 * Kein aktives Geraet ist auch hier kein Fehler: der
                 * Aufruf ist durchgegangen, die Verbindung stand, es
                 * gab nur gerade nichts zu aendern.
                 *
                 * "volume": null statt eines leeren Koerpers, damit
                 * die Antwort lesbares JSON bleibt - wer sie sonst
                 * auspackt, faellt bei einer leeren Zeichenkette auf
                 * die Nase.
                 */
                if ($laut === null) {
                    return Response::json(['volume' => null]);
                }

                $ziel = self::volumeTarget($aktion, $laut);

                return $spotify->setVolume($ziel)
                    ? Response::json(['volume' => $ziel])
                    : Response::json(['error' => 'Spotify error'], 502);

            case 'nextTrack':
                return $spotify->next()
                    ? Response::json(['ok' => true])
                    : Response::json(['error' => 'Spotify error'], 502);

            case 'previousTrack':
                return $spotify->previous()
                    ? Response::json(['ok' => true])
                    : Response::json(['error' => 'Spotify error'], 502);

            case 'addToFavorites':
                $laeuft = $spotify->currentlyPlaying();
                $titel = is_array($laeuft) ? ($laeuft['item'] ?? null) : null;
                $id = is_array($titel) ? (string) ($titel['id'] ?? '') : '';

                if ($id === '') {
                    return Response::json(['error' => 'Nothing playing'], 404);
                }

                /*
                 * In die SPOTIFY-Bibliothek, nicht in die Merkliste
                 * eines Zuschauers. Die gehoert dort einer
                 * Twitch-Kennung, und ein Token ist niemand.
                 */
                return $spotify->saveTrack($id)
                    ? Response::json(['ok' => true, 'id' => $id])
                    : Response::json(['error' => 'Spotify error'], 502);
        }

        return Response::json(['error' => 'Unknown action'], 400);
    }
}
