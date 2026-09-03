<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Alerts;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Overlay\Bus;

/**
 * ===================================================================
 *  Der Rahmen fuer Alerts
 * ===================================================================
 *
 * Dieses Plugin bringt keinen einzigen Alert mit. Es bringt:
 *
 *   1. den Bereich "Anzeigen > Alerts" samt Reitern
 *   2. die Flaeche im Overlay und die Warteschlange davor
 *   3. die Grundeinstellungen - Breite, Mediengroesse, Hauptschalter
 *
 * Was ein Alert IST, liefern andere Plugins. Twitch-Alerts meldet
 * seine Reiter an und schickt seine Alerts hierher:
 *
 *   use TwitchController\Plugin\Alerts\Alerts;
 *
 *   Alerts::send($app, [
 *       'text'     => '{{ username }} hat neu abonniert',
 *       'values'   => ['username' => $name],
 *       'video'    => '/uploads/alerts/sub.webm',
 *       'audio'    => '/uploads/alerts/sub.mp3',
 *       'duration' => 8,
 *   ]);
 *
 * Einen Reiter meldet ein Plugin so an:
 *
 *   $hooks->on('alerts.tabs', function (array $tabs) use ($app): array {
 *       $tabs['twitch-follow'] = [
 *           'label'  => translate('twitch_alerts.follow'),
 *           'order'  => 10,
 *           'render' => static fn (): string => …,   // gibt HTML zurueck
 *       ];
 *       return $tabs;
 *   });
 *
 * "render" wird nur fuer den offenen Reiter aufgerufen - sonst wuerde
 * jeder Seitenaufruf alle Reiter bauen, von denen sieben unsichtbar
 * bleiben.
 */
final class Alerts
{
    /** Der Platz im Overlay, in dem Alerts erscheinen. */
    public const SLOT = 'alerts';

    /** Vorgaben, wenn nichts eingestellt ist. */
    public const DEFAULT_WIDTH = 800;
    public const DEFAULT_OFFSET_TOP = 120;
    public const DEFAULT_DURATION = 8;

    /**
     * Laengste Anzeigedauer. Ein Tippfehler in der Einstellung soll
     * nicht dazu fuehren, dass ein Alert eine Stunde stehen bleibt und
     * alle weiteren dahinter warten.
     */
    public const MAX_DURATION = 120;

    public static function scope(): string
    {
        return Settings::pluginScope('alerts');
    }

    /**
     * Einen Alert ins Overlay schicken.
     *
     * Der Text wird HIER aufgeloest, nicht im Browser: die Werte kommen
     * aus Twitch, also von fremd. Escaping an einer Stelle ist
     * ueberpruefbar - in jedem Plugin einzeln nicht.
     *
     * @param array{
     *     text?: string,
     *     values?: array<string, string|int|float>,
     *     video?: string,
     *     audio?: string,
     *     duration?: int|float,
     *     kind?: string
     * } $alert
     * @return bool Ob er abgeschickt wurde
     */
    public static function send(App $app, array $alert): bool
    {
        if (!self::enabled($app)) {
            return false;
        }

        $dauer = (float) ($alert['duration'] ?? 0);
        if ($dauer <= 0) {
            $dauer = (float) $app->settings->int('duration', self::DEFAULT_DURATION, self::scope());
        }
        $dauer = max(1.0, min((float) self::MAX_DURATION, $dauer));

        $nachricht = [
            'kind'     => (string) ($alert['kind'] ?? ''),
            'html'     => self::renderText(
                (string) ($alert['text'] ?? ''),
                (array) ($alert['values'] ?? [])
            ),
            'video'    => self::mediaUrl((string) ($alert['video'] ?? '')),
            'audio'    => self::mediaUrl((string) ($alert['audio'] ?? '')),
            'duration' => $dauer,
        ];

        // Ein Alert ohne alles wuerde als leerer Kasten aufblitzen.
        if ($nachricht['html'] === '' && $nachricht['video'] === '') {
            return false;
        }

        return (new Bus($app))->send(self::SLOT, $nachricht) > 0;
    }

    /**
     * Hauptschalter. Ein einzelner Alert-Typ hat seinen eigenen
     * Schalter; dieser hier gilt fuer alle.
     */
    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    /**
     * Platzhalter einsetzen und dabei escapen.
     *
     * Reihenfolge wie im Markdown-Uebersetzer: ZUERST wird der ganze
     * Text escaped, danach werden die Platzhalter durch fertiges HTML
     * ersetzt. Ein Twitch-Name mit "<script>" darin ist damit Text, und
     * ein Alert-Text aus der Einstellung kann kein HTML einschmuggeln.
     *
     * @param array<string, string|int|float> $values
     */
    public static function renderText(string $text, array $values): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        foreach ($values as $name => $wert) {
            $name = trim((string) $name);
            if ($name === '' || preg_match('/^[a-z_][a-z0-9_]*$/i', $name) !== 1) {
                continue;
            }

            $sicher = htmlspecialchars((string) $wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $ersatz = $sicher === ''
                ? ''
                : '<span class="alert-accent">' . $sicher . '</span>';

            // Beide Schreibweisen: mit und ohne Leerzeichen. Die
            // Legacy schrieb "{{ username }}", von Hand tippt man
            // schnell "{{username}}".
            $html = str_replace(
                ['{{ ' . $name . ' }}', '{{' . $name . '}}'],
                $ersatz,
                $html
            );
        }

        // Was uebrig bleibt, war ein Platzhalter ohne Wert. Weg damit -
        // "{{ tier }}" mitten im Stream sieht nach Fehler aus, ein
        // fehlendes Wort nach Absicht.
        $html = (string) preg_replace('/\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/', '', $html);

        return trim((string) preg_replace('/\s{2,}/', ' ', $html));
    }

    /**
     * Adresse einer Mediendatei.
     *
     * Erlaubt sind eigene Pfade und http(s). Alles andere - allen voran
     * "javascript:" und "data:" - wird verworfen: die Adresse landet in
     * einem src-Attribut auf einer Seite, die unbeaufsichtigt im Stream
     * laeuft.
     */
    public static function mediaUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        if (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
            return filter_var($url, FILTER_VALIDATE_URL) === false ? '' : $url;
        }

        return '';
    }

    /**
     * Die Reiter, die Plugins angemeldet haben - nach order sortiert.
     *
     * @return array<string, array{label: string, order: int, render: callable|null}>
     */
    public static function tabs(App $app): array
    {
        $tabs = $app->hooks->filter('alerts.tabs', []);
        if (!is_array($tabs)) {
            return [];
        }

        $sauber = [];

        foreach ($tabs as $key => $tab) {
            $key = strtolower(trim((string) $key));

            // Der Schluessel wird Teil einer Adresse.
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $key) !== 1 || !is_array($tab)) {
                continue;
            }

            $render = $tab['render'] ?? null;

            $sauber[$key] = [
                'label'  => trim((string) ($tab['label'] ?? $key)) ?: $key,
                'order'  => (int) ($tab['order'] ?? 100),
                'render' => is_callable($render) ? $render : null,
            ];
        }

        uasort(
            $sauber,
            static fn (array $a, array $b): int => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]
        );

        return $sauber;
    }

    /**
     * Breite der Alert-Flaeche in Pixeln.
     */
    public static function width(App $app): int
    {
        return max(160, min(3840, $app->settings->int('width', self::DEFAULT_WIDTH, self::scope())));
    }

    public static function offsetTop(App $app): int
    {
        return max(0, min(2160, $app->settings->int('offset_top', self::DEFAULT_OFFSET_TOP, self::scope())));
    }

    /**
     * Groesse fuer Video und Bild. Leer heisst: so gross wie die
     * Flaeche, Hoehe nach Seitenverhaeltnis.
     *
     * @return array{width: string, height: string}
     */
    public static function mediaSize(App $app): array
    {
        $scope = self::scope();

        $zahl = static function (int $wert): string {
            return $wert > 0 ? $wert . 'px' : '';
        };

        return [
            'width'  => $zahl($app->settings->int('media_width', 0, $scope)),
            'height' => $zahl($app->settings->int('media_height', 0, $scope)),
        ];
    }
}
