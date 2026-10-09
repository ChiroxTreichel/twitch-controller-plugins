<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Giveaway;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Plugin\ChatCommands\Commands;

/**
 * Was eingestellt ist: Schalter, Befehl, Texte, Preise, Werbung.
 *
 * Gespeichert wird im Scope "plugin:giveaway". Wo die Ziehung gerade
 * steht, haelt Draw; wer wie viele Tickets hat, Tickets.
 *
 * Platzhalter wie bei den Alerts: {{ username }}, mit und ohne
 * Leerzeichen. Einer, der in einem Text nichts bedeutet, faellt weg -
 * "{{ tickets }}" mitten im Chat saehe nach Fehler aus, ein fehlendes
 * Wort nach Absicht.
 */
final class Giveaway
{
    public const SLUG = 'giveaway';
    public const SLOT = 'giveaway';
    public const TIMER_ID = 'giveaway';

    /** Abstand zwischen zwei Tickets je Zuschauer, in Minuten. */
    public const INTERVAL_MIN = 1;
    public const INTERVAL_MAX = 240;

    /**
     * Grenzen der Werbung im Chat - dieselben wie im Timer-Plugin. Das
     * erzwingt sie ohnehin; hier stehen sie, damit das Feld nicht
     * etwas annimmt, das hinterher stillschweigend anders gilt.
     */
    public const TIMER_MIN = 5;
    public const TIMER_MAX = 120;

    public const MAX_PRIZES = 50;
    public const MAX_PRIZE = 100;

    /** Twitch nimmt 500 Zeichen; Name und Preis kommen noch dazu. */
    public const MAX_MESSAGE = 400;

    /**
     * Welche Platzhalter in welchem Text etwas bedeuten - fuer die
     * Hinweise unter den Feldern. Was hier steht, muss beim Fuellen
     * auch geliefert werden.
     */
    public const PLACEHOLDERS = [
        'message_ok'     => ['username', 'tickets', 'added', 'command', 'interval'],
        'message_wait'   => ['username', 'wait', 'tickets', 'command'],
        'message_closed' => ['username', 'command'],
        'announce'       => ['username', 'prize'],
        'timer_message'  => ['command', 'interval', 'prizes'],
    ];

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    /**
     * Aus ist die Vorgabe: ein frisch installiertes Giveaway soll nicht
     * Tickets verteilen, bevor jemand Preise eingetragen hat.
     */
    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', false, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    // -----------------------------------------------------------------
    //  Befehl, Texte, Werbung
    // -----------------------------------------------------------------

    /**
     * Alles Eingestellte, immer vollstaendig.
     *
     * Ein Text, der nie gespeichert wurde, kommt aus der Sprachdatei.
     * Ein gespeicherter LEERER Text bleibt leer - er heisst "nichts
     * sagen", und das darf man wollen.
     *
     * @return array{command: string, interval: int, sub_bonus: bool, multiple_wins: bool,
     *               message_ok: string, message_wait: string, message_closed: string, announce: string,
     *               timer_enabled: bool, timer_interval: int, timer_lines: int, timer_message: string}
     */
    public static function config(App $app): array
    {
        $s = $app->settings;
        $scope = self::scope();

        $text = static function (string $key, string $vorgabe) use ($s, $scope): string {
            $wert = $s->get($key, null, $scope);

            return is_string($wert) ? $wert : $vorgabe;
        };

        return [
            'command'        => Commands::normalizeName($s->string('command', 'ticket', $scope)) ?: 'ticket',
            'interval'       => self::clamp($s->int('interval', 10, $scope), self::INTERVAL_MIN, self::INTERVAL_MAX),
            'sub_bonus'      => $s->bool('sub_bonus', false, $scope),
            'multiple_wins'  => $s->bool('multiple_wins', false, $scope),
            'message_ok'     => $text('message_ok', translate('giveaway.default.message_ok')),
            'message_wait'   => $text('message_wait', translate('giveaway.default.message_wait')),
            'message_closed' => $text('message_closed', translate('giveaway.default.message_closed')),
            'announce'       => $text('announce', translate('giveaway.default.announce')),
            'timer_enabled'  => $s->bool('timer_enabled', false, $scope),
            'timer_interval' => self::clamp($s->int('timer_interval', 15, $scope), self::TIMER_MIN, self::TIMER_MAX),
            'timer_lines'    => self::clamp($s->int('timer_lines', 10, $scope), 0, 1000),
            'timer_message'  => $text('timer_message', translate('giveaway.default.timer_message')),
        ];
    }

    /**
     * Befehl, Intervall, Schalter und Texte speichern.
     *
     * Ein Schalter ist an, wenn sein Feld nicht leer ist - eine nicht
     * angehakte Checkbox schickt gar nichts, und "" wie false heissen aus.
     *
     * @param array<string, mixed> $eingabe
     * @return string leer bei Erfolg, sonst der Grund
     */
    public static function saveCommand(App $app, array $eingabe): string
    {
        $name = Commands::normalizeName((string) ($eingabe['command'] ?? ''));

        if ($name === '') {
            return translate('giveaway.error.bad_command');
        }

        // Ein Befehl, den es schon gibt, wuerde nie greifen: Grund- und
        // eigene Befehle antworten vor jedem Plugin. Der eigene Name
        // steht ueber unseren Haken selbst in der Liste - der zaehlt
        // nicht.
        $vergeben = array_diff(Commands::names($app), [self::config($app)['command']]);
        if (in_array($name, $vergeben, true)) {
            return translate('giveaway.error.command_taken', ['command' => '!' . $name]);
        }

        $scope = self::scope();
        $app->settings->set('command', $name, $scope);
        $app->settings->set('interval', self::clamp((int) ($eingabe['interval'] ?? 10), self::INTERVAL_MIN, self::INTERVAL_MAX), $scope);
        $app->settings->set('sub_bonus', !empty($eingabe['sub_bonus']), $scope);
        $app->settings->set('multiple_wins', !empty($eingabe['multiple_wins']), $scope);

        foreach (['message_ok', 'message_wait', 'message_closed', 'announce'] as $feld) {
            $app->settings->set($feld, self::cut((string) ($eingabe[$feld] ?? ''), self::MAX_MESSAGE), $scope);
        }

        return '';
    }

    /**
     * Die Werbung im Chat speichern.
     *
     * @param array<string, mixed> $eingabe
     */
    public static function saveTimer(App $app, array $eingabe): void
    {
        $scope = self::scope();
        $app->settings->set('timer_enabled', !empty($eingabe['timer_enabled']), $scope);
        $app->settings->set('timer_interval', self::clamp((int) ($eingabe['timer_interval'] ?? 15), self::TIMER_MIN, self::TIMER_MAX), $scope);
        $app->settings->set('timer_lines', self::clamp((int) ($eingabe['timer_lines'] ?? 10), 0, 1000), $scope);
        $app->settings->set('timer_message', self::cut((string) ($eingabe['timer_message'] ?? ''), self::MAX_MESSAGE), $scope);
    }

    /**
     * Der Text der Werbung - oder nichts.
     *
     * Nur, solange Tickets zu holen sind. Waehrend der Ziehung fuer den
     * Befehl zu werben, hiesse Leute zu etwas einzuladen, das gerade
     * zu ist.
     */
    public static function timerMessage(App $app): string
    {
        if (!self::enabled($app) || (new Draw($app))->phase() !== Draw::COLLECT) {
            return '';
        }

        $config = self::config($app);

        return self::fill($config['timer_message'], [
            'command'  => '!' . $config['command'],
            'interval' => (string) $config['interval'],
            'prizes'   => implode(', ', self::prizes($app)),
        ]);
    }

    // -----------------------------------------------------------------
    //  Preise
    // -----------------------------------------------------------------

    /**
     * Die Preise, oben zuerst. Gezogen wird von unten nach oben - der
     * oberste kommt zuletzt, wie der Hauptpreis am Ende einer Show.
     *
     * @return list<string>
     */
    public static function prizes(App $app): array
    {
        $gespeichert = $app->settings->get('prizes', null, self::scope());

        if (!is_array($gespeichert)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $p): string => is_scalar($p) ? trim((string) $p) : '', $gespeichert),
            static fn (string $p): bool => $p !== ''
        ));
    }

    /** @return string leer bei Erfolg, sonst der Grund */
    public static function addPrize(App $app, string $preis): string
    {
        $preis = self::cut(trim((string) preg_replace('/\s+/u', ' ', $preis)), self::MAX_PRIZE);

        if ($preis === '') {
            return translate('giveaway.error.prize_empty');
        }

        $alle = self::prizes($app);
        if (count($alle) >= self::MAX_PRIZES) {
            return translate('giveaway.error.too_many_prizes', ['max' => (string) self::MAX_PRIZES]);
        }

        $alle[] = $preis;
        $app->settings->set('prizes', $alle, self::scope());

        return '';
    }

    public static function removePrize(App $app, int $index): bool
    {
        $alle = self::prizes($app);
        if (!isset($alle[$index])) {
            return false;
        }

        array_splice($alle, $index, 1);
        $app->settings->set('prizes', $alle, self::scope());

        return true;
    }

    public static function movePrize(App $app, int $index, string $richtung): bool
    {
        $alle = self::prizes($app);
        $ziel = $richtung === 'up' ? $index - 1 : $index + 1;

        if (!isset($alle[$index], $alle[$ziel])) {
            return false;
        }

        [$alle[$index], $alle[$ziel]] = [$alle[$ziel], $alle[$index]];
        $app->settings->set('prizes', $alle, self::scope());

        return true;
    }

    // -----------------------------------------------------------------
    //  Texte fuellen
    // -----------------------------------------------------------------

    /**
     * Platzhalter ersetzen, in einem Durchgang.
     *
     * Ein Durchgang und nicht ein str_replace je Wert: sonst wuerde ein
     * Preis, der selbst "{{ username }}" heisst, im naechsten Schritt
     * noch einmal ersetzt.
     *
     * @param array<string, string> $werte
     */
    public static function fill(string $text, array $werte): string
    {
        $text = (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            static fn (array $treffer): string => (string) ($werte[$treffer[1]] ?? ''),
            $text
        );

        return trim((string) preg_replace('/ {2,}/', ' ', $text));
    }

    /**
     * "@login" - im Chat wird das zur echten Erwaehnung, und der
     * Gewinner bekommt mit, dass er gemeint ist.
     */
    public static function mention(string $login): string
    {
        $login = ltrim(trim($login), '@');

        return $login === '' ? '' : '@' . $login;
    }

    /** "4 Minuten", "1 Minute", "30 Sekunden" - fuer {{ wait }}. */
    public static function duration(int $sekunden): string
    {
        $sekunden = max(1, $sekunden);

        if ($sekunden >= 60) {
            $minuten = (int) ceil($sekunden / 60);

            return $minuten === 1
                ? translate('giveaway.wait.minute')
                : translate('giveaway.wait.minutes', ['count' => (string) $minuten]);
        }

        return $sekunden === 1
            ? translate('giveaway.wait.second')
            : translate('giveaway.wait.seconds', ['count' => (string) $sekunden]);
    }

    private static function clamp(int $wert, int $min, int $max): int
    {
        return max($min, min($max, $wert));
    }

    private static function cut(string $text, int $max): string
    {
        return mb_substr(trim($text), 0, $max);
    }
}
