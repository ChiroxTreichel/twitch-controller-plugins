<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Viewers;

use RuntimeException;
use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * Die Abos bei Twitch abholen. (Die Follower holt der Kern - siehe
 * Twitch\Followers.)
 *
 * Der Endpunkt liefert hundert Zeilen je Anfrage und blaettert mit
 * einem Cursor. Erst wenn ALLE Seiten da sind, wird verglichen: wer
 * dann fehlt, hat kein Abo mehr. Bricht der Abruf mittendrin ab,
 * bleibt die Tabelle, wie sie war - sonst verloere ein Netzfehler auf
 * Seite drei alle ab Seite vier.
 *
 * Gefragt wird mit dem Token des Kanalinhabers (channel:read:subscriptions,
 * fordert der Kern an).
 */
final class Sync
{
    /** Hundert Seiten zu hundert: zehntausend. Danach eher ein Fehler. */
    private const MAX_PAGES = 1000;

    /** Laeuft im Worker - der soll nicht minutenlang an einer Liste haengen. */
    private const MAX_SECONDS = 120;

    /** Faellig? Dann abgleichen. Fuer cron.tick. */
    public static function due(App $app): void
    {
        $jetzt = time();

        if ($jetzt - Viewers::syncState($app, 'subs')['at'] >= Viewers::SUBS_EVERY) {
            self::subs($app);
        }

    }

    public static function subs(App $app): bool
    {
        return self::run($app, 'subs', static function () use ($app): void {
            $kanal = $app->twitch->broadcasterId();
            $zeilen = self::fetchAll($app, 'subscriptions', $kanal);
            $stand = (string) $app->db->value('SELECT now()::text');

            $app->db->transaction(static function () use ($app, $zeilen, $kanal, $stand): void {
                foreach ($zeilen as $zeile) {
                    $id = (string) ($zeile['user_id'] ?? '');

                    // Der Kanalinhaber steht in seiner eigenen Liste.
                    if ($id === '' || $id === $kanal) {
                        continue;
                    }

                    $app->db->run(
                        'INSERT INTO viewers_subs (user_id, login, name, tier, is_gift, gifter_name, seen_at)
                         VALUES (:id, :login, :name, :tier, CAST(:gift AS boolean), :gifter, CAST(:stand AS timestamptz))
                         ON CONFLICT (user_id) DO UPDATE
                            SET login = EXCLUDED.login, name = EXCLUDED.name, tier = EXCLUDED.tier,
                                is_gift = EXCLUDED.is_gift, gifter_name = EXCLUDED.gifter_name,
                                seen_at = EXCLUDED.seen_at,
                                -- War es Geschichte, ist es wieder ein Abo. Das alte
                                -- Ende gehoert zum alten Abo.
                                ends_at = CASE WHEN viewers_subs.active THEN viewers_subs.ends_at END,
                                ends_estimated = viewers_subs.active AND viewers_subs.ends_estimated,
                                active = true, ended_at = NULL, updated_at = now()',
                        [
                            'id'     => $id,
                            'login'  => (string) ($zeile['user_login'] ?? ''),
                            'name'   => (string) ($zeile['user_name'] ?? $zeile['user_login'] ?? ''),
                            'tier'   => (string) ($zeile['tier'] ?? '1000'),
                            'gift'   => !empty($zeile['is_gift']) ? 'true' : 'false',
                            'gifter' => (string) ($zeile['gifter_name'] ?? ''),
                            'stand'  => $stand,
                        ]
                    );
                }

                // Wer nicht mehr in der Liste steht, hat kein Abo mehr.
                if (Viewers::historyAllowed($app)) {
                    $app->db->run(
                        'UPDATE viewers_subs
                            SET active = false, ended_at = now(), ends_estimated = false, updated_at = now()
                          WHERE active AND seen_at < CAST(:stand AS timestamptz)',
                        ['stand' => $stand]
                    );
                } else {
                    $app->db->run(
                        'DELETE FROM viewers_subs WHERE active AND seen_at < CAST(:stand AS timestamptz)',
                        ['stand' => $stand]
                    );
                }
            });

            Store::fillEnds($app);
            Store::rollEnds($app);
        });
    }

    /**
     * Abgleich mit Buchfuehrung: Zeitpunkt und Fehler stehen danach in
     * den Einstellungen, damit die Seite sagen kann, wie alt die Liste
     * ist - und warum sie es ist.
     *
     * Der Zeitpunkt wird auch bei einem Fehler gesetzt. Sonst versuchte
     * es der Worker alle fuenfzehn Sekunden erneut, und ein fehlendes
     * Recht wuerde zur Dauerschleife gegen die Twitch-API.
     */
    private static function run(App $app, string $was, callable $arbeit): bool
    {
        $app->settings->set($was . '_synced_at', time(), Viewers::scope());

        try {
            if ($app->twitch->broadcasterId() === '') {
                throw new RuntimeException(translate('viewers.error.no_channel'));
            }

            $arbeit();
            $app->settings->set($was . '_sync_error', '', Viewers::scope());

            return true;
        } catch (Throwable $fehler) {
            $app->settings->set($was . '_sync_error', mb_substr($fehler->getMessage(), 0, 300), Viewers::scope());
            $app->log('Zuschauer: Abgleich ' . $was . ' gescheitert - ' . $fehler->getMessage());

            return false;
        }
    }

    /**
     * Alle Seiten eines Endpunkts.
     *
     * @return list<array<string, mixed>>
     */
    private static function fetchAll(App $app, string $endpunkt, string $kanal): array
    {
        $api = $app->twitch->api()->as(TokenStore::BROADCASTER);
        $alle = [];
        $cursor = '';
        $start = time();

        for ($seite = 0; $seite < self::MAX_PAGES; $seite++) {
            if (time() - $start > self::MAX_SECONDS) {
                throw new RuntimeException(translate('viewers.error.too_long'));
            }

            $abfrage = ['broadcaster_id' => $kanal, 'first' => 100];
            if ($cursor !== '') {
                $abfrage['after'] = $cursor;
            }

            $antwort = $api->get($endpunkt, $abfrage);
            if (!$antwort->ok()) {
                throw new RuntimeException($endpunkt . ': ' . $antwort->error());
            }

            $daten = $antwort->json['data'] ?? [];
            foreach (is_array($daten) ? $daten : [] as $zeile) {
                if (is_array($zeile)) {
                    $alle[] = $zeile;
                }
            }

            $cursor = (string) ($antwort->json['pagination']['cursor'] ?? '');
            if ($cursor === '' || $daten === []) {
                return $alle;
            }
        }

        throw new RuntimeException(translate('viewers.error.too_many'));
    }
}
