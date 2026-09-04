<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Goals;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Overlay\Bus;

/**
 * ===================================================================
 *  Goals - der Rahmen
 * ===================================================================
 *
 * Dieses Plugin zeigt selbst kein einziges Ziel an. Es stellt bereit,
 * was alle Ziel-Plugins gemeinsam brauchen:
 *
 *   die Flaeche     einen Platz im Overlay, mit Groesse und Lage
 *
 * Wichtig dabei: der Kern bleibt unangetastet. Ein Platz ist dort ein
 * leerer Kasten, und ueber den Hook overlay.assets darf ein Plugin
 * JEDE eigene Adresse als CSS oder JS anmelden - auch eine Route. Das
 * Geruest und das Stylesheet der Ziele stehen in den Einstellungen und
 * werden darum von zwei Routen erzeugt statt aus Dateien gelesen.
 *   die Reiter      Hook goals.tabs, wie bei den Alerts
 *   das Geruest     Hook goals.markup - jedes Plugin liefert HTML und
 *                   CSS, hier wird beides eingesammelt
 *   die Werte       Goals::send() schickt sie ins Overlay
 *   den Pruefer     required() sagt, welche Pflichtelemente fehlen
 *
 * Der Inhalt kommt von Twitch-Goals und spaeter vom Spenden-Plugin.
 * Ohne ein solches Plugin steht hier eine leere Flaeche und ein
 * Hinweis, wo man eines herbekommt.
 *
 * ------------------------------------------------------------------
 *  Warum HTML und CSS einstellbar sind
 * ------------------------------------------------------------------
 *
 * Ein Ziel im Overlay ist ein Balken mit drei Zahlen - und jeder will
 * ihn anders. Im alten System war das Aussehen eine Datei im
 * Projektordner: wer etwas aendern wollte, musste an den Quellcode.
 *
 * Deshalb steht das Geruest jetzt in den Einstellungen. Die Werte
 * kommen ueber data-Attribute hinein, genau wie im alten System:
 *
 *   data-bind="follower_current"   der Wert wird als Text eingesetzt
 *   data-format="int"              wie er geschrieben wird
 *   data-fill="follower"           Breite in Prozent des Ziels
 *   data-goal="follower"           gehoert zu diesem Ziel; wird
 *                                  verborgen, wenn es aus ist
 *
 * Damit man sich die Anzeige nicht versehentlich zerstoert, nennt
 * jedes Ziel-Plugin seine Pflichtelemente. Fehlt eines, sagt die
 * Oberflaeche das beim Speichern - siehe missing().
 */
final class Goals
{
    public const SLUG = 'goals';

    /** Der Platz im Overlay. */
    public const SLOT = 'goals';

    /** Wie in der Legacy: mittig oben, 1000 Pixel breit. */
    public const DEFAULT_WIDTH = 1000;
    public const DEFAULT_OFFSET_TOP = 20;

    public const MIN_WIDTH = 200;
    public const MAX_WIDTH = 1920;
    public const MAX_OFFSET_TOP = 1080;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    public static function enabled(App $app): bool
    {
        return $app->settings->bool('enabled', true, self::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set('enabled', $an, self::scope());
    }

    // -----------------------------------------------------------------
    //  Groesse und Lage
    // -----------------------------------------------------------------

    public static function width(App $app): int
    {
        return max(self::MIN_WIDTH, min(
            self::MAX_WIDTH,
            $app->settings->int('width', self::DEFAULT_WIDTH, self::scope())
        ));
    }

    public static function offsetTop(App $app): int
    {
        return max(0, min(
            self::MAX_OFFSET_TOP,
            $app->settings->int('offset_top', self::DEFAULT_OFFSET_TOP, self::scope())
        ));
    }

    // -----------------------------------------------------------------
    //  Die Reiter der Ziel-Plugins
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{label: string, order: int, render: callable|null}>
     */
    public static function tabs(App $app): array
    {
        $tabs = $app->hooks->filter('goals.tabs', []);
        if (!is_array($tabs)) {
            return [];
        }

        $sauber = [];
        foreach ($tabs as $key => $tab) {
            $key = strtolower(trim((string) $key));
            if ($key === '' || !is_array($tab)) {
                continue;
            }

            $sauber[$key] = [
                'label'  => trim((string) ($tab['label'] ?? $key)) ?: $key,
                'order'  => (int) ($tab['order'] ?? 50),
                'render' => is_callable($tab['render'] ?? null) ? $tab['render'] : null,
            ];
        }

        uasort($sauber, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $sauber;
    }

    // -----------------------------------------------------------------
    //  Das Geruest im Overlay
    // -----------------------------------------------------------------

    /**
     * HTML und CSS aller Ziel-Plugins, in einem Stueck.
     *
     * Jedes Plugin haengt sich an goals.markup und liefert seinen
     * Abschnitt. Die Reihenfolge entscheidet 'order' - beim alten
     * System stand der Follower-Balken links, der Sub-Balken rechts
     * und das Tip-Ziel darunter.
     *
     * @return array{html: string, css: string}
     */
    public static function markup(App $app): array
    {
        $teile = $app->hooks->filter('goals.markup', []);
        if (!is_array($teile)) {
            $teile = [];
        }

        $sauber = [];
        foreach ($teile as $key => $teil) {
            if (!is_array($teil)) {
                continue;
            }

            $sauber[(string) $key] = [
                'order' => (int) ($teil['order'] ?? 50),
                'html'  => (string) ($teil['html'] ?? ''),
                'css'   => (string) ($teil['css'] ?? ''),
            ];
        }

        uasort($sauber, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $html = [];
        $css = [];
        foreach ($sauber as $key => $teil) {
            if (trim($teil['html']) !== '') {
                $html[] = $teil['html'];
            }
            if (trim($teil['css']) !== '') {
                // Ein Kommentar davor: wer im Overlay den Quelltext
                // ansieht, soll erkennen, von welchem Plugin ein
                // Abschnitt kommt.
                $css[] = '/* ' . str_replace(['/*', '*/'], '', $key) . " */\n" . $teil['css'];
            }
        }

        return [
            'html' => self::sanitize(implode("\n", $html)),
            'css'  => implode("\n\n", $css),
        ];
    }

    /**
     * Das Geruest von allem befreien, was nicht zum Aussehen gehoert.
     *
     * Herausgeworfen werden script, iframe, object, embed, style und
     * Attribute wie onclick. Der Zweck der Einstellung ist das
     * Aussehen; wer Verhalten braucht, liefert eine JS-Datei ueber
     * overlay.assets mit, und das Stylesheet hat sein eigenes Feld.
     *
     * Das ist kein Schutz gegen den Betreiber - er kann ohnehin eigene
     * Dateien mitbringen -, sondern verhindert, dass eine unbedacht
     * kopierte Vorlage im Overlay Code ausfuehrt. Das Overlay laeuft
     * im Stream, und dort sieht niemand hin.
     */
    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Elemente samt Inhalt, dann die alleinstehenden Formen.
        $html = (string) preg_replace(
            '~<\s*(script|iframe|object|embed|style)\b[^>]*>.*?<\s*/\s*\1\s*>~is',
            '',
            $html
        );
        $html = (string) preg_replace(
            '~<\s*/?\s*(script|iframe|object|embed|style)\b[^>]*>~i',
            '',
            $html
        );

        // on…="…" in allen drei Schreibweisen der Anfuehrung.
        $html = (string) preg_replace(
            '~\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i',
            '',
            $html
        );

        // javascript: in href und src.
        $html = (string) preg_replace(
            '~\b(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2~i',
            '',
            $html
        );

        return trim($html);
    }

    /**
     * Der Zustand, den eine frisch verbundene Anzeige braucht.
     *
     * Die Leitung ins Overlay spielt NICHTS nach: sie beginnt bei der
     * hoechsten bekannten Nachrichtennummer, damit eine Browserquelle
     * nach einem Neustart nicht die Alerts der letzten Viertelstunde
     * nachholt. Fuer Alerts ist das richtig - fuer Ziele falsch: sie
     * stehen dauerhaft da, und ohne Anfangszustand zeigte das Overlay
     * nach jedem Laden Nullen und leere Titel, bis sich zufaellig ein
     * Wert aendert.
     *
     * Darum liefert jedes Ziel-Plugin hier seinen letzten bekannten
     * Stand, und der geht mit der Seite hinaus.
     *
     * @return array<string, mixed>
     */
    public static function state(App $app): array
    {
        $zustand = $app->hooks->filter('goals.state', []);

        return is_array($zustand) ? $zustand : [];
    }

    /**
     * Ein Zeitstempel, der sich bei jeder Aenderung aendert.
     *
     * Das Stylesheet der Ziele kommt aus den Einstellungen und ist
     * keine Datei - App::asset() kann ihm also keinen
     * Aenderungsstempel anhaengen. Ohne einen solchen Stempel zeigt
     * OBS nach einer Aenderung das alte CSS, bis die Quelle neu
     * angelegt wird.
     */
    public static function stamp(App $app): int
    {
        $stempel = $app->hooks->filter('goals.stamp', 0);

        return is_numeric($stempel) ? (int) $stempel : 0;
    }

    // -----------------------------------------------------------------
    //  Werte ins Overlay
    // -----------------------------------------------------------------

    /**
     * Werte an die Anzeige schicken.
     *
     * Geschickt wird ein Ausschnitt, kein vollstaendiger Zustand: das
     * JavaScript legt ihn ueber das, was es schon hat. So kann ein
     * Plugin nur "follower_current" erneuern, ohne die Werte der
     * anderen zu kennen oder zu ueberschreiben.
     *
     * @param array<string, mixed> $werte
     */
    public static function send(App $app, array $werte): bool
    {
        if ($werte === [] || !self::enabled($app)) {
            return false;
        }

        return (new Bus($app))->send(self::SLOT, $werte) > 0;
    }

    // -----------------------------------------------------------------
    //  Der Pruefer fuer die Pflichtelemente
    // -----------------------------------------------------------------

    /**
     * Welche der verlangten Bindungen fehlen im HTML?
     *
     * Geprueft wird auf data-bind="…" und data-fill="…". Ein Ziel ohne
     * seine Elemente zeigt nichts an, und der Fehler faellt erst im
     * Overlay auf - also mitten im Stream.
     *
     * @param list<string> $bindings Namen fuer data-bind
     * @param list<string> $fills    Namen fuer data-fill
     * @param list<string> $goals    Namen fuer data-goal
     * @return list<string> die fehlenden, als data-bind="x" geschrieben
     */
    public static function missing(
        string $html,
        array $bindings,
        array $fills = [],
        array $goals = []
    ): array {
        $fehlend = [];

        foreach ($bindings as $name) {
            if (!self::hasAttribute($html, 'data-bind', (string) $name)) {
                $fehlend[] = 'data-bind="' . $name . '"';
            }
        }

        foreach ($fills as $name) {
            if (!self::hasAttribute($html, 'data-fill', (string) $name)) {
                $fehlend[] = 'data-fill="' . $name . '"';
            }
        }

        foreach ($goals as $name) {
            if (!self::hasAttribute($html, 'data-goal', (string) $name)) {
                $fehlend[] = 'data-goal="' . $name . '"';
            }
        }

        return $fehlend;
    }

    /**
     * Steht dieses Attribut mit diesem Wert im HTML?
     *
     * Beide Anfuehrungszeichen und auch ohne - wer sein Geruest von
     * Hand tippt, schreibt nicht immer doppelte Anfuehrungszeichen,
     * und daran soll die Pruefung nicht scheitern.
     */
    private static function hasAttribute(string $html, string $attribut, string $wert): bool
    {
        $muster = '~\b' . preg_quote($attribut, '~') . '\s*=\s*'
            . '(?:"' . preg_quote($wert, '~') . '"'
            . "|'" . preg_quote($wert, '~') . "'"
            . '|' . preg_quote($wert, '~') . '(?=[\s/>]|$))~i';

        return preg_match($muster, $html) === 1;
    }
}
