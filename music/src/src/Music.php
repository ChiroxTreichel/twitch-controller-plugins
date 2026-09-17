<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * ===================================================================
 *  Songwuensche: was eingestellt ist
 * ===================================================================
 *
 * Im alten System stand das an drei Orten: die Zugangsdaten in einer
 * .env, der Schalter und die Abkuehlzeit in token.json (mitten unter
 * den Zugangstoken), und die Regeln als fuenf <li> im HTML.
 *
 * Hier liegt alles in den Einstellungen dieses Plugins. Die
 * Zugangsdaten verschluesselt - sie stehen sonst im Klartext in der
 * Datenbank, und ein Spotify-Konto ist ein Konto.
 */
final class Music
{
    public const SLUG = 'music';

    /**
     * Wie lange ein Zuschauer zwischen zwei Wuenschen warten muss.
     *
     * Vorgabe wie im alten System. Die Grenzen sind dieselben wie im
     * alten Formular: unter einer Minute ist keine Abkuehlzeit, ueber
     * einer Stunde keine Warteschlange mehr.
     */
    public const DEFAULT_COOLDOWN = 15;
    public const MIN_COOLDOWN = 1;
    public const MAX_COOLDOWN = 60;

    /**
     * Die Regeln, die der Zuschauer annehmen muss.
     *
     * Im alten System standen sie fest im HTML - wer sie aendern
     * wollte, aenderte eine PHP-Datei. Es sind die fuenf von dort, als
     * Vorgabe: wer nichts eintraegt, bekommt sie und muss sich nicht
     * erst welche ausdenken.
     *
     * @var list<string>
     */
    public const DEFAULT_RULES = [
        'Keine politische Musik - weder links, noch rechts, noch sonstwas.',
        'Keine deutsche Schlagermusik.',
        'Kein "boeser" Deutschrap.',
        'Keine Musik, die von Selbstschaedigung handelt.',
        'Parodie-/Comedysongs sind nur auf Nachfrage erlaubt.',
    ];

    /** Mehr Regeln liest niemand, und laenger auch nicht. */
    public const MAX_RULES = 20;
    public const MAX_RULE_LENGTH = 200;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Der Betrieb
    // -----------------------------------------------------------------

    /**
     * Nimmt die Seite ueberhaupt Wuensche an?
     *
     * Getrennt vom Plugin-Schalter: das Plugin kann laufen - Overlay,
     * Warteschlange, Bannliste - waehrend gerade niemand wuenschen
     * soll. Genau dafuer gab es im alten Admin das Auswahlfeld
     * "Songwuensche erlauben".
     */
    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    /** Die Abkuehlzeit in Minuten. */
    public static function cooldown(App $app): int
    {
        return max(self::MIN_COOLDOWN, min(
            self::MAX_COOLDOWN,
            $app->settings->int('cooldown', self::DEFAULT_COOLDOWN, self::scope())
        ));
    }

    /**
     * Die Regeln, Zeile fuer Zeile.
     *
     * Gespeichert als ein Textfeld mit Zeilenumbruechen und nicht als
     * Liste von Feldern: es sind fuenf Saetze, und ein Textfeld ist
     * dafuer das einfachere Werkzeug - hinein, heraus, fertig.
     *
     * @return list<string>
     */
    public static function rules(App $app): array
    {
        $roh = trim($app->settings->string('rules', '', self::scope()));

        if ($roh === '') {
            return self::DEFAULT_RULES;
        }

        return self::parseRules($roh);
    }

    /**
     * Aus einem Textfeld eine Liste.
     *
     * Ohne App, damit sich genau das pruefen laesst: leere Zeilen,
     * Aufzaehlungszeichen, die jemand mitkopiert, und die Grenzen.
     *
     * @return list<string>
     */
    public static function parseRules(string $text): array
    {
        $zeilen = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $zeile) {
            // Wer eine Liste aus einem Dokument kopiert, bringt die
            // Punkte und Striche mit. Die stehen auf der Seite ohnehin
            // schon davor - zweimal sieht nach Fehler aus.
            $zeile = trim((string) preg_replace('/^\s*[-*\x{2022}\x{2013}]\s*/u', '', $zeile));

            if ($zeile === '') {
                continue;
            }

            if (preg_match('/^.{0,' . self::MAX_RULE_LENGTH . '}/us', $zeile, $treffer) === 1) {
                $zeile = $treffer[0];
            }

            $zeilen[] = $zeile;

            if (count($zeilen) >= self::MAX_RULES) {
                break;
            }
        }

        return $zeilen;
    }

    public static function setRules(App $app, string $text): void
    {
        $app->settings->set('rules', implode("\n", self::parseRules($text)), self::scope());
    }

    // -----------------------------------------------------------------
    //  Wie gross der Platz im Overlay ist
    // -----------------------------------------------------------------

    /**
     * Vorgabe: so breit, dass ein langer Titel neben das Bild passt,
     * und so hoch wie Bild plus Fortschrittsbalken.
     *
     * Im alten System gab es das nicht - die Seite fuellte die
     * Browserquelle, und die Groesse stellte man in OBS ein. Hier
     * gehoert der Platz in eine Flaeche mit anderen, und dafuer muss
     * seine Groesse bekannt sein.
     */
    public const DEFAULT_WIDTH = 560;
    public const DEFAULT_HEIGHT = 120;

    /**
     * Die Grenzen sind weit: eine Buehne kann 3840 breit sein, und wer
     * nur das Bild will, nimmt 80. Zu klein waere trotzdem keine
     * Anzeige mehr, sondern ein Fleck.
     */
    public const MIN_SIZE = 80;
    public const MAX_SIZE = 3840;

    public static function width(App $app): int
    {
        return self::size($app->settings->int('width', self::DEFAULT_WIDTH, self::scope()), self::DEFAULT_WIDTH);
    }

    public static function height(App $app): int
    {
        return self::size($app->settings->int('height', self::DEFAULT_HEIGHT, self::scope()), self::DEFAULT_HEIGHT);
    }

    // -----------------------------------------------------------------
    //  Wo der Platz im Overlay steht
    // -----------------------------------------------------------------

    /**
     * Der groesste sinnvolle Abstand: eine Buehne ist 3840 breit, und
     * weiter weg als ihr Rand faengt niemand etwas an.
     */
    public const MAX_OFFSET = 3840;

    /** Abstand von links. */
    public static function offsetX(App $app): int
    {
        return self::offset($app->settings->int('offset_x', 0, self::scope()));
    }

    /** Abstand von oben. */
    public static function offsetY(App $app): int
    {
        return self::offset($app->settings->int('offset_y', 0, self::scope()));
    }

    /**
     * Steht ein Abstand? Dann haengt der Kasten oben links und wird um
     * die beiden Werte verschoben.
     *
     * Solange beide leer sind, bleibt alles, wie es war: unten links.
     * Ein Abstand von oben ist dort ohne Wirkung - und wer nie einen
     * eingetragen hat, soll nach einem Update nicht suchen muessen, wo
     * seine Musik hin ist.
     */
    public static function isPlaced(App $app): bool
    {
        return self::offsetX($app) > 0 || self::offsetY($app) > 0;
    }

    /**
     * Ein Abstand liegt zwischen 0 und dem Rand der Buehne.
     *
     * Die 0 ist hier - anders als bei der Groesse - kein Platzhalter
     * fuer eine Vorgabe, sondern die Antwort: kein Abstand.
     */
    public static function offset(int $wert): int
    {
        return max(0, min(self::MAX_OFFSET, $wert));
    }

    // -----------------------------------------------------------------
    //  Wie der Platz im Overlay aussieht
    // -----------------------------------------------------------------

    /**
     * Dunkel wie der Rest dieses Systems, oder hell wie das alte
     * obs.php (weisser Grund, schwarze Schrift).
     *
     * Eine Entscheidung ueber den Stream und nicht ueber den
     * Geschmack: auf einem hellen Spiel verschwindet ein dunkler
     * Kasten, auf einem dunklen ein heller.
     */
    public const THEMES = ['dark', 'light'];

    public static function theme(App $app): string
    {
        return self::normalizeTheme($app->settings->string('theme', 'dark', self::scope()));
    }

    public static function normalizeTheme(string $wert): string
    {
        $wert = strtolower(trim($wert));

        return in_array($wert, self::THEMES, true) ? $wert : 'dark';
    }

    /**
     * Eine Groesse auf den erlaubten Bereich bringen.
     *
     * Die 0 ist ausdruecklich die Vorgabe und nicht das Kleinstmass:
     * ein leeres Feld heisst "wie vorgesehen", und das ist die
     * haeufigste Eingabe.
     */
    public static function size(int $wert, int $vorgabe): int
    {
        if ($wert <= 0) {
            return $vorgabe;
        }

        return max(self::MIN_SIZE, min(self::MAX_SIZE, $wert));
    }

    // -----------------------------------------------------------------
    //  Der Hinweis im Chat
    // -----------------------------------------------------------------

    /**
     * Grenzen wie beim Timer-Plugin - dort laeuft er ja.
     *
     * Nachgebaut und nicht von dort geholt: das Timer-Plugin kann
     * fehlen, und eine Klasse, die es dann nicht gibt, waere hier ein
     * Fehler beim Laden statt einer Karte, die nicht erscheint.
     */
    public const TIMER_INTERVAL_MIN = 5;
    public const TIMER_INTERVAL_MAX = 120;
    public const TIMER_DEFAULT_INTERVAL = 30;
    public const TIMER_MAX_MESSAGE = 400;

    /** Die Kennung, unter der der Timer beim Timer-Plugin steht. */
    public const TIMER_ID = 'music';

    /**
     * Einen Text auf seine Laenge bringen.
     *
     * Mit mb_substr, wo es das gibt: sonst faellt der Schnitt mitten
     * in einen Umlaut, und im Chat steht ein Fragezeichen. Ohne
     * mbstring ist substr die zweitbeste Antwort - dieselbe Stelle,
     * nur zeichenweise unsauber.
     */
    public static function cut(string $text, int $laenge): string
    {
        return function_exists('mb_substr')
            ? mb_substr($text, 0, $laenge)
            : substr($text, 0, $laenge);
    }

    public static function timerEnabled(App $app): bool
    {
        return $app->settings->bool('timer_enabled', false, self::scope());
    }

    public static function timerInterval(App $app): int
    {
        return self::timerIntervalOf($app->settings->int('timer_interval', self::TIMER_DEFAULT_INTERVAL, self::scope()));
    }

    public static function timerIntervalOf(int $wert): int
    {
        if ($wert <= 0) {
            return self::TIMER_DEFAULT_INTERVAL;
        }

        return max(self::TIMER_INTERVAL_MIN, min(self::TIMER_INTERVAL_MAX, $wert));
    }

    public static function timerLines(App $app): int
    {
        return max(0, $app->settings->int('timer_lines', 0, self::scope()));
    }

    /** Was im Chat steht, solange gewuenscht werden darf. */
    public static function timerMessageOn(App $app): string
    {
        return $app->settings->string('timer_message_on', '', self::scope());
    }

    /** Und was, solange nicht. */
    public static function timerMessageOff(App $app): string
    {
        return $app->settings->string('timer_message_off', '', self::scope());
    }

    /**
     * Der Text, der jetzt dran waere - oder nichts.
     *
     * Drei Gruende fuer "nichts", und alle drei sind dasselbe: es gibt
     * gerade nichts zu sagen.
     *
     *   - Es laeuft keine Musik. Dann ist ein Hinweis auf die Seite
     *     eine Einladung zu einer leeren Warteschlange.
     *   - Der Text fuer diesen Fall ist leer. Wer ihn nicht schreibt,
     *     will ihn nicht posten.
     *   - Spotify ist nicht verbunden.
     */
    public static function timerMessage(App $app): string
    {
        if (!self::isConnected($app) || !self::timerEnabled($app)) {
            return '';
        }

        return self::timerTextFor(
            self::overlayState($app)['playing'],
            self::enabled($app),
            self::timerMessageOn($app),
            self::timerMessageOff($app)
        );
    }

    /**
     * Welcher der beiden Texte jetzt gilt - und ob ueberhaupt einer.
     *
     * Die Entscheidung steht fuer sich und nicht zwischen den
     * Abfragen: so laesst sie sich pruefen, ohne Spotify und ohne
     * Datenbank. Sie ist der Kern der ganzen Karte, und ein Kern, den
     * man nur im laufenden Betrieb sehen kann, ist einer, den niemand
     * ansieht.
     *
     * Gefragt wird im Moment des Postens. Wer die Wuensche mitten im
     * Stream abschaltet, bekommt beim naechsten Mal den anderen Text -
     * und nicht den, der beim Einstellen galt.
     *
     * @param bool   $laeuft   spielt Spotify gerade etwas?
     * @param bool   $offen    duerfen Zuschauer sich etwas wuenschen?
     * @param string $wennAuf  Text fuer "Songwuensche erlaubt"
     * @param string $wennZu   Text fuer "Songwuensche nicht erlaubt"
     */
    public static function timerTextFor(bool $laeuft, bool $offen, string $wennAuf, string $wennZu): string
    {
        /*
         * Laeuft nichts, gibt es nichts zu sagen - auch dann nicht,
         * wenn gewuenscht werden darf. Ein Hinweis auf die Seite waere
         * eine Einladung zu einer leeren Warteschlange.
         */
        if (!$laeuft) {
            return '';
        }

        /*
         * Ein leeres Feld ist eine Antwort und kein Versehen: wer den
         * Text fuer diesen Zustand nicht schreibt, will ihn nicht
         * posten. Dann bleibt es still, und der andere Zustand postet
         * trotzdem.
         */
        return trim($offen ? $wennAuf : $wennZu);
    }

    // -----------------------------------------------------------------
    //  Der Zugang zu Spotify
    // -----------------------------------------------------------------

    /**
     * Die Anwendungsdaten aus dem Spotify-Entwicklerkonto.
     *
     * Die Kennung ist oeffentlich - sie steht in jeder Adresse, die zu
     * Spotify fuehrt. Das Geheimnis nicht, darum verschluesselt.
     */
    public static function clientId(App $app): string
    {
        return trim($app->settings->string('client_id', '', self::scope()));
    }

    public static function clientSecret(App $app): string
    {
        return $app->settings->secret('client_secret', '', self::scope());
    }

    public static function hasCredentials(App $app): bool
    {
        return self::clientId($app) !== ''
            && $app->settings->hasSecret('client_secret', self::scope());
    }

    /**
     * Wohin Spotify nach der Anmeldung zurueckschickt.
     *
     * Muss im Entwicklerkonto Zeichen fuer Zeichen genauso eingetragen
     * sein - Spotify vergleicht stur. Darum wird sie hier gebaut und
     * nicht eingetippt: eine abgetippte Adresse ist eine Fehlerquelle,
     * die man erst beim Anmelden bemerkt.
     */
    public static function redirectUri(App $app): string
    {
        return $app->url('/account/music/callback');
    }

    /**
     * Die Freigaben, die wir brauchen.
     *
     * Genau die des alten Systems, und keine mehr:
     *
     *   user-modify-playback-state   Titel in die Warteschlange
     *   user-read-playback-state     was laeuft, Lautstaerke, Geraet
     *   user-read-currently-playing  der laufende Titel
     *   user-read-recently-played    der Titel davor
     *   user-library-modify          "in die Bibliothek" im Panel
     */
    public const SCOPES = [
        'user-modify-playback-state',
        'user-read-playback-state',
        'user-read-currently-playing',
        'user-read-recently-played',
        'user-library-modify',
    ];

    /**
     * Was das Overlay anzeigt.
     *
     * An EINER Stelle, weil es von zweien gebraucht wird: der Takt
     * schickt es bei Aenderung, und die frisch geladene Seite bekommt
     * es gleich mit. Zwei Fassungen desselben Zustands liefen
     * auseinander, sobald eine davon ein Feld dazubekommt.
     *
     * @return array<string, mixed>
     */
    public static function overlayState(App $app): array
    {
        $leer = [
            'playing'  => false,
            'uri'      => '',
            'name'     => '',
            'artists'  => '',
            'image'    => '',
            'duration' => 0,
            'progress' => 0,
            'wishedBy' => '',
        ];

        if (!self::isConnected($app)) {
            return $leer;
        }

        $laeuft = (new Spotify($app))->currentlyPlaying();
        $titel = is_array($laeuft) ? ($laeuft['item'] ?? null) : null;

        if (!is_array($titel)) {
            return $leer;
        }

        $uri = (string) ($titel['uri'] ?? '');

        return [
            'playing'  => (bool) ($laeuft['is_playing'] ?? false),
            'uri'      => $uri,
            'name'     => (string) ($titel['name'] ?? ''),
            'artists'  => implode(', ', array_filter(array_map(
                static fn (array $a): string => (string) ($a['name'] ?? ''),
                (array) ($titel['artists'] ?? [])
            ))),
            'image'    => (string) ($titel['album']['images'][0]['url'] ?? ''),
            'duration' => (int) ($titel['duration_ms'] ?? 0),
            'progress' => (int) ($laeuft['progress_ms'] ?? 0),
            'wishedBy' => $uri !== '' ? Wishes::wishedBy($app, $uri) : '',
        ];
    }

    public static function isConnected(App $app): bool
    {
        return $app->settings->hasSecret('refresh_token', self::scope());
    }

    /** Der Name des verbundenen Spotify-Kontos, fuer die Anzeige. */
    public static function accountName(App $app): string
    {
        return $app->settings->string('account_name', '', self::scope());
    }

    /**
     * Das Land, in dem gesucht wird.
     *
     * Spotify braucht es: ohne Markt weist es die Suche ab. Das alte
     * System hatte dafuer ein festes "DE" im Code - hier kommt es aus
     * dem verbundenen Konto, denn wer aus Oesterreich streamt, bekommt
     * sonst Titel angeboten, die er nicht abspielen kann.
     *
     * DE ist der Rueckfall, nicht die Regel: eine Verbindung, die vor
     * dieser Aenderung entstanden ist, hat das Land noch nicht
     * gespeichert.
     */
    public const DEFAULT_MARKET = 'DE';

    public static function market(App $app): string
    {
        return self::normalizeMarket($app->settings->string('account_country', '', self::scope()));
    }

    /**
     * Ein Laenderkennzeichen nach ISO 3166-1 alpha-2 - oder der
     * Rueckfall.
     *
     * Zwei Buchstaben, sonst nichts: was Spotify nicht kennt, macht
     * aus einer Suche eine Fehlermeldung, und dann lieber DE als gar
     * keine Treffer.
     */
    public static function normalizeMarket(string $wert): string
    {
        $wert = strtoupper(trim($wert));

        return preg_match('/^[A-Z]{2}$/', $wert) === 1 ? $wert : self::DEFAULT_MARKET;
    }

    public static function disconnect(App $app): void
    {
        $app->settings->set('refresh_token', null, self::scope());
        $app->settings->set('access_token', null, self::scope());
        $app->settings->set('token_expires', 0, self::scope());
        $app->settings->set('account_name', '', self::scope());
        $app->settings->set('account_country', '', self::scope());
    }
}
