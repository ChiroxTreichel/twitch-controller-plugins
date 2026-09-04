<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Raids;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Twitch\TokenStore;

/**
 * ===================================================================
 *  Wem folgt der Kanal, und wer davon streamt noch?
 * ===================================================================
 *
 * Die Frage klingt einfach und ist bei Twitch drei Abfragen:
 *
 *   channels/followed   wem folgt der Kanal (blaettert, 100 je Seite)
 *   videos              wann hat dieser Kanal zuletzt gestreamt
 *   streams             wer davon ist JETZT live
 *
 * Die erste ist billig, die zweite teuer: sie geht je Kanal einmal
 * hinaus. Bei zweihundert Follows sind das zweihundert Aufrufe, und
 * Twitch zaehlt mit. Darum wird sie gestreckt - siehe
 * refreshActivity().
 *
 * "Hat noch gestreamt" wird am letzten Video gemessen und nicht an
 * einer Statistik: Twitch verraet nicht, wann jemand zuletzt live war,
 * aber ein Archiv-Video entsteht bei jedem Stream. Wer seine Aufnahmen
 * abschaltet, faellt damit aus der Liste - das ist die Naeherung, die
 * das alte System schon benutzt hat, und sie ist gut genug fuer die
 * Frage "wen kann ich raiden".
 */
final class Channels
{
    public const SLUG = 'raids';

    /**
     * Ohne diese Freigabe gibt es keine Follow-Liste.
     *
     * Alles andere in diesem Plugin arbeitet mit dem, was schon in der
     * Tabelle steht - die Liste ist also der einzige Teil, der ohne sie
     * leer bleibt.
     */
    public const SCOPE = 'user:read:follows';

    /**
     * Wie lange ein Stream zurueckliegen darf, damit der Kanal als
     * aktiv gilt. Wie im alten System.
     */
    public const ACTIVE_DAYS = 30;

    /**
     * Wie oft die Follow-Liste neu geholt wird, und wie oft ein
     * einzelner Kanal auf seinen letzten Stream geprueft wird.
     *
     * Einmal am Tag genuegt fuer beides: wem man folgt, aendert sich
     * selten, und "hat in den letzten 30 Tagen gestreamt" wird von
     * einem Tag Verzug nicht falsch.
     */
    public const REFRESH_SECONDS = 86400;

    /**
     * Wie viele Kanaele je Cron-Tick geprueft werden.
     *
     * Klein gehalten, damit sich die Last verteilt: der Worker tickt
     * alle 15 Sekunden, zehn Kanaele je Tick sind also vierzig in der
     * Minute. Zweihundert Follows sind damit in fuenf Minuten durch -
     * und Twitch sieht nie einen Schwung von zweihundert Abfragen.
     */
    public const BATCH = 10;

    /** Wie viele Logins Twitch je Live-Abfrage annimmt. */
    private const LIVE_CHUNK = 100;

    public function __construct(private readonly App $app)
    {
    }

    private static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Was in der Tabelle steht
    // -----------------------------------------------------------------

    /**
     * Die Kanaele, die in den letzten 30 Tagen selbst gestreamt haben.
     *
     * Favoriten zuerst, dann nach Namen - so wie im alten System. Die
     * Sortierung passiert in der Abfrage und nicht danach in PHP: es
     * ist dieselbe Arbeit, und die Datenbank kann sie.
     *
     * @return list<array{login: string, display_name: string, profile_image_url: string, favorite: bool}>
     */
    public function active(): array
    {
        $rows = $this->app->db->all(
            "SELECT login, display_name, profile_image_url, favorite
               FROM raid_channels
              WHERE last_active_at IS NOT NULL
                AND last_active_at > now() - (:tage || ' days')::interval
              ORDER BY favorite DESC, lower(display_name)",
            ['tage' => (string) self::ACTIVE_DAYS]
        );

        return array_map(static fn (array $row): array => [
            'login'             => (string) $row['login'],
            'display_name'      => (string) ($row['display_name'] ?: $row['login']),
            'profile_image_url' => (string) $row['profile_image_url'],
            'favorite'          => (bool) $row['favorite'],
        ], $rows);
    }

    /**
     * Die Logins der Favoriten.
     *
     * @return list<string>
     */
    public function favorites(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['login'],
            $this->app->db->all('SELECT login FROM raid_channels WHERE favorite ORDER BY lower(display_name)')
        );
    }

    /**
     * Einen Kanal als Favorit merken oder nicht mehr.
     *
     * Nur bekannte Kanaele: die Liste kommt von Twitch, und ein Login
     * aus einem Formular ist kein Grund, eine Zeile anzulegen. Wer
     * einem Kanal nicht folgt, kann ihn hier auch nicht merken.
     */
    public function setFavorite(string $login, bool $favorit): bool
    {
        $login = self::normalizeLogin($login);
        if ($login === '') {
            return false;
        }

        $this->app->db->run(
            'UPDATE raid_channels SET favorite = :wert WHERE login = :login',
            ['wert' => $favorit, 'login' => $login]
        );

        return true;
    }

    public function count(): int
    {
        return (int) $this->app->db->value('SELECT count(*) FROM raid_channels');
    }

    public function syncedAt(): int
    {
        return $this->app->settings->int('synced_at', 0, self::scope());
    }

    /** Wie viele Kanaele noch auf ihre erste Pruefung warten. */
    public function pending(): int
    {
        return (int) $this->app->db->value(
            "SELECT count(*) FROM raid_channels
              WHERE checked_at IS NULL
                 OR checked_at < now() - (:sek || ' seconds')::interval",
            ['sek' => (string) self::REFRESH_SECONDS]
        );
    }

    // -----------------------------------------------------------------
    //  Wer ist jetzt live?
    // -----------------------------------------------------------------

    /**
     * Die Favoriten, die gerade streamen.
     *
     * Frisch bei Twitch gefragt und nicht gespeichert: "gerade live"
     * ist in einer Minute falsch, und ein gespeicherter Wert waere
     * genau die Art Auskunft, auf die man sich verlaesst und die dann
     * nicht stimmt.
     *
     * @return list<array{login: string, display_name: string, title: string, game_name: string, profile_image_url: string}>
     */
    public function live(): array
    {
        $logins = $this->favorites();
        if ($logins === []) {
            return [];
        }

        // Die Bilder stehen schon in der Tabelle - der Live-Aufruf
        // liefert sie nicht mit, und ein zweiter Aufruf nur fuer
        // Avatare waere Verschwendung.
        $bilder = [];
        foreach ($this->app->db->all('SELECT login, profile_image_url FROM raid_channels WHERE favorite') as $row) {
            $bilder[(string) $row['login']] = (string) $row['profile_image_url'];
        }

        $live = [];

        foreach (array_chunk($logins, self::LIVE_CHUNK) as $haeufchen) {
            $antwort = $this->get('streams', ['user_login' => $haeufchen]);

            foreach ($antwort as $zeile) {
                $login = self::normalizeLogin((string) ($zeile['user_login'] ?? ''));
                if ($login === '') {
                    continue;
                }

                $live[] = [
                    'login'             => $login,
                    'display_name'      => (string) ($zeile['user_name'] ?? $login),
                    'title'             => (string) ($zeile['title'] ?? ''),
                    'game_name'         => (string) ($zeile['game_name'] ?? ''),
                    'profile_image_url' => $bilder[$login] ?? '',
                ];
            }
        }

        usort(
            $live,
            static fn (array $a, array $b): int => strnatcasecmp($a['display_name'], $b['display_name'])
        );

        return $live;
    }

    // -----------------------------------------------------------------
    //  Abgleich mit Twitch
    // -----------------------------------------------------------------

    /**
     * Die Follow-Liste holen und die Tabelle darauf einstellen.
     *
     * Wer neu dabei ist, kommt hinzu; wer entfolgt wurde, geht. Was
     * schon bekannt ist, behaelt seinen Aktivitaetsstand und seinen
     * Favoritenhaken - sonst begaenne die Pruefung nach jedem Abgleich
     * von vorn, und ein Haken verschwaende sich von selbst.
     *
     * @return int wie viele Kanaele danach in der Liste stehen, -1 bei
     *             einem Fehler (siehe error())
     */
    public function sync(): int
    {
        $kanalId = $this->app->settings->string('twitch_broadcaster_id');
        if ($kanalId === '') {
            $this->fehler = translate('raids.error.no_channel');

            return -1;
        }

        $gefunden = [];
        $cursor = '';

        // Geblaettert, bis Twitch keinen Zeiger mehr nennt. Eine Grenze
        // von 50 Seiten als Notbremse: 5000 Follows sind mehr als
        // plausibel, und eine Endlosschleife im Worker faellt niemandem
        // auf.
        for ($seite = 0; $seite < 50; $seite++) {
            $query = ['user_id' => $kanalId, 'first' => 100];
            if ($cursor !== '') {
                $query['after'] = $cursor;
            }

            try {
                $antwort = $this->app->twitch->api()
                    ->as(TokenStore::BROADCASTER)
                    ->get('channels/followed', $query);
            } catch (Throwable $e) {
                $this->fehler = $e->getMessage();

                return -1;
            }

            if (!$antwort->ok()) {
                $this->fehler = $this->explain($antwort->status, $antwort->error());

                return -1;
            }

            foreach (($antwort->json['data'] ?? []) as $zeile) {
                if (!is_array($zeile)) {
                    continue;
                }

                $login = self::normalizeLogin((string) ($zeile['broadcaster_login'] ?? ''));
                if ($login === '') {
                    continue;
                }

                $gefunden[$login] = [
                    'user_id'      => (string) ($zeile['broadcaster_id'] ?? ''),
                    'display_name' => (string) ($zeile['broadcaster_name'] ?? $login),
                    'followed_at'  => (string) ($zeile['followed_at'] ?? ''),
                ];
            }

            $cursor = trim((string) ($antwort->json['pagination']['cursor'] ?? ''));
            if ($cursor === '') {
                break;
            }
        }

        if ($gefunden === []) {
            // Eine leere Antwort ist kein Grund, die Liste zu leeren:
            // wahrscheinlicher als "folgt niemandem mehr" ist eine
            // Stoerung, und der Favoritenhaken ist zu wertvoll fuer
            // diese Wette.
            $this->fehler = translate('raids.error.empty_list');

            return -1;
        }

        foreach ($gefunden as $login => $info) {
            $this->app->db->run(
                "INSERT INTO raid_channels (login, user_id, display_name, followed_at)
                      VALUES (:login, :id, :name, NULLIF(:followed, '')::timestamptz)
                 ON CONFLICT (login) DO UPDATE
                    SET user_id      = EXCLUDED.user_id,
                        display_name = EXCLUDED.display_name,
                        followed_at  = EXCLUDED.followed_at",
                [
                    'login'    => $login,
                    'id'       => $info['user_id'],
                    'name'     => $info['display_name'],
                    'followed' => $info['followed_at'],
                ]
            );
        }

        // Entfolgte weg. Mit einer Abfrage und nicht einer je Zeile:
        // die Liste kann hunderte Namen lang sein.
        $platzhalter = [];
        $werte = [];
        $i = 0;

        foreach (array_keys($gefunden) as $login) {
            $platzhalter[] = ':l' . $i;
            $werte['l' . $i] = $login;
            $i++;
        }

        $this->app->db->run(
            'DELETE FROM raid_channels WHERE login NOT IN (' . implode(', ', $platzhalter) . ')',
            $werte
        );

        $this->app->settings->set('synced_at', time(), self::scope());

        return count($gefunden);
    }

    /**
     * Fuer eine Handvoll Kanaele nachsehen, wann sie zuletzt gestreamt
     * haben.
     *
     * Genommen werden die am laengsten nicht geprueften. Damit kommt
     * jeder Kanal irgendwann dran, ohne dass jemand mitzaehlen muss -
     * und ein neu dazugekommener steht vorn, weil er noch nie geprueft
     * wurde.
     *
     * @return int wie viele Kanaele geprueft wurden
     */
    public function refreshActivity(int $anzahl = self::BATCH): int
    {
        $rows = $this->app->db->all(
            "SELECT login, user_id, profile_image_url
               FROM raid_channels
              WHERE user_id <> ''
                AND (checked_at IS NULL
                     OR checked_at < now() - (:sek || ' seconds')::interval)
              ORDER BY checked_at NULLS FIRST
              LIMIT " . max(1, min(100, $anzahl)),
            ['sek' => (string) self::REFRESH_SECONDS]
        );

        $geprueft = 0;

        foreach ($rows as $row) {
            $login = (string) $row['login'];
            $userId = (string) $row['user_id'];

            $videos = $this->get('videos', [
                'user_id' => $userId,
                'type'    => 'archive',
                'first'   => 1,
            ]);

            $zuletzt = '';
            if (isset($videos[0]) && is_array($videos[0])) {
                $zuletzt = (string) ($videos[0]['created_at'] ?? '');
            }

            // Das Bild nur holen, wenn es fehlt. Es aendert sich selten,
            // und ein Aufruf je Kanal je Tag waere die Haelfte der
            // gesamten Last fuer ein Bild, das gleich bleibt.
            $bild = (string) $row['profile_image_url'];
            if ($bild === '') {
                $nutzer = $this->get('users', ['id' => $userId]);
                if (isset($nutzer[0]) && is_array($nutzer[0])) {
                    $bild = (string) ($nutzer[0]['profile_image_url'] ?? '');
                }
            }

            // checked_at wird IMMER gesetzt, auch wenn die Abfrage
            // nichts ergab. Sonst kaeme derselbe Kanal bei jedem Tick
            // wieder an die Reihe und alle anderen nie.
            $this->app->db->run(
                "UPDATE raid_channels
                    SET last_active_at    = NULLIF(:zuletzt, '')::timestamptz,
                        profile_image_url = :bild,
                        checked_at        = now()
                  WHERE login = :login",
                ['zuletzt' => $zuletzt, 'bild' => $bild, 'login' => $login]
            );

            $geprueft++;
        }

        return $geprueft;
    }

    /**
     * Beides in einem: Liste abgleichen, wenn sie alt ist, und eine
     * Handvoll Kanaele pruefen. Fuer den Cron-Tick.
     */
    public function tick(): void
    {
        if (time() - $this->syncedAt() >= self::REFRESH_SECONDS || $this->count() === 0) {
            if ($this->sync() < 0) {
                $this->app->log('Raids: Abgleich der Follow-Liste fehlgeschlagen: ' . $this->error());
            }
        }

        $this->refreshActivity();
    }

    // -----------------------------------------------------------------
    //  Twitch
    // -----------------------------------------------------------------

    /**
     * Eine Helix-Abfrage, deren Datenteil zurueckkommt.
     *
     * Ein Fehler ergibt eine leere Liste und eine Zeile im Log. Fuer
     * diese Funktion ist das richtig: sie laeuft im Worker, und ein
     * Kanal, der sich heute nicht pruefen liess, wird morgen wieder
     * genommen.
     *
     * @param array<string, string|int|list<string>> $query
     * @return list<mixed>
     */
    private function get(string $endpunkt, array $query): array
    {
        try {
            $antwort = $this->app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->get($endpunkt, $query);
        } catch (Throwable $e) {
            $this->fehler = $e->getMessage();
            $this->app->log('Raids: ' . $endpunkt . ' nicht abrufbar: ' . $e->getMessage());

            return [];
        }

        if (!$antwort->ok()) {
            $this->fehler = $this->explain($antwort->status, $antwort->error());
            $this->app->log('Raids: ' . $endpunkt . ' nicht abrufbar: ' . $antwort->error());

            return [];
        }

        $daten = $antwort->json['data'] ?? [];

        return is_array($daten) ? array_values($daten) : [];
    }

    private string $fehler = '';

    public function error(): string
    {
        return $this->fehler;
    }

    /**
     * Hat der Kanal die Freigabe fuer die Follow-Liste?
     *
     * Gefragt wird das gespeicherte Token und nicht Twitch - die
     * Freigaben stehen beim Token, und eine Seite soll fuer eine
     * Ja/Nein-Frage keinen Netzaufruf machen.
     */
    public function canRead(): bool
    {
        try {
            return $this->app->twitch->tokens()->missingScopes(
                TokenStore::BROADCASTER,
                [self::SCOPE]
            ) === [];
        } catch (Throwable) {
            return false;
        }
    }

    private function explain(int $status, string $roh): string
    {
        return match ($status) {
            401 => translate('raids.error.unauthorized'),
            403 => translate('raids.error.forbidden'),
            429 => translate('raids.error.rate_limit'),
            default => $roh,
        };
    }

    public static function normalizeLogin(string $login): string
    {
        return strtolower(trim($login));
    }
}
