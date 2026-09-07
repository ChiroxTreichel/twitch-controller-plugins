<?php

declare(strict_types=1);

namespace TwitchController\Plugin\RaidsRaid;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Twitch\TokenStore;

/**
 * ===================================================================
 *  Den Raid starten
 * ===================================================================
 *
 * Es fuehlt sich an wie "/raid name in den Chat schreiben", und genau
 * das geht NICHT: Twitch fuehrt Schraegstrich-Befehle nur im Chat
 * selbst aus. Eine Nachricht ueber die Schnittstelle ist Text - "/raid
 * xyz" landete als sichtbare Zeile im Chat, und geraidet wuerde
 * niemand. Der richtige Weg ist ein eigener Endpunkt:
 *
 *   POST   helix/raids   startet den Raid
 *   DELETE helix/raids   bricht ihn ab
 *
 * Was Twitch daraus macht, ist dasselbe wie beim Befehl: der Kanal
 * bekommt neunzig Sekunden Vorlauf, in denen die Zuschauer den Hinweis
 * sehen und mitkommen koennen. Erst danach wechseln sie wirklich.
 *
 * Diese neunzig Sekunden sind der Grund fuer den Abbruch-Knopf. Ohne
 * ihn waere ein Fehlgriff endgueltig - und beim Roulette klickt man
 * einmal und bekommt einen Namen, den man sich nicht ausgesucht hat.
 */
final class Raid
{
    public const SLUG = 'raids-raid';

    /** Ohne diese Freigabe laesst Twitch keinen Raid starten. */
    public const SCOPE = 'channel:manage:raids';

    /**
     * Wie lange der Abbruch-Knopf nach einem Start zu sehen ist.
     *
     * Twitch gibt neunzig Sekunden Vorlauf. Etwas mehr, weil die Uhr
     * des Servers und die Anzeige im Browser nicht dieselbe ist - und
     * ein Knopf, der noch da ist, obwohl es nichts mehr abzubrechen
     * gibt, ist harmlos: Twitch antwortet dann mit einem Fehler, und
     * der steht auf der Seite.
     */
    public const CANCEL_WINDOW = 120;

    private static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Starten und abbrechen
    // -----------------------------------------------------------------

    /**
     * Den Raid auf einen Kanal starten.
     *
     * @return array{ok: bool, error: string}
     */
    public static function start(App $app, string $zielId): array
    {
        $eigene = $app->settings->string('twitch_broadcaster_id');
        if ($eigene === '') {
            return ['ok' => false, 'error' => translate('raids_raid.error.no_channel')];
        }

        if ($zielId === '') {
            return ['ok' => false, 'error' => translate('raids_raid.error.unknown_channel')];
        }

        if ($zielId === $eigene) {
            return ['ok' => false, 'error' => translate('raids_raid.error.self')];
        }

        try {
            $antwort = $app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->post('raids', [
                    'from_broadcaster_id' => $eigene,
                    'to_broadcaster_id'   => $zielId,
                ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (!$antwort->ok()) {
            return ['ok' => false, 'error' => self::explain($antwort->status, $antwort->error())];
        }

        // Wann gestartet wurde - daran haengt der Abbruch-Knopf. Ein
        // Zeitstempel und kein Schalter: ein Schalter muesste
        // zurueckgestellt werden, und niemand ist da, der das nach
        // neunzig Sekunden tut.
        $app->settings->set('started_at', time(), self::scope());

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Den laufenden Raid abbrechen.
     *
     * @return array{ok: bool, error: string}
     */
    public static function cancel(App $app): array
    {
        $eigene = $app->settings->string('twitch_broadcaster_id');
        if ($eigene === '') {
            return ['ok' => false, 'error' => translate('raids_raid.error.no_channel')];
        }

        try {
            $antwort = $app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->delete('raids', ['broadcaster_id' => $eigene]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        // Der Zeitstempel geht in JEDEM Fall weg, auch wenn Twitch
        // ablehnt: dann laeuft schon keiner mehr, und der Knopf soll
        // nicht stehen bleiben.
        $app->settings->set('started_at', 0, self::scope());

        if (!$antwort->ok()) {
            return ['ok' => false, 'error' => self::explain($antwort->status, $antwort->error())];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Laeuft gerade ein Raid, den man abbrechen koennte?
     *
     * Geraten und nicht gefragt: Twitch hat keinen Endpunkt fuer "ist
     * ein Raid im Vorlauf". Gemerkt wird der Start, und daraus folgt
     * das Fenster.
     */
    public static function pending(App $app): bool
    {
        $start = $app->settings->int('started_at', 0, self::scope());

        return $start > 0 && (time() - $start) < self::CANCEL_WINDOW;
    }

    // -----------------------------------------------------------------
    //  Kann das ueberhaupt?
    // -----------------------------------------------------------------

    /**
     * Hat der Kanal die Freigabe zum Raiden?
     *
     * Gefragt wird das gespeicherte Token und nicht Twitch - die
     * Freigaben stehen beim Token, und eine Seite soll fuer eine
     * Ja/Nein-Frage keinen Netzaufruf machen.
     */
    public static function ready(App $app): bool
    {
        try {
            return $app->twitch->tokens()->missingScopes(
                TokenStore::BROADCASTER,
                [self::SCOPE]
            ) === [];
        } catch (Throwable) {
            return false;
        }
    }

    private static function explain(int $status, string $roh): string
    {
        return match ($status) {
            400 => translate('raids_raid.error.rejected'),
            401 => translate('raids_raid.error.unauthorized'),
            403 => translate('raids_raid.error.forbidden'),
            404 => translate('raids_raid.error.nothing_to_cancel'),
            409 => translate('raids_raid.error.already_running'),
            429 => translate('raids_raid.error.rate_limit'),
            default => $roh,
        };
    }
}
