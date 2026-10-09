<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Giveaway;

use TwitchController\Core\App;

/**
 * Wer wie viele Tickets hat.
 *
 * Ein Zuschauer bekommt mit dem Befehl ein Ticket, Abonnenten auf
 * Wunsch eines mehr - und dann erst wieder, wenn sein Intervall um
 * ist. Wer laenger dabei ist, sammelt also mehr; genau das ist der
 * Sinn.
 */
final class Tickets
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * Der Befehl kam an: Ticket vergeben, wenn es dran ist, und die
     * Antwort fuer den Chat liefern (leer heisst: still).
     *
     * @param array<string, mixed> $message Nutzlast von core.chat.message
     */
    public function claim(array $message): string
    {
        $id = trim((string) ($message['chatter_id'] ?? ''));
        $login = strtolower(trim((string) ($message['chatter_login'] ?? '')));

        if ($id === '' || $login === '') {
            return '';
        }

        $name = trim((string) ($message['chatter_name'] ?? '')) ?: $login;
        $config = Giveaway::config($this->app);

        $werte = [
            'username' => Giveaway::mention($login),
            'command'  => '!' . $config['command'],
            'interval' => (string) $config['interval'],
        ];

        if ((new Draw($this->app))->phase() !== Draw::COLLECT) {
            return Giveaway::fill($config['message_closed'], $werte);
        }

        $dazu = $config['sub_bonus'] && self::isSubscriber((array) ($message['badges'] ?? [])) ? 2 : 1;

        /*
         * Pruefen und hochzaehlen in EINER Anweisung. Erst nachsehen und
         * dann schreiben liesse eine Luecke: schickt jemand den Befehl
         * zweimal schnell hintereinander, sehen beide Requests "Intervall
         * ist um" und beide zaehlen. Das WHERE am DO UPDATE laesst die
         * Zeile stehen, solange gewartet werden muss - dann kommt nichts
         * zurueck.
         */
        $neu = $this->app->db->first(
            'INSERT INTO giveaway_entries (twitch_id, login, display_name, tickets, last_ticket_at)
             VALUES (:id, :login, :name, CAST(:dazu AS INTEGER), now())
             ON CONFLICT (twitch_id) DO UPDATE
                SET tickets        = giveaway_entries.tickets + EXCLUDED.tickets,
                    login          = EXCLUDED.login,
                    display_name   = EXCLUDED.display_name,
                    last_ticket_at = now()
              WHERE giveaway_entries.last_ticket_at IS NULL
                 OR giveaway_entries.last_ticket_at <= now() - (:minuten || \' minutes\')::interval
             RETURNING tickets',
            [
                'id'      => $id,
                'login'   => $login,
                'name'    => $name,
                'dazu'    => (string) $dazu,
                'minuten' => (string) $config['interval'],
            ]
        );

        if ($neu !== null) {
            return Giveaway::fill($config['message_ok'], $werte + [
                'tickets' => (string) $neu['tickets'],
                'added'   => (string) $dazu,
            ]);
        }

        $stand = $this->app->db->first(
            'SELECT tickets,
                    CEIL(EXTRACT(EPOCH FROM (last_ticket_at + (:minuten || \' minutes\')::interval - now()))) AS rest
               FROM giveaway_entries
              WHERE twitch_id = :id',
            ['id' => $id, 'minuten' => (string) $config['interval']]
        );

        return Giveaway::fill($config['message_wait'], $werte + [
            'tickets' => (string) ($stand['tickets'] ?? 0),
            'wait'    => Giveaway::duration((int) ($stand['rest'] ?? 1)),
        ]);
    }

    /**
     * Alle, die gerade auf dem Rad stehen.
     *
     * Nach Namen sortiert und nicht nach Tickets: das Rad soll nicht
     * die grossen Stuecke nebeneinander haben, und dieselbe Reihenfolge
     * bei jedem Aufruf heisst, dass das Rad zwischen zwei Preisen nicht
     * durcheinanderspringt.
     *
     * @return list<array{twitch_id: string, login: string, display_name: string, tickets: int}>
     */
    public function entries(): array
    {
        return array_map(
            static fn (array $zeile): array => [
                'twitch_id'    => (string) $zeile['twitch_id'],
                'login'        => (string) $zeile['login'],
                'display_name' => (string) $zeile['display_name'],
                'tickets'      => (int) $zeile['tickets'],
            ],
            $this->app->db->all(
                'SELECT twitch_id, login, display_name, tickets
                   FROM giveaway_entries
                  WHERE tickets > 0
                  ORDER BY lower(display_name), twitch_id'
            )
        );
    }

    /** Alle Tickets weg - das Giveaway ist vorbei. */
    public function clear(): void
    {
        $this->app->db->run('DELETE FROM giveaway_entries');
    }

    /**
     * Abonnent oder nicht, nach den Badges der Chatnachricht.
     *
     * "founder" zaehlt mit: Twitch zeigt bei den ersten Abonnenten eines
     * Kanals dieses Badge STATT "subscriber". Ohne es bekaeme genau die
     * treueste Gruppe kein Extraticket.
     *
     * @param array<int, mixed> $badges
     */
    public static function isSubscriber(array $badges): bool
    {
        foreach ($badges as $badge) {
            if (is_array($badge) && in_array((string) ($badge['set_id'] ?? ''), ['subscriber', 'founder'], true)) {
                return true;
            }
        }

        return false;
    }
}
