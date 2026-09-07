<?php

declare(strict_types=1);

namespace TwitchController\Plugin\LiveNotify;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Support\Http;

/**
 * Das Ziel Discord: ein Webhook, eine Nachricht.
 *
 * Warum ein Webhook und keine Bot-Anbindung: er ist eine Adresse, die
 * man in Discord in zwei Klicks anlegt, und er braucht keinen laufenden
 * Prozess und keine Rechteverwaltung. Fuer "sag Bescheid, wenn jemand
 * live geht" ist das genau richtig.
 *
 * Die Adresse ist ein GEHEIMNIS. Wer sie hat, kann in diesen Kanal
 * schreiben, so oft er will - darum liegt sie verschluesselt und wird
 * in der Oberflaeche nie wieder angezeigt, nur als "gesetzt".
 */
final class Discord
{
    /**
     * Wie lang eine Nachricht sein darf.
     *
     * Discord nimmt 2000 Zeichen. Die Vorlage wird darunter gehalten,
     * damit auch ein langer Stream-Titel nicht zu einer Ablehnung
     * fuehrt, deren Grund man dann im Log sucht.
     */
    public const MAX_MESSAGE = 1500;

    /** Die Vorgabe, wortgleich aus dem alten System. */
    public const DEFAULT_MESSAGE = "🔴 **{{display_name}}** ist jetzt live: {{title}}\nhttps://twitch.tv/{{login}}";

    public static function webhook(App $app): string
    {
        return $app->settings->secret('webhook_url', '', LiveNotify::scope());
    }

    public static function hasWebhook(App $app): bool
    {
        return $app->settings->hasSecret('webhook_url', LiveNotify::scope());
    }

    public static function setWebhook(App $app, string $url): void
    {
        $app->settings->setSecret('webhook_url', trim($url), LiveNotify::scope());
    }

    public static function message(App $app): string
    {
        $wert = trim($app->settings->string('discord_message', '', LiveNotify::scope()));

        return $wert === '' ? self::DEFAULT_MESSAGE : $wert;
    }

    public static function setMessage(App $app, string $vorlage): void
    {
        $vorlage = trim($vorlage);

        // Leer heisst "nimm die Vorgabe" und nicht "schicke nichts":
        // ein leeres Textfeld ist ein haeufiger Weg, etwas
        // zurueckzusetzen, und eine leere Nachricht lehnt Discord ohnehin
        // ab.
        $app->settings->set(
            'discord_message',
            $vorlage === '' ? '' : self::cut($vorlage, self::MAX_MESSAGE),
            LiveNotify::scope()
        );
    }

    /**
     * Eine gueltige Webhook-Adresse?
     *
     * Geprueft wird der Anfang, nicht die Erreichbarkeit: eine Adresse,
     * die nicht zu Discord fuehrt, ist ein Tippfehler, und den soll man
     * beim Speichern erfahren. Ob der Webhook noch existiert, sagt erst
     * das Senden - und dafuer gibt es den Testknopf.
     */
    public static function looksValid(string $url): bool
    {
        $url = trim($url);

        return preg_match('~^https://(?:\w+\.)?discord(?:app)?\.com/api/webhooks/\d+/[\w-]+~', $url) === 1;
    }

    /**
     * Eine Nachricht in den Kanal.
     *
     * @return array{ok: bool, error: string}
     */
    public static function send(App $app, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => translate('live_notify.error.empty_message')];
        }

        $url = self::webhook($app);
        if ($url === '') {
            return ['ok' => false, 'error' => translate('live_notify.error.no_webhook')];
        }

        try {
            $antwort = Http::json('POST', $url, [
                'content' => self::cut($text, self::MAX_MESSAGE),
                // Kein Aufsehen: eine Live-Meldung soll nicht jedes Mal
                // alle im Kanal anpingen, auch wenn im Titel ein @hier
                // steht.
                'allowed_mentions' => ['parse' => []],
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (!$antwort->ok()) {
            return ['ok' => false, 'error' => self::explain($antwort->status, $antwort->body)];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Discords knappe Ablehnungen in einen brauchbaren Satz.
     */
    private static function explain(int $status, string $roh): string
    {
        return match ($status) {
            401, 403 => translate('live_notify.error.webhook_rejected'),
            404 => translate('live_notify.error.webhook_gone'),
            429 => translate('live_notify.error.webhook_rate_limit'),
            default => $roh !== '' ? $roh : translate('live_notify.error.webhook_failed', [
                'status' => (string) $status,
            ]),
        };
    }

    private static function cut(string $text, int $laenge): string
    {
        // Zeichenweise und nicht byteweise: eine Nachricht endet sonst
        // mit einem halben Zeichen, und der Grund waere nicht zu sehen.
        return preg_match('/^.{0,' . $laenge . '}/us', $text, $treffer) === 1 ? $treffer[0] : '';
    }
}
