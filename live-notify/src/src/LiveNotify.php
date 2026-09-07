<?php

declare(strict_types=1);

namespace TwitchController\Plugin\LiveNotify;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Http\Request;
use TwitchController\Core\Twitch\TokenStore;

/**
 * ===================================================================
 *  Wer von den beobachteten Kanaelen geht gerade live?
 * ===================================================================
 *
 * Gemeldet wird nicht "ist live", sondern der UEBERGANG offline -> live.
 * Das ist der ganze Kern: der Zustand steht in der Tabelle, und nur
 * wenn er umspringt, geht eine Nachricht hinaus. Ohne gemerkten Zustand
 * kaeme bei jedem Tick eine neue - alle 15 Sekunden, bis niemand den
 * Discord-Kanal mehr abonniert hat.
 *
 * Die ZIELE sind Plugins. Dieses hier bringt Discord mit, Chat und
 * Shoutout kommen dazu, und weitere koennen folgen:
 *
 *   live_notify.targets   ein Ziel anmelden (Name, Reihenfolge, Zustand)
 *   live_notify.live      ein Kanal ist gerade live geworden
 *
 * Mehr braucht es nicht. Reiter gibt es hier keine: die Seite zeigt die
 * Kanaele, und die Einstellungen jedes Ziels stehen unter
 * Plugins > Einstellungen - dort sucht man sie.
 *
 * Jedes Ziel hat je Kanal einen eigenen Haken. Welche an sind, steht
 * als Liste in der Kanalzeile - nicht je Ziel eine Spalte: ein neues
 * Ziel soll keine Migration der Tabelle dieses Plugins verlangen.
 */
final class LiveNotify
{
    public const SLUG = 'live-notify';

    /**
     * Wie viele Kanaele Twitch je Live-Abfrage annimmt.
     *
     * Mehr Kanaele als das zu beobachten ist unwahrscheinlich, aber die
     * Schleife kostet nichts und die Alternative waere eine stille
     * Grenze bei 100.
     */
    private const CHUNK = 100;

    /**
     * Wie oft nachgesehen wird.
     *
     * Der Worker tickt alle 15 Sekunden. Jede Runde ist EIN Aufruf fuer
     * alle beobachteten Kanaele - das ist billig, und eine Meldung, die
     * eine Minute zu spaet kommt, ist die halbe Meldung.
     */
    public const POLL_SECONDS = 30;

    /**
     * Die Platzhalter, die in jeder Vorlage stehen duerfen.
     *
     * Ausgeschrieben und nicht aus den Werten abgeleitet: die
     * Oberflaeche zeigt diese Liste, und was dort steht, muss auch
     * ersetzt werden. Eine Liste, die sich aus dem Zufall der letzten
     * Twitch-Antwort ergibt, waere keine Zusage.
     *
     * @var list<string>
     */
    public const PLACEHOLDERS = ['login', 'display_name', 'title', 'game_name', 'url'];

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Der Hauptschalter
    // -----------------------------------------------------------------

    /**
     * Laeuft die Beobachtung?
     *
     * Voreinstellung AN: wer das Plugin installiert und einen Kanal
     * aufnimmt, will die Meldung. Ein Schalter, der nach der
     * Installation aus ist, laesst einen die erste halbe Stunde suchen -
     * anders als beim Loeschbot, wo ein ungefragt loeschender Bot die
     * unangenehmere Ueberraschung waere.
     */
    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    // -----------------------------------------------------------------
    //  Die Ziele
    // -----------------------------------------------------------------

    /**
     * Die angemeldeten Ziele, geordnet.
     *
     * Ein Ziel darf melden, dass es gerade nicht kann - fehlende
     * Freigabe, fehlende Adresse. Dann steht der Grund neben seinem
     * Haken, statt dass der Haken stillschweigend nichts tut. Genau
     * diese Art Schalter hat mich bei den Zielen schon einmal einen
     * Nachmittag gekostet.
     *
     * @return array<string, array{label: string, order: int, ready: bool, hint: string}>
     */
    public static function targets(App $app): array
    {
        $ziele = $app->hooks->filter('live_notify.targets', []);
        if (!is_array($ziele)) {
            return [];
        }

        $sauber = [];

        foreach ($ziele as $key => $ziel) {
            $key = self::normalizeKey((string) $key);
            if ($key === '' || !is_array($ziel)) {
                continue;
            }

            $sauber[$key] = [
                'label' => trim((string) ($ziel['label'] ?? $key)) ?: $key,
                'order' => (int) ($ziel['order'] ?? 50),
                'ready' => (bool) ($ziel['ready'] ?? true),
                'hint'  => trim((string) ($ziel['hint'] ?? '')),
            ];
        }

        uasort($sauber, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $sauber;
    }

    // -----------------------------------------------------------------
    //  Die beobachteten Kanaele
    // -----------------------------------------------------------------

    /**
     * @return list<array{login: string, display_name: string, targets: list<string>, live: bool, checked_at: ?string}>
     */
    public static function channels(App $app): array
    {
        $rows = $app->db->all(
            'SELECT login, display_name, targets, live, checked_at
               FROM live_notify_channels
              ORDER BY login'
        );

        return array_map(static function (array $row): array {
            $ziele = json_decode((string) $row['targets'], true);

            return [
                'login'        => (string) $row['login'],
                'display_name' => (string) ($row['display_name'] ?: $row['login']),
                'targets'      => is_array($ziele) ? array_values(array_map('strval', $ziele)) : [],
                'live'         => (bool) $row['live'],
                'checked_at'   => $row['checked_at'] === null ? null : (string) $row['checked_at'],
            ];
        }, $rows);
    }

    /**
     * Einen Kanal aufnehmen.
     *
     * Der Login wird bei Twitch nachgeschlagen: so steht der richtige
     * Anzeigename in der Liste, und ein Tippfehler faellt beim Anlegen
     * auf statt spaeter durch eine Benachrichtigung, die nie kommt.
     *
     * @return string der aufgenommene Login, '' bei einem Fehler
     */
    public static function add(App $app, string $login): string
    {
        $login = self::normalizeLogin($login);
        if ($login === '') {
            self::$fehler = translate('live_notify.error.bad_login');

            return '';
        }

        $name = $login;

        try {
            $nutzer = $app->twitch->api()->userByLogin($login);

            if ($nutzer === null) {
                self::$fehler = translate('live_notify.error.no_such_channel', ['login' => $login]);

                return '';
            }

            $name = (string) ($nutzer['display_name'] ?? $login);
        } catch (Throwable $e) {
            // Twitch nicht erreichbar: aufnehmen trotzdem, mit dem Login
            // als Namen. Die Liste zu fuehren soll nicht daran haengen,
            // dass Twitch gerade antwortet - der Name wird beim ersten
            // Live-Werden ohnehin richtig.
            $app->log('LiveNotify: Kanal ' . $login . ' nicht nachschlagbar: ' . $e->getMessage());
        }

        $app->db->run(
            'INSERT INTO live_notify_channels (login, display_name)
                  VALUES (:login, :name)
             ON CONFLICT (login) DO UPDATE SET display_name = EXCLUDED.display_name',
            ['login' => $login, 'name' => $name]
        );

        return $login;
    }

    public static function remove(App $app, string $login): void
    {
        $app->db->run(
            'DELETE FROM live_notify_channels WHERE login = :login',
            ['login' => self::normalizeLogin($login)]
        );
    }

    /**
     * Ein Ziel fuer einen Kanal ein- oder ausschalten.
     *
     * Gelesen, geaendert, geschrieben - und das ist hier in Ordnung:
     * es passiert auf einen Klick eines Menschen, nicht im Betrieb.
     * Zwei Leute, die im selben Augenblick denselben Haken umlegen,
     * sind kein Fall, den es zu bedenken gibt.
     */
    public static function setTarget(App $app, string $login, string $key, bool $an): bool
    {
        $login = self::normalizeLogin($login);
        $key = self::normalizeKey($key);

        if ($login === '' || $key === '') {
            return false;
        }

        // Nur angemeldete Ziele. Ein Schluessel aus einem Formular ist
        // kein Grund, etwas in die Liste zu schreiben, das niemand
        // ausliest.
        if (!array_key_exists($key, self::targets($app))) {
            return false;
        }

        $roh = $app->db->value(
            'SELECT targets FROM live_notify_channels WHERE login = :login',
            ['login' => $login]
        );

        if ($roh === null) {
            return false;
        }

        $ziele = json_decode((string) $roh, true);
        $ziele = is_array($ziele) ? array_values(array_map('strval', $ziele)) : [];

        $ziele = array_values(array_filter($ziele, static fn (string $z): bool => $z !== $key));
        if ($an) {
            $ziele[] = $key;
        }

        $app->db->run(
            'UPDATE live_notify_channels SET targets = CAST(:ziele AS JSONB) WHERE login = :login',
            ['ziele' => (string) json_encode(array_values($ziele)), 'login' => $login]
        );

        return true;
    }

    // -----------------------------------------------------------------
    //  Vorlagen
    // -----------------------------------------------------------------

    /**
     * Platzhalter in einer Vorlage ersetzen.
     *
     * Die Schreibweise ist die des alten Systems - {{login}} mit
     * Leerraum erlaubt, damit vorhandene Vorlagen weiter passen. Wer
     * "{{ display_name }}" getippt hat, soll nicht plaetzlich den
     * Rohtext im Discord-Kanal lesen.
     *
     * Unbekannte Platzhalter bleiben stehen und werden NICHT geleert:
     * ein Tippfehler soll sichtbar sein. Ein stillschweigend leerer
     * Platz sieht wie ein fehlender Wert aus, und man sucht ihn bei
     * Twitch.
     *
     * @param array<string, string> $werte
     */
    public static function render(string $vorlage, array $werte): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            static function (array $treffer) use ($werte): string {
                $name = strtolower($treffer[1]);

                return array_key_exists($name, $werte) ? $werte[$name] : $treffer[0];
            },
            $vorlage
        );
    }

    /**
     * Die Werte eines Live-Kanals fuer die Vorlagen.
     *
     * @param array<string, mixed> $info
     * @return array<string, string>
     */
    public static function values(array $info): array
    {
        $login = (string) ($info['login'] ?? '');
        $name = (string) ($info['display_name'] ?? '');

        return [
            'login'        => $login,
            'display_name' => $name !== '' ? $name : $login,
            'title'        => (string) ($info['title'] ?? ''),
            'game_name'    => (string) ($info['game_name'] ?? ''),
            'url'          => 'https://twitch.tv/' . $login,
        ];
    }

    // -----------------------------------------------------------------
    //  Der eigene Kanal
    // -----------------------------------------------------------------

    /**
     * Ist der eigene Kanal gerade live?
     *
     * Steht HIER und nicht im Chat-Plugin, obwohl nur dieses es
     * braucht: es ist eine Frage an Twitch ueber den eigenen Kanal, und
     * dieses Plugin fragt Twitch ohnehin. Ein zweites Plugin mit einem
     * zweiten Weg dorthin waere ein zweiter Ort, an dem etwas schief
     * gehen kann.
     */
    public static function broadcasterIsLive(App $app): bool
    {
        $login = $app->settings->string('twitch_broadcaster_login');
        if ($login === '') {
            return false;
        }

        try {
            $antwort = $app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->get('streams', ['user_login' => $login]);
        } catch (Throwable) {
            return false;
        }

        return $antwort->ok() && ($antwort->json['data'][0] ?? null) !== null;
    }

    // -----------------------------------------------------------------
    //  Die Runde
    // -----------------------------------------------------------------

    /**
     * Nachsehen, wer live ist, und den Uebergang melden.
     *
     * @return int wie viele Kanaele gerade live geworden sind
     */
    public static function tick(App $app): int
    {
        // Der Hauptschalter haelt die ganze Runde auf - nicht nur das
        // Senden. Wer abschaltet, will Ruhe, und dazu gehoert, dass
        // auch der ZUSTAND nicht mitgeschrieben wird: sonst gilt ein
        // Kanal nach dem Wiedereinschalten als "war schon live", und
        // seine Meldung faellt aus.
        if (!self::enabled($app)) {
            return 0;
        }

        $kanaele = self::channels($app);
        if ($kanaele === []) {
            return 0;
        }

        $letzte = $app->settings->int('checked_at', 0, self::scope());
        if (time() - $letzte < self::POLL_SECONDS) {
            return 0;
        }

        $app->settings->set('checked_at', time(), self::scope());

        $logins = array_map(static fn (array $k): string => $k['login'], $kanaele);
        $live = [];

        foreach (array_chunk($logins, self::CHUNK) as $haeufchen) {
            try {
                $antwort = $app->twitch->api()
                    ->as(TokenStore::BROADCASTER)
                    ->get('streams', ['user_login' => $haeufchen]);
            } catch (Throwable $e) {
                $app->log('LiveNotify: Live-Abfrage fehlgeschlagen: ' . $e->getMessage());

                return 0;
            }

            if (!$antwort->ok()) {
                $app->log('LiveNotify: Live-Abfrage fehlgeschlagen: ' . $antwort->error());

                return 0;
            }

            foreach (($antwort->json['data'] ?? []) as $zeile) {
                if (!is_array($zeile)) {
                    continue;
                }

                $login = self::normalizeLogin((string) ($zeile['user_login'] ?? ''));
                if ($login === '') {
                    continue;
                }

                $live[$login] = [
                    'login'        => $login,
                    'user_id'      => (string) ($zeile['user_id'] ?? ''),
                    'display_name' => (string) ($zeile['user_name'] ?? $login),
                    'title'        => (string) ($zeile['title'] ?? ''),
                    'game_name'    => (string) ($zeile['game_name'] ?? ''),
                    'started_at'   => (string) ($zeile['started_at'] ?? ''),
                ];
            }
        }

        $gemeldet = 0;

        foreach ($kanaele as $kanal) {
            $login = $kanal['login'];
            $info = $live[$login] ?? null;
            $istLive = $info !== null;

            // Der Zustand wird IMMER geschrieben, auch wenn er sich
            // nicht geaendert hat: checked_at ist die Auskunft "es wurde
            // nachgesehen", und die will man auf der Seite sehen.
            $app->db->run(
                "UPDATE live_notify_channels
                    SET live         = :live,
                        display_name = CASE WHEN :name = '' THEN display_name ELSE :name END,
                        started_at   = NULLIF(:start, '')::timestamptz,
                        checked_at   = now()
                  WHERE login = :login",
                [
                    'live'  => $istLive,
                    'name'  => $istLive ? (string) $info['display_name'] : '',
                    'start' => $istLive ? (string) $info['started_at'] : '',
                    'login' => $login,
                ]
            );

            // Nur der Uebergang. Wer schon live war, hat seine Meldung
            // bekommen.
            if (!$istLive || $kanal['live']) {
                continue;
            }

            $gemeldet++;

            // Ein Ziel, das sich verschluckt, darf die anderen nicht
            // mitnehmen - darum ueber die Hooks, die je Zuhoerer
            // abfangen, und nicht in einer eigenen Schleife.
            $app->hooks->dispatch('live_notify.live', $info, $kanal['targets']);
        }

        return $gemeldet;
    }

    // -----------------------------------------------------------------
    //  Kleinigkeiten
    // -----------------------------------------------------------------

    private static string $fehler = '';

    public static function error(): string
    {
        return self::$fehler;
    }

    /**
     * Ein Twitch-Login, wie Twitch ihn schreibt.
     *
     * Die Grenzen sind die von Twitch: Kleinbuchstaben, Ziffern,
     * Unterstrich, bis 25 Zeichen. Geprueft wird hier und nicht erst
     * beim Aufruf - ein Login mit einem Leerzeichen darin ergibt eine
     * Abfrage, die nie etwas findet, und niemand sieht warum.
     */
    public static function normalizeLogin(string $login): string
    {
        $login = strtolower(trim($login));

        // Eine ganze Adresse ist ein haeufiger Fehlgriff: wer einen
        // Kanal aufnehmen will, hat oft die Adresse in der Hand.
        if (preg_match('~(?:twitch\.tv/)([a-z0-9_]{1,25})~', $login, $treffer) === 1) {
            $login = $treffer[1];
        }

        return preg_match('/^[a-z0-9_]{1,25}$/', $login) === 1 ? $login : '';
    }

    private static function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));

        return preg_match('/^[a-z][a-z0-9_-]{0,30}$/', $key) === 1 ? $key : '';
    }

    /**
     * Der Login aus einem Formular, ohne dass jeder Aufrufer das
     * Putzen wiederholt.
     */
    public static function loginFrom(Request $request, string $feld = 'login'): string
    {
        return self::normalizeLogin($request->input($feld));
    }
}
