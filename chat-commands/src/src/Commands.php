<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChatCommands;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Die Befehle und ihre Einstellungen.
 *
 * Zwei Sorten, wie im alten System:
 *
 *   Grundbefehle   fest eingebaut, mit eigenen Feldern. !befehle hat
 *                  keine, !discord hat Anmeldelink und Alter in Tagen.
 *   Eigene Befehle Name und Antworttext, beliebig viele.
 *
 * Beides liegt im Scope "plugin:chat-commands" - der Kern loescht ihn
 * beim Entfernen des Plugins mit.
 */
final class Commands
{
    public const SLUG = 'chat-commands';

    /** Twitch nimmt 500 Zeichen; 400 war schon im alten System die Grenze. */
    public const MAX_RESPONSE = 400;

    /** Mehr wird unuebersichtlich, und !befehle unlesbar lang. */
    public const MAX_CUSTOM = 100;

    /**
     * Was ein Befehlsname sein darf.
     *
     * Kleinbuchstaben, Ziffern, Bindestrich, Unterstrich - dieselbe
     * Regel wie im alten System. Wichtig ist nicht die Strenge, sondern
     * dass sie beim Speichern und beim Erkennen im Chat dieselbe ist:
     * ein Befehl, den man anlegen kann und der dann nie ausloest, waere
     * das Aergerlichste.
     */
    public const NAME_PATTERN = '/^[a-z0-9_-]+$/';

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    /**
     * Reagiert das Plugin ueberhaupt auf Chatnachrichten?
     *
     * Voreinstellung an: wer das Plugin installiert, will Befehle -
     * ein Schalter, den man nach der Installation erst suchen muss,
     * waere eine Falle.
     *
     * Der Schalter sitzt zusaetzlich in der Seitenleiste, damit man
     * mitten im Stream Ruhe herstellen kann, ohne erst hierher zu
     * navigieren. Die eingestellten Befehle bleiben dabei stehen.
     */
    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    // -----------------------------------------------------------------
    //  Grundbefehle
    // -----------------------------------------------------------------

    /**
     * Die eingebauten Befehle samt ihrer Felder.
     *
     * Die Tabelle treibt beides: die Oberflaeche baut daraus die Felder,
     * und das Speichern prueft dagegen. Ein neues Feld steht damit an
     * genau einer Stelle.
     *
     * @return array<string, array{label: string, description: string, hint: string, fields: array<string, array{label: string, type: string, min?: int, max_length?: int}>}>
     */
    public static function builtin(): array
    {
        return [
            'befehle' => [
                'label'       => translate('chat_commands.builtin.befehle'),
                'description' => translate('chat_commands.builtin.befehle.description'),
                'hint'        => '',
                'fields'      => [],
            ],
            'discord' => [
                'label'       => translate('chat_commands.builtin.discord'),
                'description' => translate('chat_commands.builtin.discord.description'),
                'hint'        => translate('chat_commands.builtin.discord.hint'),
                'fields'      => [
                    'invite_link' => [
                        'label'      => translate('chat_commands.field.invite_link'),
                        'type'       => 'text',
                        'max_length' => 400,
                    ],
                    'minimum_follow_days' => [
                        'label' => translate('chat_commands.field.follow_days'),
                        'type'  => 'number',
                        'min'   => 0,
                    ],
                ],
            ],
        ];
    }

    public static function isBuiltin(string $name): bool
    {
        return isset(self::builtin()[$name]);
    }

    /**
     * Die Einstellungen eines Grundbefehls - immer vollstaendig.
     *
     * @return array<string, mixed>
     */
    public static function settingsOf(App $app, string $name): array
    {
        $definition = self::builtin()[$name] ?? null;
        if ($definition === null) {
            return [];
        }

        $gespeichert = $app->settings->get('builtin.' . $name, null, self::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $werte = [];
        foreach ($definition['fields'] as $key => $field) {
            $werte[$key] = self::normalizeField($field, $gespeichert[$key] ?? null);
        }

        return $werte;
    }

    /**
     * @param array<string, mixed> $eingabe
     */
    public static function saveSettings(App $app, string $name, array $eingabe): void
    {
        $definition = self::builtin()[$name] ?? null;
        if ($definition === null) {
            return;
        }

        $werte = [];
        foreach ($definition['fields'] as $key => $field) {
            $werte[$key] = self::normalizeField($field, $eingabe[$key] ?? null);
        }

        $app->settings->set('builtin.' . $name, $werte, self::scope());
    }

    /**
     * @param array{label: string, type: string, min?: int, max_length?: int} $field
     */
    private static function normalizeField(array $field, mixed $wert): mixed
    {
        if (($field['type'] ?? 'text') === 'number') {
            return max((int) ($field['min'] ?? 0), (int) $wert);
        }

        $text = trim((string) $wert);
        $grenze = (int) ($field['max_length'] ?? self::MAX_RESPONSE);

        return function_exists('mb_substr') ? mb_substr($text, 0, $grenze) : substr($text, 0, $grenze);
    }

    // -----------------------------------------------------------------
    //  Eigene Befehle
    // -----------------------------------------------------------------

    /**
     * Alle eigenen Befehle, natuerlich sortiert.
     *
     * @return array<string, string> Name ohne "!" => Antworttext
     */
    public static function custom(App $app): array
    {
        $gespeichert = $app->settings->get('custom', null, self::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $befehle = [];
        foreach ($gespeichert as $name => $antwort) {
            $name = self::normalizeName((string) $name);
            if ($name === '') {
                continue;
            }

            // Aeltere Staende hielten hier ein Array mit 'response'.
            $text = is_array($antwort) ? (string) ($antwort['response'] ?? '') : (string) $antwort;
            $text = self::normalizeResponse($text);
            if ($text === '') {
                continue;
            }

            $befehle[$name] = $text;
        }

        uksort($befehle, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return $befehle;
    }

    /**
     * Einen eigenen Befehl anlegen oder aendern.
     *
     * @return string Leer bei Erfolg, sonst der Grund
     */
    public static function saveCustom(App $app, string $name, string $antwort, string $vorher = ''): string
    {
        $name = self::normalizeName($name);
        if ($name === '') {
            return translate('chat_commands.error.bad_name');
        }

        if (self::isBuiltin($name)) {
            return translate('chat_commands.error.builtin_name', ['name' => $name]);
        }

        $antwort = self::normalizeResponse($antwort);
        if ($antwort === '') {
            return translate('chat_commands.error.empty_response');
        }

        $befehle = self::custom($app);
        $vorher = self::normalizeName($vorher);

        // Umbenennen: der alte Eintrag verschwindet, sonst haette man
        // den Befehl hinterher zweimal.
        if ($vorher !== '' && $vorher !== $name) {
            unset($befehle[$vorher]);
        }

        if (!isset($befehle[$name]) && count($befehle) >= self::MAX_CUSTOM) {
            return translate('chat_commands.error.too_many', ['max' => (string) self::MAX_CUSTOM]);
        }

        $befehle[$name] = $antwort;

        $app->settings->set('custom', $befehle, self::scope());

        return '';
    }

    public static function deleteCustom(App $app, string $name): bool
    {
        $name = self::normalizeName($name);
        $befehle = self::custom($app);

        if ($name === '' || !isset($befehle[$name])) {
            return false;
        }

        unset($befehle[$name]);
        $app->settings->set('custom', $befehle, self::scope());

        return true;
    }

    // -----------------------------------------------------------------
    //  Namen
    // -----------------------------------------------------------------

    /**
     * Aus "!Liebe " wird "liebe" - und aus allem Unerlaubten "".
     */
    public static function normalizeName(string $name): string
    {
        $name = strtolower(trim(ltrim(trim($name), '!')));

        return preg_match(self::NAME_PATTERN, $name) === 1 ? $name : '';
    }

    public static function normalizeResponse(string $text): string
    {
        $text = trim($text);

        return function_exists('mb_substr')
            ? mb_substr($text, 0, self::MAX_RESPONSE)
            : substr($text, 0, self::MAX_RESPONSE);
    }

    /**
     * Alle Namen, die !befehle aufzaehlt.
     *
     * Der Hook ist die Stelle, an der ein anderes Plugin seine Befehle
     * beisteuert. Im alten System standen hier die Timer-Befehle mit
     * drin; ohne Hook waere die Liste nach dem ersten weiteren Plugin
     * unvollstaendig - ohne dass es auffaellt.
     *
     * @return list<string>
     */
    public static function names(App $app): array
    {
        $namen = array_merge(array_keys(self::builtin()), array_keys(self::custom($app)));

        $ergaenzt = $app->hooks->filter('chat_commands.names', $namen);
        if (is_array($ergaenzt)) {
            $namen = $ergaenzt;
        }

        $sauber = [];
        foreach ($namen as $name) {
            $name = self::normalizeName((string) $name);
            if ($name !== '') {
                $sauber[$name] = true;
            }
        }

        $namen = array_keys($sauber);
        sort($namen, SORT_NATURAL | SORT_FLAG_CASE);

        return $namen;
    }
}
