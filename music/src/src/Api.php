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
 * Sie haengt an einem GERAET, nicht an einer Sitzung - ein Stream
 * Deck, eine Tastenkombination, ein Skript. Darum gibt es keinen
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
     * Die Statuscodes sind die des alten Systems: 404 ohne aktives
     * Geraet oder ohne laufenden Titel, 502 wenn Spotify die Aenderung
     * ablehnt.
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
                 * ein Stream Deck, das die Zahl in eine Beschriftung
                 * schreibt, will genau das.
                 */
                return $laut === null
                    ? Response::text('', 404)
                    : Response::text((string) $laut);

            case 'raiseVolume':
            case 'lowerVolume':
                $laut = self::volumeOf($spotify->playerState());

                if ($laut === null) {
                    return Response::json(['error' => 'No active device'], 404);
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
