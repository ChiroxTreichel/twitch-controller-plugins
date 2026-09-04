<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TwitchGoals;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Plugin\Goals\Goals;

/**
 * Einstellungen der Twitch-Ziele.
 *
 * Zwei Sorten:
 *
 *   die Titel      "Follower-Ziel", "Sub-Ziel" - frei, sie stehen im
 *                  Overlay
 *   das Aussehen   HTML und CSS. Im alten System war das eine Datei
 *                  im Projektordner; wer etwas aendern wollte, musste
 *                  an den Quellcode.
 *
 * Die Zahlen selbst stehen NICHT hier: sie kommen von Twitch und
 * liegen unter "state" - siehe Fetcher.
 */
final class Config
{
    public const SLUG = 'twitch-goals';

    /**
     * Die Bindungen, die im Geruest vorkommen MUESSEN.
     *
     * Fehlt eine, zeigt das Ziel im Overlay nichts an - und das faellt
     * erst mitten im Stream auf. Goals::missing() prueft dagegen, und
     * die Oberflaeche sagt beim Speichern, was fehlt.
     *
     * @var list<string>
     */
    public const REQUIRED_BINDINGS = [
        'follower_title',
        'follower_current',
        'follower_goal',
        'sub_title',
        'sub_current',
        'sub_goal',
    ];

    /**
     * Die Abschnitte, die sich einzeln abschalten lassen.
     *
     * data-goal="follower" markiert, was zum Follower-Ziel gehoert.
     * Ohne diese Markierung wuesste das Overlay nicht, was es
     * ausblenden soll - der Schalter waere dann ohne Wirkung, und das
     * merkt man erst im Stream.
     *
     * Darum steht sie in der Pflichtliste: lieber eine Meldung beim
     * Speichern als ein Schalter, der nichts tut.
     *
     * @var list<string>
     */
    public const REQUIRED_GOALS = ['follower', 'sub'];

    /**
     * Die Balken, die vorkommen muessen.
     *
     * data-fill="follower" rechnet mit follower_current und
     * follower_goal - ohne den Balken bleibt die Anzeige eine Zeile
     * Text ohne Fortschritt.
     *
     * @var list<string>
     */
    public const REQUIRED_FILLS = ['follower', 'sub'];

    /**
     * Die Pflichtelemente mit Klarnamen, fuer die Tabelle in der
     * Oberflaeche.
     *
     * Ausgeschrieben und nicht aus dem Namen zusammengesetzt: ein
     * Schluessel, der erst zur Laufzeit entsteht, ist fuer
     * bin/lang.php unsichtbar - er meldet ihn als fehlend und die
     * einzelnen als unbenutzt. Die Regel steht im Kopf jenes
     * Werkzeugs.
     *
     * @return array<string, string>
     */
    public static function bindingLabels(): array
    {
        return [
            'follower_title'   => translate('twitch_goals.bind.follower_title'),
            'follower_current' => translate('twitch_goals.bind.follower_current'),
            'follower_goal'    => translate('twitch_goals.bind.follower_goal'),
            'sub_title'        => translate('twitch_goals.bind.sub_title'),
            'sub_current'      => translate('twitch_goals.bind.sub_current'),
            'sub_goal'         => translate('twitch_goals.bind.sub_goal'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function goalLabels(): array
    {
        return [
            'follower' => translate('twitch_goals.goal.follower'),
            'sub'      => translate('twitch_goals.goal.sub'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fillLabels(): array
    {
        return [
            'follower' => translate('twitch_goals.fill.follower'),
            'sub'      => translate('twitch_goals.fill.sub'),
        ];
    }

    public const MAX_TITLE = 80;
    public const MAX_HTML = 20000;
    public const MAX_CSS = 20000;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Die zwei Ziele einzeln
    // -----------------------------------------------------------------

    /**
     * Die Arten, die dieses Plugin anzeigt.
     *
     * @var list<string>
     */
    public const KINDS = ['follower', 'sub'];

    /**
     * Ist dieses Ziel eingeschaltet?
     *
     * Voreinstellung an: wer das Plugin installiert, will beide Ziele.
     *
     * Ausgeschaltet heisst NICHT "nicht abrufen": helix/goals liefert
     * beide in einem Aufruf, und im Reiter soll die Zahl weiter
     * stehen. Es heisst nur, dass der Abschnitt im Overlay verborgen
     * wird - siehe assets/goals.js im Plugin Goals.
     */
    public static function goalEnabled(App $app, string $art): bool
    {
        if (!in_array($art, self::KINDS, true)) {
            return false;
        }

        return $app->settings->bool($art . '_enabled', true, self::scope());
    }

    public static function setGoalEnabled(App $app, string $art, bool $an): void
    {
        if (in_array($art, self::KINDS, true)) {
            $app->settings->set($art . '_enabled', $an, self::scope());
        }
    }

    /**
     * @return array<string, bool>
     */
    public static function switches(App $app): array
    {
        $schalter = [];
        foreach (self::KINDS as $art) {
            $schalter[$art] = self::goalEnabled($app, $art);
        }

        return $schalter;
    }

    // -----------------------------------------------------------------
    //  Titel
    // -----------------------------------------------------------------

    /**
     * @return array{follower_title: string, sub_title: string}
     */
    public static function titles(App $app): array
    {
        return [
            'follower_title' => self::title(
                $app->settings->string('follower_title', '', self::scope()),
                translate('twitch_goals.default.follower_title')
            ),
            'sub_title' => self::title(
                $app->settings->string('sub_title', '', self::scope()),
                translate('twitch_goals.default.sub_title')
            ),
        ];
    }

    private static function title(string $wert, string $vorgabe): string
    {
        $wert = trim($wert);

        return $wert === '' ? $vorgabe : self::cut($wert, self::MAX_TITLE);
    }

    // -----------------------------------------------------------------
    //  Aussehen
    // -----------------------------------------------------------------

    public static function html(App $app): string
    {
        $wert = trim($app->settings->string('html', '', self::scope()));

        return $wert === '' ? self::defaultHtml() : self::cut($wert, self::MAX_HTML);
    }

    public static function css(App $app): string
    {
        $wert = trim($app->settings->string('css', '', self::scope()));

        return $wert === '' ? self::defaultCss() : self::cut($wert, self::MAX_CSS);
    }

    /**
     * Steht in den Einstellungen ein eigenes Geruest, oder greift die
     * Vorgabe?
     */
    public static function isCustom(App $app): bool
    {
        return trim($app->settings->string('html', '', self::scope())) !== ''
            || trim($app->settings->string('css', '', self::scope())) !== '';
    }

    /**
     * Speichern - und melden, was am Geruest fehlt.
     *
     * Gespeichert wird trotzdem: ein halb fertiges Geruest soll man
     * stehen lassen und weiterschreiben koennen. Gemeldet wird es
     * sofort, denn ein fehlendes Element sieht man im Overlay nicht -
     * dort ist dann nur nichts.
     *
     * @return list<string> die fehlenden Bindungen
     */
    public static function save(App $app, string $html, string $css): array
    {
        $html = self::cut(trim($html), self::MAX_HTML);
        $css = self::cut(trim($css), self::MAX_CSS);

        $app->settings->setMany([
            'html'       => $html,
            'css'        => $css,
            // Der Stempel treibt die Adresse des Stylesheets. Ohne ihn
            // behaelt OBS nach einer Aenderung das alte CSS, bis die
            // Browserquelle neu angelegt wird.
            'updated_at' => time(),
        ], self::scope());

        return Goals::missing(
            $html === '' ? self::defaultHtml() : $html,
            self::REQUIRED_BINDINGS,
            self::REQUIRED_FILLS,
            self::REQUIRED_GOALS
        );
    }

    public static function stamp(App $app): int
    {
        return $app->settings->int('updated_at', 0, self::scope());
    }

    // -----------------------------------------------------------------
    //  Die Vorgabe: das Aussehen des alten Systems
    // -----------------------------------------------------------------

    /**
     * Wortgleich aus legacy/templates/goals.html, ohne den Tip-Balken.
     *
     * Der kommt mit dem Spenden-Plugin und haengt sich dann als
     * eigener Abschnitt in denselben Hook - deshalb steht er hier
     * nicht als Kommentar herum.
     */
    public static function defaultHtml(): string
    {
        return <<<'HTML'
<div class="goal-strip">
    <section class="goal goal-small" data-goal="follower">
        <div class="goal-bar bg-brown">
            <span class="goal-fill fg-follow" data-fill="follower"></span>
            <div class="goal-row small">
                <p class="goal-label"   data-bind="follower_title"></p>
                <p class="goal-current" data-bind="follower_current" data-format="int"></p>
                <p class="goal-amount"  data-bind="follower_goal"    data-format="int"></p>
            </div>
        </div>
    </section>

    <section class="goal goal-small" data-goal="sub">
        <div class="goal-bar bg-brown">
            <span class="goal-fill fg-sub" data-fill="sub"></span>
            <div class="goal-row small">
                <p class="goal-label"   data-bind="sub_title"></p>
                <p class="goal-current" data-bind="sub_current" data-format="int"></p>
                <p class="goal-amount"  data-bind="sub_goal"    data-format="int"></p>
            </div>
        </div>
    </section>
</div>
HTML;
    }

    /**
     * Wortgleich aus legacy/public/styles.css, auf die Ziele gekuerzt.
     *
     * Die Farben standen dort in :root. Hier sind sie auf den Platz
     * bezogen - im Overlay liegen mehrere Plugins nebeneinander, und
     * eine Farbe namens "--bg-brown" gehoert keinem von ihnen allein.
     */
    public static function defaultCss(): string
    {
        return <<<'CSS'
#ov-slot-goals {
    --white: #fff;
    --text-shadow: 3px 3px 3px rgba(0, 0, 0, .9);
    --bg-brown: #58453e;
    --fill-sub: #e867a0;
    --fill-follow: #aeee11;

    font-family: Nunito, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}

.goal-strip {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 60px;
}

.goal {
    position: relative;
    width: 100%;
}

.goal-small {
    flex: 0 1 230px;
    width: 230px;
    height: 13px;
}

.goal-bar {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 4px;
    overflow: hidden;
}

.bg-brown { background: var(--bg-brown); }

.goal-fill {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0%;
    transition: width .2s linear;
}

.fg-sub { background: var(--fill-sub); }
.fg-follow { background: var(--fill-follow); }

.goal-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 8px;
    color: var(--white);
    text-shadow: var(--text-shadow);
}

.goal-row p { margin: 0; padding: 0; font-size: 24px; }
.goal-row.small p { font-size: 14px; text-shadow: 1px 1px 2px black; }
.goal-small .goal-row p { margin-top: -2px; }

.goal-label { position: absolute; left: 5px; }
.goal-current { opacity: .95; width: 100%; text-align: center; }
.goal-amount { opacity: .95; text-align: right; position: absolute; right: 5px; }
CSS;
    }

    private static function cut(string $text, int $laenge): string
    {
        return function_exists('mb_substr')
            ? mb_substr($text, 0, $laenge)
            : substr($text, 0, $laenge);
    }
}
