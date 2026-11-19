<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Viewers;

use TwitchController\Core\App;

/**
 * Die eigene Abo-Tabelle an die Liste des Kerns angleichen.
 *
 * Seit 2.19.0 fuehrt der Kern die Abos (twitch_subscribers) und gleicht
 * sie alle zehn Sekunden mit Twitch ab. Hier wird nicht mehr bei Twitch
 * gefragt - nur verglichen:
 *
 *   in der Liste des Kerns  -> aktiv, Stufe und Geschenk von dort
 *   nicht mehr darin        -> Geschichte (wenn freigeschaltet), sonst weg
 *
 * Eigenes bleibt hier: das errechnete Ende und die Geschichte.
 *
 * Ausgeloest von core.subscribers.changed, und zur Sicherheit alle zehn
 * Minuten im Worker - das kostet nur zwei Abfragen in der Datenbank.
 */
final class Sync
{
    /** Fuer cron.tick: das Netz unter dem Hook. */
    public static function due(App $app): void
    {
        if (time() - $app->settings->int('subs_synced_at', 0, Viewers::scope()) >= Viewers::SUBS_EVERY) {
            self::subs($app);
        }
    }

    /**
     * @return bool false, wenn der Kern noch keine Liste hat
     */
    public static function subs(App $app): bool
    {
        $app->settings->set('subs_synced_at', time(), Viewers::scope());

        /*
         * Hat der Kern noch nie abgeglichen, ist seine Tabelle leer -
         * und eine leere Liste hiesse hier: niemand hat mehr ein Abo.
         * Das waere die ganze Geschichte auf einen Schlag, und falsch.
         */
        if ($app->twitch->subscribers()->total() === null) {
            return false;
        }

        $app->db->transaction(static function () use ($app): void {
            $app->db->run(
                'INSERT INTO viewers_subs (user_id, login, name, tier, is_gift, gifter_name, seen_at)
                 SELECT user_id, login, name, tier, is_gift, gifter_name, now()
                   FROM twitch_subscribers
                  WHERE user_id <> :owner
                 ON CONFLICT (user_id) DO UPDATE
                    SET login = EXCLUDED.login, name = EXCLUDED.name, tier = EXCLUDED.tier,
                        is_gift = EXCLUDED.is_gift, gifter_name = EXCLUDED.gifter_name,
                        seen_at = EXCLUDED.seen_at,
                        -- War es Geschichte, ist es wieder ein Abo. Das alte
                        -- Ende gehoert zum alten Abo.
                        ends_at = CASE WHEN viewers_subs.active THEN viewers_subs.ends_at END,
                        ends_estimated = viewers_subs.active AND viewers_subs.ends_estimated,
                        active = true, ended_at = NULL, updated_at = now()',
                // Der Kanalinhaber hat ein Abo auf sich selbst. Das ist
                // kein Abonnent.
                ['owner' => $app->twitch->broadcasterId()]
            );

            $weg = 'active AND NOT EXISTS (SELECT 1 FROM twitch_subscribers t WHERE t.user_id = viewers_subs.user_id)';

            if (Viewers::historyAllowed($app)) {
                $app->db->run(
                    'UPDATE viewers_subs
                        SET active = false, ended_at = now(), ends_estimated = false, updated_at = now()
                      WHERE ' . $weg
                );
            } else {
                $app->db->run('DELETE FROM viewers_subs WHERE ' . $weg);
            }
        });

        Store::fillEnds($app);
        Store::rollEnds($app);

        return true;
    }
}
