<?php

declare(strict_types=1);

namespace TwitchController\Plugin\DeleteBot;

use TwitchController\Core\App;

/**
 * Der Betrieb: jede Chatnachricht pruefen und bei Treffer loeschen.
 *
 * Laeuft im Webhook-Request (core.chat.message). Twitch wartet auf
 * unsere Antwort, und geloescht werden soll schnell - eine Nachricht,
 * die erst nach zehn Sekunden verschwindet, hat jeder gelesen.
 */
final class Moderator
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array<string, mixed> $message Nutzlast von core.chat.message
     */
    public function handle(array $message): void
    {
        if (!Words::enabled($this->app)) {
            return;
        }

        $text = (string) ($message['text'] ?? '');
        if (trim($text) === '') {
            return;
        }

        $muster = Words::all($this->app);
        if ($muster === []) {
            return;
        }

        $ergebnis = Words::check($text, $muster);

        // Kaputte Muster fallen hier auf, nicht erst wenn jemand sich
        // wundert. Einmal je Nachricht ist zu oft - gemeldet wird nur,
        // wenn ohnehin etwas passiert.
        if (!$ergebnis['blocked']) {
            return;
        }

        $messageId = (string) ($message['message_id'] ?? '');
        if ($messageId === '') {
            $this->app->log('Loeschbot: Treffer, aber ohne Nachrichten-Nummer.');

            return;
        }

        $geloescht = $this->app->chat->delete($messageId);

        if ($geloescht['ok']) {
            $this->app->log(sprintf(
                'Loeschbot: Nachricht von %s geloescht (Muster: %s).',
                (string) ($message['chatter_login'] ?? '?'),
                $ergebnis['pattern']
            ));

            $this->app->hooks->dispatch('delete_bot.deleted', $message, $ergebnis['pattern']);

            return;
        }

        // Der haeufigste Fall hier ist der Kanalinhaber selbst: seine
        // eigenen Nachrichten laesst Twitch niemanden loeschen. Das ist
        // kein Fehler der Einstellung, deshalb steht die Ursache im
        // Log und nicht nur ein "fehlgeschlagen".
        $this->app->log(sprintf(
            'Loeschbot: Treffer bei %s (Muster: %s), aber nicht geloescht: %s',
            (string) ($message['chatter_login'] ?? '?'),
            $ergebnis['pattern'],
            $geloescht['error']
        ));
    }
}
