<?php

declare(strict_types=1);

namespace TwitchController\Plugin\LiveNotifyChat;

use Throwable;
use TwitchController\Core\Chat\Chat;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\App;

/**
 * Die Vorlage und die Frage, ob ueberhaupt gesendet werden kann.
 *
 * Wenig Code, und das ist der Punkt: gesendet wird ueber die
 * Kernfaehigkeit Chat. Sie kennt den richtigen Absender, kuerzt auf
 * Twitchs 500 Zeichen und merkt sich die eigene Nachricht, damit kein
 * Chatbefehl auf sie anspringt. Ein eigener Weg zu Helix waere ein
 * zweiter Ort, an dem das alles noch einmal richtig sein muesste.
 */
final class ChatTarget
{
    public const SLUG = 'live-notify-chat';

    /**
     * Wie lang die Vorlage sein darf.
     *
     * Twitch nimmt 500 Zeichen, und Chat::send() kuerzt darauf. Hier
     * ist die Grenze knapper: in die Vorlage kommen noch Titel und
     * Spielname, und eine Vorlage, die schon allein zu lang ist, ergibt
     * nie eine vollstaendige Nachricht.
     */
    public const MAX_MESSAGE = 300;

    /** Die Vorgabe, wortgleich aus dem alten System. */
    public const DEFAULT_MESSAGE = '🔴 {{display_name}} ist jetzt live: https://twitch.tv/{{login}}';

    private static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    public static function message(App $app): string
    {
        $wert = trim($app->settings->string('message', '', self::scope()));

        return $wert === '' ? self::DEFAULT_MESSAGE : $wert;
    }

    public static function setMessage(App $app, string $vorlage): void
    {
        $vorlage = trim($vorlage);

        // Leer heisst "nimm die Vorgabe" und nicht "schicke nichts": ein
        // leeres Textfeld ist ein haeufiger Weg, etwas zurueckzusetzen.
        $app->settings->set(
            'message',
            $vorlage === '' ? '' : self::cut($vorlage, self::MAX_MESSAGE),
            self::scope()
        );
    }

    /**
     * Gibt es einen Absender fuer den Chat?
     *
     * Gefragt wird die Kernfaehigkeit, nicht Twitch: sie entscheidet, ob
     * das Bot-Konto oder der Kanalinhaber schreibt, und ohne beides geht
     * nichts. Eine Ja/Nein-Frage soll keinen Netzaufruf kosten.
     */
    public static function canSend(App $app): bool
    {
        try {
            $chat = new Chat($app);

            return $chat->senderId($chat->senderPurpose()) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private static function cut(string $text, int $laenge): string
    {
        // Zeichenweise und nicht byteweise: eine Vorlage endet sonst mit
        // einem halben Zeichen, und der Grund waere nicht zu sehen.
        return preg_match('/^.{0,' . $laenge . '}/us', $text, $treffer) === 1 ? $treffer[0] : '';
    }
}
