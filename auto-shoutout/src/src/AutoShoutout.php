<?php

declare(strict_types=1);

namespace TwitchController\Plugin\AutoShoutout;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Http\Request;
use TwitchController\Core\Twitch\TokenStore;

/**
 * ===================================================================
 *  Shoutout beim ersten Chatbeitrag im Stream
 * ===================================================================
 *
 * Eine Liste von Kanaelen, wie bei der Live-Benachrichtigung. Statt
 * nachzusehen, ob einer davon live geht, wird hier auf den Chat
 * gehoert: schreibt jemand aus der Liste in diesem Stream zum ersten
 * Mal, geht ein Twitch-Shoutout auf ihn hinaus.
 *
 * "In diesem Stream" heisst: in der laufenden RUNDE. Eine neue Runde
 * beginnt, wenn Twitch stream.online meldet - oder wenn man auf der
 * Seite "Runde neu starten" drueckt. Letzteres ist zum Ausprobieren
 * da: ohne den Knopf bekaeme jeder Kanal bis zum naechsten Streamstart
 * genau einen Versuch.
 *
 * Zwei Bedingungen von Twitch, die man kennen muss:
 *
 *   Der eigene Kanal muss LIVE sein. Ohne Stream lehnt Twitch den
 *   Shoutout ab. Ist der Kanal offline, bricht dieses Plugin darum
 *   still ab - ohne Meldung, ohne Log, und ohne die Nachricht fuer
 *   die Runde zu zaehlen.
 *
 *   Sperrfristen: zwei Minuten zwischen zwei Shoutouts, sechzig fuer
 *   denselben Kanal. Twitch antwortet mit 429. Ein solcher Shoutout
 *   bleibt vorgemerkt und wird im Takt nachgeholt - schreiben drei
 *   Leute aus der Liste kurz nacheinander, bekommen alle drei ihren,
 *   nur eben im Abstand von zwei Minuten.
 */
final class AutoShoutout
{
    public const SLUG = 'auto-shoutout';

    /** Ohne diese Freigabe nimmt Twitch keinen Shoutout an. */
    public const SCOPE = 'moderator:manage:shoutouts';

    /**
     * Abstand zwischen zwei Shoutouts, wie Twitch ihn verlangt - plus
     * ein paar Sekunden, damit der Nachholversuch nicht genau an der
     * Kante scheitert.
     */
    public const GAP_SECONDS = 125;

    /**
     * Wie oft ein vorgemerkter Shoutout nachgeholt wird.
     *
     * Alle gut zwei Minuten, also rund siebzig Minuten lang: das deckt
     * auch die Sperrfrist von sechzig Minuten fuer denselben Kanal ab
     * (etwa wenn die Live-Benachrichtigung ihn gerade erst geshoutet
     * hat). Danach wird aufgegeben, und der Grund steht an der Kachel.
     */
    private const MAX_TRIES = 35;

    private const CHUNK = 100;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    /**
     * Voreinstellung AN: mit leerer Liste passiert ohnehin nichts, und
     * wer einen Kanal eintraegt, will den Shoutout.
     */
    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    /**
     * Hat der Kanal die Freigabe? Gefragt wird das gespeicherte Token,
     * nicht Twitch - eine Ja/Nein-Frage soll keinen Netzaufruf kosten.
     */
    public static function ready(App $app): bool
    {
        try {
            return $app->twitch->tokens()->missingScopes(TokenStore::BROADCASTER, [self::SCOPE]) === [];
        } catch (Throwable) {
            return false;
        }
    }

    // -----------------------------------------------------------------
    //  Die Runde
    // -----------------------------------------------------------------

    /**
     * Die laufende Runde.
     *
     * Gibt es noch keine (frisch installiert), wird eine angelegt -
     * sonst bekaeme nach der Installation niemand einen Shoutout, bis
     * der naechste Stream startet.
     */
    public static function round(App $app): string
    {
        $runde = $app->settings->string('round', '', self::scope());

        if ($runde === '') {
            $runde = self::newRound($app, 'start');
        }

        return $runde;
    }

    /**
     * Wann die laufende Runde begann (Unixzeit), fuer die Anzeige.
     */
    public static function roundStartedAt(App $app): int
    {
        self::round($app);

        return $app->settings->int('round_at', time(), self::scope());
    }

    public static function roundReason(App $app): string
    {
        self::round($app);

        return $app->settings->string('round_reason', 'start', self::scope());
    }

    /**
     * Eine neue Runde: jeder Kanal darf wieder einen Shoutout bekommen.
     *
     * Was aus der alten Runde noch vorgemerkt war, verfaellt - ein
     * Shoutout fuer jemanden, der im LETZTEN Stream geschrieben hat,
     * waere im neuen keine Begruessung mehr.
     *
     * @param string $grund 'stream' (Streamstart), 'reset' (Knopf) oder 'start'
     */
    public static function newRound(App $app, string $grund, ?int $seit = null): string
    {
        $seit ??= time();
        $runde = $grund . ':' . $seit . ':' . bin2hex(random_bytes(3));

        $app->settings->set('round', $runde, self::scope());
        $app->settings->set('round_at', $seit, self::scope());
        $app->settings->set('round_reason', $grund, self::scope());

        $app->db->run('UPDATE auto_shoutout_channels SET pending = false WHERE pending');

        return $runde;
    }

    // -----------------------------------------------------------------
    //  Die Kanaele
    // -----------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public static function channels(App $app): array
    {
        $rows = $app->db->all(
            'SELECT login, user_id, display_name, round, pending, tries,
                    seen_at, tried_at, sent_at, last_error
               FROM auto_shoutout_channels
              ORDER BY login'
        );

        $runde = self::round($app);

        return array_map(static function (array $row) use ($runde): array {
            $diese = (string) ($row['round'] ?? '') === $runde;
            $fehler = (string) $row['last_error'];
            $wartet = (bool) $row['pending'];

            // Der Stand in DIESER Runde - alles andere ist Geschichte.
            $stand = match (true) {
                !$diese => 'idle',
                $wartet => 'waiting',
                $fehler !== '' => 'failed',
                default => 'done',
            };

            return [
                'login'        => (string) $row['login'],
                'display_name' => (string) ($row['display_name'] ?: $row['login']),
                'state'        => $stand,
                'error'        => $fehler,
                'seen_at'      => $row['seen_at'] === null ? null : (string) $row['seen_at'],
                'sent_at'      => $row['sent_at'] === null ? null : (string) $row['sent_at'],
            ];
        }, $rows);
    }

    /**
     * Einen Kanal aufnehmen. Der Login wird bei Twitch nachgeschlagen -
     * ein Tippfehler faellt so beim Anlegen auf und nicht erst durch
     * einen Shoutout, der nie kommt.
     *
     * @return string der aufgenommene Login, '' bei einem Fehler
     */
    public static function add(App $app, string $login): string
    {
        $login = self::normalizeLogin($login);
        if ($login === '') {
            self::$fehler = translate('auto_shoutout.error.bad_login');

            return '';
        }

        $name = $login;
        $id = '';

        try {
            $nutzer = $app->twitch->api()->userByLogin($login);

            if ($nutzer === null) {
                self::$fehler = translate('auto_shoutout.error.no_such_channel', ['login' => $login]);

                return '';
            }

            $name = (string) ($nutzer['display_name'] ?? $login);
            $id = (string) ($nutzer['id'] ?? '');
        } catch (Throwable $e) {
            // Twitch nicht erreichbar: trotzdem aufnehmen. Die Kennung
            // kommt mit der ersten Chatnachricht ohnehin mit.
            $app->log('AutoShoutout: Kanal ' . $login . ' nicht nachschlagbar: ' . $e->getMessage());
        }

        $app->db->run(
            "INSERT INTO auto_shoutout_channels (login, display_name, user_id)
                  VALUES (:login, :name, :id)
             ON CONFLICT (login) DO UPDATE
                     SET display_name = EXCLUDED.display_name,
                         user_id = CASE WHEN EXCLUDED.user_id = '' THEN auto_shoutout_channels.user_id
                                        ELSE EXCLUDED.user_id END",
            ['login' => $login, 'name' => $name, 'id' => $id]
        );

        return $login;
    }

    public static function remove(App $app, string $login): void
    {
        $app->db->run(
            'DELETE FROM auto_shoutout_channels WHERE login = :login',
            ['login' => self::normalizeLogin($login)]
        );
    }

    // -----------------------------------------------------------------
    //  Der Betrieb
    // -----------------------------------------------------------------

    /**
     * Eine Chatnachricht: ist das der erste Beitrag eines eingetragenen
     * Kanals in dieser Runde?
     *
     * @param array<string, mixed> $message Nutzlast von core.chat.message
     */
    public static function onChat(App $app, array $message): void
    {
        if (!self::enabled($app)) {
            return;
        }

        $login = self::normalizeLogin((string) ($message['chatter_login'] ?? ''));
        if ($login === '') {
            return;
        }

        $id = (string) ($message['chatter_id'] ?? '');

        // Auf sich selbst gibt Twitch keinen Shoutout.
        if ($id !== '' && $id === $app->settings->string('twitch_broadcaster_id')) {
            return;
        }

        // Nur Kanaele aus der Liste - erst dann lohnt die Frage, ob der
        // eigene Stream laeuft.
        $bekannt = $app->db->value(
            'SELECT 1 FROM auto_shoutout_channels WHERE login = :login',
            ['login' => $login]
        );

        if ($bekannt === null || !self::live($app)) {
            return;
        }

        $runde = self::round($app);

        // Der Vergleich der Runde steht IM UPDATE: nur eine Nachricht
        // kann die Zeile umschreiben. Zwei Nachrichten im selben
        // Augenblick ergeben so einen Shoutout und nicht zwei.
        $zeile = $app->db->first(
            "UPDATE auto_shoutout_channels
                SET round        = :runde,
                    pending      = true,
                    tries        = 0,
                    tried_at     = NULL,
                    last_error   = '',
                    seen_at      = now(),
                    user_id      = CASE WHEN :id = '' THEN user_id ELSE :id END,
                    display_name = CASE WHEN :name = '' THEN display_name ELSE :name END
              WHERE login = :login
                AND round IS DISTINCT FROM :runde
          RETURNING login",
            [
                'runde' => $runde,
                'id'    => $id,
                'name'  => (string) ($message['chatter_name'] ?? ''),
                'login' => $login,
            ]
        );

        if ($zeile === null) {
            return;
        }

        $app->log('AutoShoutout: ' . $login . ' schreibt zum ersten Mal in dieser Runde.');

        // Sofort versuchen. Laeuft die Sperrfrist noch, bleibt er
        // vorgemerkt, und der Takt holt ihn nach.
        self::attempt($app, $login);
    }

    /**
     * Im Takt: einen vorgemerkten Shoutout nachholen.
     *
     * Einer je Takt reicht - zwischen zwei Shoutouts muessen ohnehin
     * zwei Minuten liegen.
     */
    public static function tick(App $app): void
    {
        if (!self::enabled($app) || self::coolingDown($app) || !self::live($app)) {
            return;
        }

        $login = $app->db->value(
            "SELECT login FROM auto_shoutout_channels
              WHERE pending
                AND round = :runde
                AND (tried_at IS NULL OR tried_at < now() - (:gap || ' seconds')::interval)
              ORDER BY seen_at
              LIMIT 1",
            ['runde' => self::round($app), 'gap' => (string) self::GAP_SECONDS]
        );

        if ($login !== null) {
            self::attempt($app, (string) $login);
        }
    }

    /**
     * Einen vorgemerkten Shoutout versuchen.
     */
    private static function attempt(App $app, string $login): void
    {
        if (self::coolingDown($app)) {
            return;
        }

        // Den Versuch beanspruchen: Webhook und Takt koennen gleichzeitig
        // hier ankommen, und nur einer soll Twitch fragen.
        $zeile = $app->db->first(
            "UPDATE auto_shoutout_channels
                SET tried_at = now(),
                    tries    = tries + 1
              WHERE login = :login
                AND pending
                AND (tried_at IS NULL OR tried_at < now() - (:gap || ' seconds')::interval)
          RETURNING user_id, tries",
            ['login' => $login, 'gap' => (string) (self::GAP_SECONDS - 10)]
        );

        if ($zeile === null) {
            return;
        }

        $ergebnis = self::send($app, (string) $zeile['user_id']);

        if ($ergebnis['ok']) {
            $app->settings->set('last_sent_at', time(), self::scope());
            $app->db->run(
                "UPDATE auto_shoutout_channels
                    SET pending = false, sent_at = now(), last_error = ''
                  WHERE login = :login",
                ['login' => $login]
            );
            $app->log('AutoShoutout: Shoutout fuer ' . $login . ' gesendet.');

            return;
        }

        // Sperrfrist: vorgemerkt lassen, bis zur Hoechstzahl an
        // Versuchen. Alles andere ist endgueltig - ein "nicht live"
        // wird nicht besser, wenn man es alle zwei Minuten wiederholt.
        $nochmal = $ergebnis['cooldown'] && (int) $zeile['tries'] < self::MAX_TRIES;

        if ($ergebnis['cooldown']) {
            // Auch die Sperrfrist eines anderen Shoutouts (etwa von Hand
            // oder durch ein anderes Plugin) zaehlt: so versucht der
            // Takt es nicht vor Ablauf erneut.
            $app->settings->set('last_sent_at', time(), self::scope());
        }

        $app->db->run(
            'UPDATE auto_shoutout_channels
                SET pending = :nochmal, last_error = :fehler
              WHERE login = :login',
            ['nochmal' => $nochmal, 'fehler' => $ergebnis['error'], 'login' => $login]
        );

        $app->log(sprintf(
            'AutoShoutout: Shoutout fuer %s %s: %s',
            $login,
            $nochmal ? 'wartet' : 'gescheitert',
            $ergebnis['error']
        ));
    }

    // -----------------------------------------------------------------
    //  Laeuft der eigene Stream?
    // -----------------------------------------------------------------

    /**
     * Wie lange ein ueber Helix geholter Stand gilt.
     *
     * Gefragt wird nur, wenn jemand aus der Liste schreibt (oder etwas
     * vorgemerkt ist) - und dann hoechstens alle paar Minuten. Den
     * Wechsel melden ohnehin stream.online / .offline sofort.
     */
    private const LIVE_REFRESH_SECONDS = 300;

    /**
     * Ist der eigene Kanal live?
     *
     * Aus den Events stream.online / .offline (siehe setLive()), mit
     * Helix als Rueckfall - wer das Plugin mitten im Stream installiert,
     * haette sonst bis zum naechsten Streamstart "offline".
     */
    public static function live(App $app): bool
    {
        $stand = $app->settings->bool('live', false, self::scope());
        $geprueft = $app->settings->int('live_checked_at', 0, self::scope());

        if (time() - $geprueft < self::LIVE_REFRESH_SECONDS) {
            return $stand;
        }

        $kanalId = $app->settings->string('twitch_broadcaster_id');
        if ($kanalId === '') {
            return false;
        }

        try {
            $antwort = $app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->get('streams', ['user_id' => $kanalId]);
        } catch (Throwable) {
            return $stand;
        }

        if (!$antwort->ok()) {
            return $stand;
        }

        $laeuft = is_array($antwort->json['data'][0] ?? null);
        self::setLive($app, $laeuft);

        return $laeuft;
    }

    public static function setLive(App $app, bool $an): void
    {
        $app->settings->set('live', $an, self::scope());
        $app->settings->set('live_checked_at', time(), self::scope());
    }

    private static function coolingDown(App $app): bool
    {
        $letzter = $app->settings->int('last_sent_at', 0, self::scope());

        return time() - $letzter < self::GAP_SECONDS;
    }

    /**
     * Der Aufruf bei Twitch.
     *
     * @return array{ok: bool, cooldown: bool, error: string}
     */
    private static function send(App $app, string $zielId): array
    {
        if ($zielId === '') {
            return ['ok' => false, 'cooldown' => false, 'error' => translate('auto_shoutout.error.no_target_id')];
        }

        $eigene = $app->settings->string('twitch_broadcaster_id');
        if ($eigene === '') {
            return ['ok' => false, 'cooldown' => false, 'error' => translate('auto_shoutout.error.no_channel')];
        }

        $angaben = [
            'from_broadcaster_id' => $eigene,
            'to_broadcaster_id'   => $zielId,
            // Moderator ist der Kanalinhaber selbst - das Token gehoert
            // ihm, und Twitch verlangt, dass beides zusammenpasst.
            'moderator_id'        => $eigene,
        ];

        try {
            // Die Angaben stehen in der Adresse, wie Twitch sie dort
            // erwartet - UND im Koerper. Api::post() schickt ohne Koerper
            // ein leeres PHP-Array, also "[]" als JSON, und daran
            // scheitert Twitch: "Request body was not parsable". Mit den
            // Angaben als Objekt ist der Koerper gueltig, und Twitch
            // liest ohnehin die Adresse.
            $antwort = $app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->post('chat/shoutouts', $angaben, $angaben);
        } catch (Throwable $e) {
            return ['ok' => false, 'cooldown' => false, 'error' => $e->getMessage()];
        }

        if ($antwort->ok()) {
            return ['ok' => true, 'cooldown' => false, 'error' => ''];
        }

        $roh = $antwort->error();

        return [
            'ok'       => false,
            'cooldown' => $antwort->status === 429,
            'error'    => match ($antwort->status) {
                400 => translate('auto_shoutout.error.rejected', ['reason' => $roh]),
                401 => translate('auto_shoutout.error.unauthorized'),
                403 => translate('auto_shoutout.error.forbidden'),
                429 => translate('auto_shoutout.error.cooldown'),
                default => $roh,
            },
        ];
    }

    // -----------------------------------------------------------------
    //  Profilbilder
    // -----------------------------------------------------------------

    /**
     * Frisch bei Twitch gefragt und nicht gespeichert - ein
     * gespeichertes Bild veraltet. Scheitert der Aufruf, zeigen die
     * Kacheln den ersten Buchstaben.
     *
     * @param list<string> $logins
     * @return array<string, string>
     */
    public static function profiles(App $app, array $logins): array
    {
        $bilder = [];

        foreach (array_chunk(array_values(array_filter($logins)), self::CHUNK) as $haeufchen) {
            try {
                $antwort = $app->twitch->api()
                    ->as(TokenStore::BROADCASTER)
                    ->get('users', ['login' => $haeufchen]);
            } catch (Throwable $e) {
                $app->log('AutoShoutout: Bilder nicht abrufbar: ' . $e->getMessage());

                return $bilder;
            }

            if (!$antwort->ok()) {
                return $bilder;
            }

            foreach (($antwort->json['data'] ?? []) as $zeile) {
                if (is_array($zeile) && isset($zeile['login'])) {
                    $bilder[strtolower((string) $zeile['login'])] = (string) ($zeile['profile_image_url'] ?? '');
                }
            }
        }

        return $bilder;
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
     * Ein Twitch-Login, wie Twitch ihn schreibt. Eine ganze Adresse oder
     * ein @ davor wird angenommen - wer einen Kanal aufnimmt, hat oft
     * die Adresse in der Hand.
     */
    public static function normalizeLogin(string $login): string
    {
        $login = ltrim(strtolower(trim($login)), '@');

        if (preg_match('~twitch\.tv/([a-z0-9_]{1,25})~', $login, $treffer) === 1) {
            $login = $treffer[1];
        }

        return preg_match('/^[a-z0-9_]{1,25}$/', $login) === 1 ? $login : '';
    }

    public static function loginFrom(Request $request, string $feld = 'login'): string
    {
        return self::normalizeLogin($request->input($feld));
    }
}
