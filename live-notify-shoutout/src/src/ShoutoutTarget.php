<?php

declare(strict_types=1);

namespace TwitchController\Plugin\LiveNotifyShoutout;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * Der Twitch-Shoutout.
 *
 * Ein Shoutout hat keinen Text - Twitch baut ihn selbst und zeigt ihn
 * im Chat als eigenes Element. Es gibt hier also nichts einzustellen;
 * der Haken in der Kanalzeile ist die ganze Bedienung.
 *
 * Zwei Bedingungen von Twitch, die man kennen muss, weil sie die
 * haeufigsten Gruende fuer "es passiert nichts" sind:
 *
 *   Der eigene Kanal muss LIVE sein. Ein Shoutout ist ein Element im
 *   laufenden Stream; ohne Stream lehnt Twitch ihn ab.
 *
 *   Es gibt Sperrfristen: zwei Minuten zwischen zwei Shoutouts und
 *   sechzig Minuten fuer denselben Kanal. Beides beantwortet Twitch mit
 *   429, und beides ist kein Fehler dieser Anwendung.
 */
final class ShoutoutTarget
{
    public const SLUG = 'live-notify-shoutout';

    /**
     * Ohne diese Freigabe geht kein Shoutout.
     *
     * Sie gilt fuer den Moderator, der ihn ausloest - und das ist hier
     * der Kanalinhaber selbst.
     */
    public const SCOPE = 'moderator:manage:shoutouts';

    /**
     * Hat der Kanal die Freigabe?
     *
     * Gefragt wird das gespeicherte Token, nicht Twitch: die Freigaben
     * stehen beim Token, und eine Ja/Nein-Frage soll keinen Netzaufruf
     * kosten.
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

    /**
     * Einen Shoutout auf einen Kanal ausloesen.
     *
     * @return array{ok: bool, error: string}
     */
    public static function send(App $app, string $zielKanalId): array
    {
        if ($zielKanalId === '') {
            // Ohne die Twitch-Id des Ziels geht es nicht, und der Login
            // hilft nicht weiter: dieser Aufruf kennt nur Ids.
            return ['ok' => false, 'error' => translate('ln_shoutout.error.no_target_id')];
        }

        $eigene = $app->settings->string('twitch_broadcaster_id');
        if ($eigene === '') {
            return ['ok' => false, 'error' => translate('ln_shoutout.error.no_channel')];
        }

        try {
            $antwort = $app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->post('chat/shoutouts', [
                    'from_broadcaster_id' => $eigene,
                    'to_broadcaster_id'   => $zielKanalId,
                    // Der Moderator ist hier der Kanalinhaber selbst -
                    // das Token gehoert ihm, und Twitch verlangt, dass
                    // beides zusammenpasst.
                    'moderator_id'        => $eigene,
                ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (!$antwort->ok()) {
            return ['ok' => false, 'error' => self::explain($antwort->status, $antwort->error())];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Twitchs knappe Ablehnungen in einen brauchbaren Satz.
     *
     * Die 429 ist hier der wichtigste Fall und kein Fehler: sie heisst
     * "Sperrfrist", und wer sie als Stoerung liest, sucht an der
     * falschen Stelle.
     */
    private static function explain(int $status, string $roh): string
    {
        return match ($status) {
            400 => translate('ln_shoutout.error.rejected', ['reason' => $roh]),
            401 => translate('ln_shoutout.error.unauthorized'),
            403 => translate('ln_shoutout.error.forbidden'),
            429 => translate('ln_shoutout.error.cooldown'),
            default => $roh,
        };
    }
}
