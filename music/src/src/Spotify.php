<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;
use TwitchController\Core\Support\Http;

/**
 * ===================================================================
 *  Der Draht zu Spotify
 * ===================================================================
 *
 * Aus dem alten System uebernommen: Warteschlange, laufender Titel,
 * Suche, in die Warteschlange legen, Lautstaerke, weiter und zurueck.
 *
 * -------------------------------------------------------------------
 *  Die Sache mit dem Refresh-Token
 * -------------------------------------------------------------------
 *
 * Spotify dreht den Refresh-Token bei jeder Erneuerung weiter und
 * macht den alten dabei ungueltig. Zwei Erneuerungen gleichzeitig
 * heissen also: eine gewinnt, die andere bekommt "invalid_grant" - und
 * danach ist die Verbindung WEG, nicht nur dieser eine Aufruf. Der
 * Betreiber muss sich neu anmelden und merkt es mitten im Stream.
 *
 * Gleichzeitig passiert hier oft: der Worker fragt im Takt die
 * Warteschlange ab, waehrend jemand auf /music eine Suche tippt.
 *
 * Das alte System nahm dafuer eine Dateisperre (flock auf
 * token.json.lock). Hier ist es eine Sperre der Datenbank
 * (pg_advisory_lock): Webserver und Worker sind verschiedene
 * Container, teilen sich also kein Dateisystem mit funktionierendem
 * flock - aber dieselbe Datenbank.
 */
final class Spotify
{
    private const API = 'https://api.spotify.com/v1';
    private const ACCOUNTS = 'https://accounts.spotify.com';

    /**
     * Die Nummer der Sperre.
     *
     * pg_advisory_lock nimmt eine Zahl, keinen Namen. Sie muss ueber
     * alle Prozesse dieselbe sein und darf mit keiner anderen Sperre
     * dieser Anwendung zusammenfallen - deshalb aus dem Namen
     * gerechnet und hier festgeschrieben, statt sie irgendwo zu
     * erraten.
     */
    private const LOCK_ID = 872341601;

    /**
     * Wie lange vor Ablauf schon erneuert wird.
     *
     * Ein Token, das in zehn Sekunden ablaeuft, ist fuer einen Aufruf
     * zu wenig - er waere unterwegs abgelaufen und kaeme als 401
     * zurueck. Also eine Minute Luft.
     */
    private const REFRESH_MARGIN = 60;

    public function __construct(private readonly App $app)
    {
    }

    // -----------------------------------------------------------------
    //  Anmelden
    // -----------------------------------------------------------------

    /**
     * Die Adresse, an die der Betreiber geschickt wird.
     *
     * "show_dialog" ist absichtlich nicht gesetzt: wer schon
     * angemeldet ist, soll durchgereicht werden.
     */
    public function authorizeUrl(string $state): string
    {
        return self::ACCOUNTS . '/authorize?' . http_build_query([
            'client_id'     => Music::clientId($this->app),
            'response_type' => 'code',
            'redirect_uri'  => Music::redirectUri($this->app),
            'scope'         => implode(' ', Music::SCOPES),
            'state'         => $state,
        ]);
    }

    /**
     * Den Code von der Rueckkehr gegen Token tauschen.
     *
     * @return array{ok: bool, error: string}
     */
    public function exchangeCode(string $code): array
    {
        $antwort = $this->token([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => Music::redirectUri($this->app),
        ]);

        if (!$antwort['ok']) {
            return $antwort;
        }

        // Wessen Konto ist das? Nur fuer die Anzeige - damit auf der
        // Einstellungsseite steht, WOMIT man verbunden ist.
        $ich = $this->get('/me');

        if (is_array($ich) && isset($ich['display_name'])) {
            $this->app->settings->set(
                'account_name',
                (string) $ich['display_name'],
                Music::scope()
            );
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Ein gueltiges Zugangstoken - notfalls ein frisches.
     *
     * Gibt eine leere Zeichenkette zurueck, wenn die Verbindung fehlt
     * oder nicht mehr gilt. Aufrufer pruefen das; ein Aufruf ohne
     * Token waere sonst ein 401, den niemand deuten kann.
     */
    public function accessToken(): string
    {
        $jetzt = time();
        $laeuft = $this->app->settings->int('token_expires', 0, Music::scope());
        $token = $this->app->settings->secret('access_token', '', Music::scope());

        if ($token !== '' && $laeuft > $jetzt + self::REFRESH_MARGIN) {
            return $token;
        }

        if (!Music::isConnected($this->app)) {
            return '';
        }

        return $this->refresh();
    }

    /**
     * Erneuern - unter der Sperre.
     *
     * Nach dem Erhalt der Sperre wird NOCH EINMAL nachgesehen: ein
     * anderer Prozess hat das Token womoeglich schon erneuert, waehrend
     * wir gewartet haben. Ohne diese zweite Pruefung erneuerten beide
     * nacheinander, und der zweite verbraucht den Refresh-Token, den
     * der erste gerade bekommen hat.
     */
    private function refresh(): string
    {
        $this->app->db->value('SELECT pg_advisory_lock(' . self::LOCK_ID . ')');

        try {
            $this->app->settings->flush();

            $jetzt = time();
            $laeuft = $this->app->settings->int('token_expires', 0, Music::scope());
            $token = $this->app->settings->secret('access_token', '', Music::scope());

            if ($token !== '' && $laeuft > $jetzt + self::REFRESH_MARGIN) {
                return $token;
            }

            $ergebnis = $this->token([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->app->settings->secret('refresh_token', '', Music::scope()),
            ]);

            if (!$ergebnis['ok']) {
                return '';
            }

            return $this->app->settings->secret('access_token', '', Music::scope());
        } finally {
            $this->app->db->value('SELECT pg_advisory_unlock(' . self::LOCK_ID . ')');
        }
    }

    /**
     * Der Aufruf an /api/token - fuer beide Faelle derselbe.
     *
     * @param array<string, string> $felder
     * @return array{ok: bool, error: string}
     */
    private function token(array $felder): array
    {
        $antwort = Http::form(self::ACCOUNTS . '/api/token', $felder, [
            'Authorization' => 'Basic ' . base64_encode(
                Music::clientId($this->app) . ':' . Music::clientSecret($this->app)
            ),
        ]);

        $daten = $antwort->json;

        if (!$antwort->ok() || !isset($daten['access_token'])) {
            $grund = (string) ($daten['error_description'] ?? $daten['error'] ?? '');

            /*
             * invalid_grant heisst: der Refresh-Token gilt nicht mehr.
             * Dann ist auch jeder weitere Versuch vergeblich, und die
             * gespeicherten Token sind nur noch Ballast, der so
             * aussieht, als waere man verbunden.
             */
            if (str_contains($grund, 'invalid_grant')) {
                Music::disconnect($this->app);
            }

            $this->app->log('Musik: Spotify-Token nicht bekommen - ' . ($grund !== '' ? $grund : 'unbekannt'));

            return ['ok' => false, 'error' => $grund !== '' ? $grund : translate('music.error.token')];
        }

        $this->app->settings->setSecret('access_token', (string) $daten['access_token'], Music::scope());
        $this->app->settings->set(
            'token_expires',
            time() + max(60, (int) ($daten['expires_in'] ?? 3600)),
            Music::scope()
        );

        /*
         * Der neue Refresh-Token kommt nicht immer mit. Kommt er, MUSS
         * er den alten ersetzen - der ist ab jetzt ungueltig. Kommt er
         * nicht, gilt der alte weiter, und ihn zu leeren waere das Ende
         * der Verbindung.
         */
        if (!empty($daten['refresh_token'])) {
            $this->app->settings->setSecret('refresh_token', (string) $daten['refresh_token'], Music::scope());
        }

        return ['ok' => true, 'error' => ''];
    }

    // -----------------------------------------------------------------
    //  Die Aufrufe
    // -----------------------------------------------------------------

    /**
     * Ein Aufruf an die Spotify-API.
     *
     * Gibt das gelesene JSON zurueck, null bei 204 (Spotify antwortet
     * so, wenn gerade nichts laeuft) und false, wenn es schiefging.
     * Drei Faelle, drei Rueckgaben - "nichts laeuft" ist kein Fehler
     * und darf nicht wie einer aussehen.
     *
     * @return array<string, mixed>|null|false
     */
    public function request(string $methode, string $pfad, ?string $rumpf = null): array|null|false
    {
        $token = $this->accessToken();

        if ($token === '') {
            return false;
        }

        $antwort = Http::request($methode, self::API . $pfad, [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ], $rumpf);

        $code = $antwort->status;

        // 204 und 202 sind Spotifys "hab ich, sag aber nichts dazu" -
        // so antwortet es, wenn gerade nichts laeuft und auf jeden
        // Steuerbefehl. Das ist kein Fehler und darf nicht wie einer
        // aussehen.
        if ($code === 204 || $code === 202) {
            return null;
        }

        if (!$antwort->ok()) {
            // 429 ist das Kontingent. Es steht im Log, aber es wird
            // nicht gemerkt: das alte System schrieb dafuer eine Datei
            // "blocked_until" - und die Stelle, die sie las, war
            // auskommentiert.
            $this->app->log('Musik: Spotify ' . $methode . ' ' . $pfad . ' -> ' . $code);

            return false;
        }

        return $antwort->json !== [] ? $antwort->json : null;
    }

    /** @return array<string, mixed>|null|false */
    public function get(string $pfad): array|null|false
    {
        return $this->request('GET', $pfad);
    }

    // -----------------------------------------------------------------
    //  Was die Oberflaeche braucht
    // -----------------------------------------------------------------

    /** @return array<string, mixed>|null|false */
    public function queue(): array|null|false
    {
        return $this->get('/me/player/queue');
    }

    /** @return array<string, mixed>|null|false */
    public function currentlyPlaying(): array|null|false
    {
        return $this->get('/me/player/currently-playing');
    }

    /** @return array<string, mixed>|null|false */
    public function playerState(): array|null|false
    {
        return $this->get('/me/player');
    }

    /** @return array<string, mixed>|null|false */
    public function recentTrack(): array|null|false
    {
        return $this->get('/me/player/recently-played?limit=1');
    }

    /**
     * Titel suchen.
     *
     * @return list<array<string, mixed>>
     */
    public function searchTracks(string $begriff, int $limit = 25): array
    {
        $begriff = trim($begriff);

        if ($begriff === '') {
            return [];
        }

        $daten = $this->get('/search?' . http_build_query([
            'q'     => $begriff,
            'type'  => 'track',
            'limit' => max(1, min(50, $limit)),
        ]));

        return is_array($daten) ? array_values((array) ($daten['tracks']['items'] ?? [])) : [];
    }

    /**
     * Interpreten suchen.
     *
     * @return list<array<string, mixed>>
     */
    public function searchArtists(string $begriff, int $limit = 25): array
    {
        $begriff = trim($begriff);

        if ($begriff === '') {
            return [];
        }

        $daten = $this->get('/search?' . http_build_query([
            'q'     => $begriff,
            'type'  => 'artist',
            'limit' => max(1, min(50, $limit)),
        ]));

        return is_array($daten) ? array_values((array) ($daten['artists']['items'] ?? [])) : [];
    }

    /** @return array<string, mixed>|null */
    public function track(string $id): ?array
    {
        $daten = $this->get('/tracks/' . rawurlencode($id));

        return is_array($daten) ? $daten : null;
    }

    /**
     * Mehrere Interpreten auf einmal - fuer die Genres eines Titels.
     *
     * Ein Titel hat bei Spotify keine Genres, seine Interpreten schon.
     * Genau so prueft das alte System den Genre-Bann.
     *
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    public function artists(array $ids): array
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));

        if ($ids === []) {
            return [];
        }

        $daten = $this->get('/artists?ids=' . rawurlencode(implode(',', array_slice($ids, 0, 50))));

        return is_array($daten) ? array_values((array) ($daten['artists'] ?? [])) : [];
    }

    /** In die Warteschlange legen. */
    public function enqueue(string $uri): bool
    {
        return $this->request('POST', '/me/player/queue?uri=' . rawurlencode($uri)) !== false;
    }

    public function next(): bool
    {
        return $this->request('POST', '/me/player/next') !== false;
    }

    public function previous(): bool
    {
        return $this->request('POST', '/me/player/previous') !== false;
    }

    public function setVolume(int $prozent): bool
    {
        $prozent = max(0, min(100, $prozent));

        return $this->request('PUT', '/me/player/volume?volume_percent=' . $prozent) !== false;
    }

    /** Den laufenden Titel in die eigene Bibliothek legen. */
    public function saveTrack(string $id): bool
    {
        return $this->request('PUT', '/me/tracks?ids=' . rawurlencode($id)) !== false;
    }
}
