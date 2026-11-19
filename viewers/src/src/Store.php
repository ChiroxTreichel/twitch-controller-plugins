<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Viewers;

use TwitchController\Core\App;

/**
 * Die beiden Listen: lesen, und was Ereignisse daran aendern.
 *
 * Das Ende eines Abos sagt Twitch nicht - weder die Liste der Abos noch
 * sonst ein Endpunkt kennt es. Errechnet wird es aus dem letzten
 * Abo-Ereignis: jedes gilt einen Monat. Verlaengert sich ein Abo, ohne
 * dass Twitch es meldet, wird monatsweise fortgeschrieben (rollEnds).
 *
 * Gerechnet wird in der Datenbank und nicht in PHP: "+1 month" macht
 * in PHP aus dem 31. Januar den 3. Maerz, Postgres den 28. Februar.
 */
final class Store
{
    private const SUBSCRIBE = 'twitch.channel.subscribe';

    private const MESSAGE = 'twitch.channel.subscription.message';

    private const END = 'twitch.channel.subscription.end';

    private const FOLLOW = 'twitch.channel.follow';

    /** Kein Twitch-Ereignis: der Kern erkennt es selbst (Twitch\Followers). */
    private const UNFOLLOW = 'twitch.channel.unfollow';

    /**
     * Monate, die ein Abo-Ereignis gilt: einer.
     *
     * NICHT duration_months. Das sah nach "im Voraus bezahlte Monate"
     * aus, zaehlt aber bei jeder Verlaengerung hoch - 24, einen Monat
     * spaeter 25, knapp neben cumulative_months. Daraus wurde "noch 25
     * Monate", und Leute standen mit einem Ende im Jahr 2029 in der
     * Liste (1.1.1). Ob jemand wirklich mehrere Monate im Voraus bezahlt
     * hat, ist aus den Ereignissen nicht verlaesslich zu lesen; dann
     * schreibt rollEnds() das Ende eben monatsweise fort.
     */
    private const MONTHS_SQL = '1';

    // -----------------------------------------------------------------
    //  Lesen
    // -----------------------------------------------------------------

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function followers(App $app, string $suche, int $seite, bool $ehemalige = false): array
    {
        [$wo, $werte] = self::search($suche);

        // Wer folgt, fuehrt der Kern - er erkennt daran die Unfollows.
        // Wer nicht mehr folgt, steht hier, wenn freigeschaltet.
        return $ehemalige
            ? self::page(
                $app,
                'FROM viewers_unfollows' . ($wo === '' ? '' : ' WHERE ' . $wo),
                'ORDER BY unfollowed_at DESC, lower(name)',
                $werte,
                $seite
            )
            : self::page(
                $app,
                'FROM twitch_followers' . ($wo === '' ? '' : ' WHERE ' . $wo),
                'ORDER BY followed_at DESC, lower(name)',
                $werte,
                $seite
            );
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function subs(App $app, string $suche, int $seite, bool $ehemalige): array
    {
        [$wo, $werte] = self::search($suche);

        $bedingung = $ehemalige ? 'active = false' : 'active = true';
        if ($wo !== '') {
            $bedingung .= ' AND ' . $wo;
        }

        return self::page(
            $app,
            'FROM viewers_subs WHERE ' . $bedingung,
            $ehemalige ? 'ORDER BY ended_at DESC NULLS LAST, lower(name)' : 'ORDER BY tier DESC, lower(name)',
            $werte,
            $seite
        );
    }

    /** @return array{followers: int, unfollows: int, subs: int, former: int} */
    public static function counts(App $app): array
    {
        return [
            'followers' => (int) $app->db->value('SELECT count(*) FROM twitch_followers'),
            'unfollows' => (int) $app->db->value('SELECT count(*) FROM viewers_unfollows'),
            'subs'      => (int) $app->db->value('SELECT count(*) FROM viewers_subs WHERE active'),
            'former'    => (int) $app->db->value('SELECT count(*) FROM viewers_subs WHERE NOT active'),
        ];
    }

    /**
     * Suche ueber Anmeldenamen und Anzeigenamen.
     *
     * % und _ werden maskiert: wer nach "max_mustermann" sucht, meint
     * den Unterstrich und nicht "ein beliebiges Zeichen".
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function search(string $suche): array
    {
        $suche = mb_strtolower(trim($suche));
        if ($suche === '') {
            return ['', []];
        }

        $muster = '%' . strtr($suche, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';

        // Zwei Namen fuer denselben Wert: ohne Emulation laesst PDO einen
        // Platzhalter nur einmal je Abfrage zu.
        return ['(lower(login) LIKE :q1 OR lower(name) LIKE :q2)', ['q1' => $muster, 'q2' => $muster]];
    }

    /**
     * @param array<string, string> $werte
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private static function page(App $app, string $von, string $ordnung, array $werte, int $seite): array
    {
        $gesamt = (int) $app->db->value('SELECT count(*) ' . $von, $werte);

        $zeilen = $app->db->all(
            'SELECT * ' . $von . ' ' . $ordnung . ' LIMIT :limit OFFSET :offset',
            $werte + [
                'limit'  => Viewers::PER_PAGE,
                'offset' => max(0, $seite - 1) * Viewers::PER_PAGE,
            ]
        );

        return ['rows' => $zeilen, 'total' => $gesamt];
    }

    // -----------------------------------------------------------------
    //  Ereignisse
    // -----------------------------------------------------------------

    /**
     * Ein Ereignis sofort eintragen, ohne auf den naechsten Abgleich zu
     * warten.
     *
     * Laeuft im Webhook-Request - nur je eine Anweisung, keine Anfrage
     * bei Twitch.
     *
     * @param array<string, mixed> $event Zeile aus core.event.stored
     */
    public static function onEvent(App $app, array $event): void
    {
        $typ = (string) ($event['event_type'] ?? '');
        $id = (int) ($event['id'] ?? 0);

        if ($id <= 0 || !in_array($typ, [self::FOLLOW, self::UNFOLLOW, self::SUBSCRIBE, self::MESSAGE, self::END], true)) {
            return;
        }

        // Der Kanalinhaber hat ein Abo auf sich selbst. Das ist kein
        // Abonnent, und folgen kann er sich nicht.
        $nutzlast = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        if ((string) ($nutzlast['user_id'] ?? '') === $app->twitch->broadcasterId()) {
            return;
        }

        match ($typ) {
            self::FOLLOW   => self::follow($app, $id),
            self::UNFOLLOW => self::unfollow($app, $id),
            self::END    => self::ended($app, $id),
            default      => self::subscribed($app, $id, $typ),
        };
    }

    /**
     * Wieder gefolgt: dann ist es kein ehemaliger Follower mehr. In die
     * Follower-Liste traegt der Kern ihn selbst ein.
     */
    private static function follow(App $app, int $id): void
    {
        $app->db->run(
            "DELETE FROM viewers_unfollows WHERE user_id = (SELECT payload->>'user_id' FROM events WHERE id = :id)",
            ['id' => $id]
        );
    }

    /** Entfolgt - in die Liste, wenn freigeschaltet. */
    private static function unfollow(App $app, int $id): void
    {
        if (!Viewers::unfollowsAllowed($app)) {
            return;
        }

        $app->db->run(
            "INSERT INTO viewers_unfollows (user_id, login, name, followed_at, unfollowed_at)
             SELECT payload->>'user_id', COALESCE(payload->>'user_login', ''),
                    COALESCE(payload->>'user_name', payload->>'user_login', ''),
                    CASE WHEN COALESCE(payload->>'followed_at', '') <> ''
                         THEN (payload->>'followed_at')::timestamptz END,
                    occurred_at
               FROM events WHERE id = :id AND COALESCE(payload->>'user_id', '') <> ''
             ON CONFLICT (user_id) DO UPDATE
                SET login = EXCLUDED.login, name = EXCLUDED.name,
                    followed_at = EXCLUDED.followed_at, unfollowed_at = EXCLUDED.unfollowed_at",
            ['id' => $id]
        );
    }

    /**
     * Neues Abo oder Verlaengerung: aktiv, und das Ende neu errechnen.
     * Ein Ende aus einem Ereignis schlaegt ein fortgeschriebenes - das
     * fortgeschriebene ist nur geraten.
     */
    private static function subscribed(App $app, int $id, string $typ): void
    {
        $app->db->run(
            "INSERT INTO viewers_subs (user_id, login, name, tier, is_gift, ends_at)
             SELECT payload->>'user_id', COALESCE(payload->>'user_login', ''),
                    COALESCE(payload->>'user_name', payload->>'user_login', ''),
                    COALESCE(payload->>'tier', '1000'),
                    COALESCE((payload->>'is_gift')::boolean, false),
                    occurred_at + make_interval(months => " . self::MONTHS_SQL . ")
               FROM events WHERE id = :id AND COALESCE(payload->>'user_id', '') <> ''
             ON CONFLICT (user_id) DO UPDATE
                SET login = EXCLUDED.login, name = EXCLUDED.name, tier = EXCLUDED.tier,
                    -- Nur ein neues Abo sagt, ob es geschenkt ist; eine
                    -- Verlaengerung weiss es nicht und laesst es stehen.
                    is_gift = CASE WHEN CAST(:has_gift AS boolean) THEN EXCLUDED.is_gift ELSE viewers_subs.is_gift END,
                    -- Das neueste Ereignis entscheidet, sonst nichts.
                    ends_at = EXCLUDED.ends_at,
                    ends_estimated = false, active = true, ended_at = NULL, updated_at = now()",
            ['id' => $id, 'has_gift' => $typ === self::SUBSCRIBE ? 'true' : 'false']
        );
    }

    /**
     * Abo zu Ende. Mit Freischaltung wird es zur Geschichte, ohne
     * verschwindet die Zeile.
     */
    private static function ended(App $app, int $id): void
    {
        if (!Viewers::historyAllowed($app)) {
            $app->db->run(
                "DELETE FROM viewers_subs WHERE active AND user_id = (SELECT payload->>'user_id' FROM events WHERE id = :id)",
                ['id' => $id]
            );

            return;
        }

        $app->db->run(
            "INSERT INTO viewers_subs (user_id, login, name, tier, is_gift, active, ended_at)
             SELECT payload->>'user_id', COALESCE(payload->>'user_login', ''),
                    COALESCE(payload->>'user_name', payload->>'user_login', ''),
                    COALESCE(payload->>'tier', '1000'),
                    COALESCE((payload->>'is_gift')::boolean, false), false, occurred_at
               FROM events WHERE id = :id AND COALESCE(payload->>'user_id', '') <> ''
             ON CONFLICT (user_id) DO UPDATE
                SET active = false, ended_at = EXCLUDED.ended_at, ends_estimated = false, updated_at = now()
              WHERE viewers_subs.active",
            ['id' => $id]
        );
    }

    // -----------------------------------------------------------------
    //  Nach dem Abgleich
    // -----------------------------------------------------------------

    /**
     * Fehlende Enden aus den gespeicherten Ereignissen nachtragen.
     *
     * Der Kern bewahrt alle Twitch-Ereignisse auf. Ein Abo, das vor
     * der Installation dieses Plugins abgeschlossen wurde, hat dort
     * trotzdem sein Ereignis - und damit sein Ende.
     */
    public static function fillEnds(App $app): void
    {
        $app->db->run(
            "UPDATE viewers_subs s
                SET ends_at = e.ende, ends_estimated = false, updated_at = now()
               FROM (
                    SELECT DISTINCT ON (actor_external_id) actor_external_id,
                           occurred_at + make_interval(months => " . self::MONTHS_SQL . ") AS ende
                      FROM events
                     WHERE event_type IN (:subscribe, :message) AND actor_external_id IS NOT NULL
                     ORDER BY actor_external_id, occurred_at DESC
               ) e
              WHERE s.user_id = e.actor_external_id AND s.active AND s.ends_at IS NULL",
            ['subscribe' => self::SUBSCRIBE, 'message' => self::MESSAGE]
        );
    }

    /**
     * Ein Ende, das vorbei ist, waehrend das Abo noch besteht, um ganze
     * Monate fortschreiben.
     *
     * Das passiert, wenn sich ein Abo verlaengert, ohne dass Twitch es
     * meldet - wer seine Verlaengerung nicht im Chat teilt, schickt
     * kein Ereignis. Das neue Ende ist dann eine Schaetzung und wird so
     * angezeigt.
     */
    public static function rollEnds(App $app): void
    {
        $app->db->run(
            "UPDATE viewers_subs
                SET ends_at = ends_at + make_interval(months =>
                        (date_part('year', age(now(), ends_at)) * 12 + date_part('month', age(now(), ends_at)))::int + 1),
                    ends_estimated = true, updated_at = now()
              WHERE active AND ends_at IS NOT NULL AND ends_at <= now()"
        );
    }

    /**
     * Beim Freischalten: wer laut gespeicherten Ereignissen ein Abo
     * beendet und seitdem kein neues hat, kommt in die Geschichte.
     *
     * Sonst stuende die Liste am Anfang leer da, obwohl die Ereignisse
     * laengst im Kern liegen - man saehe erst nach Wochen etwas.
     */
    public static function importFormer(App $app): void
    {
        $app->db->run(
            "INSERT INTO viewers_subs (user_id, login, name, tier, is_gift, active, ended_at)
             SELECT DISTINCT ON (e.actor_external_id) e.actor_external_id,
                    COALESCE(e.payload->>'user_login', ''),
                    COALESCE(e.payload->>'user_name', e.actor_name, ''),
                    COALESCE(e.payload->>'tier', '1000'),
                    COALESCE((e.payload->>'is_gift')::boolean, false), false, e.occurred_at
               FROM events e
              WHERE e.event_type = :end AND e.actor_external_id IS NOT NULL
                AND e.actor_external_id <> :owner
                AND NOT EXISTS (
                    SELECT 1 FROM events n
                     WHERE n.actor_external_id = e.actor_external_id
                       AND n.event_type IN (:subscribe, :message)
                       AND n.occurred_at > e.occurred_at
                )
              ORDER BY e.actor_external_id, e.occurred_at DESC
             ON CONFLICT (user_id) DO NOTHING",
            [
                'end'       => self::END,
                'subscribe' => self::SUBSCRIBE,
                'message'   => self::MESSAGE,
                'owner'     => $app->twitch->broadcasterId(),
            ]
        );
    }

    /**
     * Beim Freischalten: wer laut gespeicherten Ereignissen entfolgt
     * hat und heute nicht wieder folgt, kommt in die Liste.
     */
    public static function importUnfollows(App $app): void
    {
        $app->db->run(
            "INSERT INTO viewers_unfollows (user_id, login, name, followed_at, unfollowed_at)
             SELECT DISTINCT ON (e.actor_external_id) e.actor_external_id,
                    COALESCE(e.payload->>'user_login', ''),
                    COALESCE(e.payload->>'user_name', e.actor_name, ''),
                    CASE WHEN COALESCE(e.payload->>'followed_at', '') <> ''
                         THEN (e.payload->>'followed_at')::timestamptz END,
                    e.occurred_at
               FROM events e
              WHERE e.event_type = :unfollow AND e.actor_external_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM twitch_followers f WHERE f.user_id = e.actor_external_id)
              ORDER BY e.actor_external_id, e.occurred_at DESC
             ON CONFLICT (user_id) DO NOTHING",
            ['unfollow' => self::UNFOLLOW]
        );
    }
}
