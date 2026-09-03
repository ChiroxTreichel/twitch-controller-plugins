<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChatCommands;

use TwitchController\Core\App;

/**
 * Erkennt Befehle im Chat und antwortet.
 *
 * Laeuft im Webhook-Request (Hook core.chat.message). Twitch wartet auf
 * unsere Antwort, deshalb: pruefen, entscheiden, eine Nachricht
 * schicken - nichts, was lange dauert.
 */
final class Dispatcher
{
    /**
     * Unsichtbares Zeichen gegen die Dedup-Sperre von Twitch.
     *
     * U+2063 INVISIBLE SEPARATOR. Siehe unique() fuer den Grund.
     */
    private const INVISIBLE = "\u{2063}";

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array<string, mixed> $message Nutzlast von core.chat.message
     */
    public function handle(array $message): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '' || $text[0] !== '!') {
            return;
        }

        // Hauptschalter. Vor allem anderen, damit "aus" auch nichts
        // ins Log schreibt - sonst sieht es beim Suchen aus, als waere
        // der Befehl angekommen und die Antwort gescheitert.
        if (!Commands::enabled($this->app)) {
            return;
        }

        // Kein Riegel gegen das eigene Konto: ohne Bot-Konto ist das
        // der Kanalinhaber, und der koennte dann keinen Befehl mehr
        // ausloesen. Gegen die Schleife sorgt der Kern - er legt jede
        // gesendete Nachricht sofort ab, sodass die Zweitzustellung
        // ueber den Webhook liegen bleibt und dieser Hook nicht noch
        // einmal feuert. Siehe Chat::rememberOwn().
        $parts = preg_split('/\s+/', $text) ?: [];
        $name = Commands::normalizeName((string) ($parts[0] ?? ''));
        if ($name === '') {
            return;
        }

        // Das alte System schrieb hier eine Zeile ins Log. Ohne sie
        // ist beim Suchen nicht zu unterscheiden, ob der Webhook
        // ausblieb oder ob nur die Antwort scheiterte.
        $this->app->log(sprintf(
            'Chatbefehle: !%s von %s erkannt.',
            $name,
            (string) ($message['chatter_login'] ?? '?')
        ));

        $antwort = $this->answerFor($name, $message, array_values($parts));
        if ($antwort === '') {
            return;
        }

        $ergebnis = $this->app->chat->send($this->unique($antwort));

        if (!$ergebnis['ok']) {
            $this->app->log('Chatbefehle: !' . $name . ' konnte nicht antworten: ' . $ergebnis['error']);
        }
    }

    /**
     * Der Antworttext, oder leer wenn es den Befehl nicht gibt.
     *
     * Reihenfolge wie im alten System: Grundbefehle zuerst. Ein eigener
     * Befehl kann einen Grundbefehl damit nicht ueberschreiben - beim
     * Anlegen wird derselbe Name auch abgelehnt, damit man nicht etwas
     * einstellt, das nie greift.
     *
     * @param array<string, mixed> $message
     * @param list<string>         $parts
     */
    public function answerFor(string $name, array $message, array $parts): string
    {
        if ($name === 'discord') {
            return (new Discord($this->app))->answer($message, $parts);
        }

        if ($name === 'befehle') {
            return $this->commandList($message);
        }

        $eigene = Commands::custom($this->app);
        if (isset($eigene[$name])) {
            return self::fillPlaceholders($eigene[$name], $message);
        }

        // Zuletzt duerfen andere Plugins antworten - die Timer bringen
        // so ihr "!titel" mit. Die eigenen Befehle haben Vorrang, sonst
        // koennte ein Plugin still etwas verdecken, das hier
        // eingestellt ist.
        $fremd = $this->app->hooks->filter('chat_commands.answer', '', $name, $message);

        return is_string($fremd) ? self::fillPlaceholders($fremd, $message) : '';
    }

    /**
     * !befehle - alle Namen alphabetisch, kommagetrennt.
     *
     * @param array<string, mixed> $message
     */
    private function commandList(array $message): string
    {
        $namen = Commands::names($this->app);
        if ($namen === []) {
            return '';
        }

        $mit = array_map(static fn (string $name): string => '!' . $name, $namen);

        return translate('chat_commands.list', [
            'user'     => self::mention((string) ($message['chatter_login'] ?? '')),
            'commands' => implode(', ', $mit),
        ]);
    }

    /**
     * {USER} wird zur Anrede des Schreibers.
     *
     * Bewusst der Login und nicht der Anzeigename: mit "@login" wird
     * die Anrede auf Twitch zu einer echten Erwaehnung, ein
     * Anzeigename mit anderer Schreibweise nicht immer.
     *
     * @param array<string, mixed> $message
     */
    public static function fillPlaceholders(string $text, array $message): string
    {
        return str_replace(
            '{USER}',
            self::mention((string) ($message['chatter_login'] ?? '')),
            $text
        );
    }

    /**
     * Haengt ein unsichtbares Zeichen an, damit Twitch die Nachricht
     * nicht als Wiederholung verwirft.
     *
     * Twitch verwirft eine Nachricht, die mit der vorigen desselben
     * Absenders identisch ist. Fragen zwei Leute hintereinander
     * dasselbe, bekaeme nur der erste eine Antwort - und der zweite
     * gar keine Meldung, es passiert einfach nichts.
     *
     * Deshalb eine wechselnde Anzahl von U+2063 INVISIBLE SEPARATOR:
     * fuer Twitch ein anderer Text, fuer Zuschauer nichts.
     *
     * Im alten System stand hier derselbe Trick - allerdings war die
     * Datei doppelt kodiert, sodass statt des unsichtbaren Zeichens
     * die Bytes von "â£" im Chat landeten. Sichtbar, an jeder Antwort,
     * bis zu zwoelfmal.
     */
    private function unique(string $text): string
    {
        return $text . ' ' . str_repeat(self::INVISIBLE, random_int(1, 12));
    }

    private static function mention(string $login): string
    {
        $login = ltrim(trim($login), '@');

        return $login === '' ? '' : '@' . $login;
    }
}
