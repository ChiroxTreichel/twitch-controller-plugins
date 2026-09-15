<?php

declare(strict_types=1);

namespace TwitchController\Plugin\PaypalTipGoals;

use TwitchController\Core\App;
use TwitchController\Core\Support\Markdown;

/**
 * ===================================================================
 *  Impressum, Datenschutz, AGB
 * ===================================================================
 *
 * Diese Texte liefert das Plugin NICHT mit, und das ist Absicht.
 *
 * In einem Impressum steht der Name und die Anschrift dessen, der die
 * Seite betreibt. Ein Plugin aus dem Katalog, das eines mitbraechte,
 * wuerde diese Angaben bei jedem verbreiten, der es installiert - und
 * der Naechste betriebe dann eine Spendenseite mit fremden Daten. Das
 * ist kein Geschmacksurteil, das geht nicht.
 *
 * Darum: leer geliefert, in den Einstellungen geschrieben. Und solange
 * das Impressum leer ist, geht die oeffentliche Seite nicht online -
 * siehe pageReady(). Eine Seite, die Geld annimmt, ohne zu sagen, wer
 * es bekommt, soll es hier nicht geben.
 *
 * Datenschutz und AGB sind nicht erzwungen: ob man sie braucht, haengt
 * davon ab, wie man die Seite betreibt. Fehlen sie, fehlt der Link im
 * Fuss - statt auf eine leere Seite zu fuehren.
 */
final class Legal
{
    /**
     * Die drei Texte. Der Schluessel steht in der Adresse.
     *
     * @var array<string, string> Schluessel => Sprachschluessel des Titels
     */
    public const PAGES = [
        'impressum'    => 'pp_tip.legal.imprint',
        'datenschutz'  => 'pp_tip.legal.privacy',
        'agb'          => 'pp_tip.legal.terms',
    ];

    /** Lang genug fuer ein Impressum, kurz genug fuer eine Einstellung. */
    public const MAX_LENGTH = 20000;

    /**
     * Der Titel eines Textes.
     *
     * Ausgeschrieben und nicht ueber PAGES zusammengebaut: der
     * Sprachpruefer liest nur literale Schluessel, und ein Titel, den
     * niemand uebersetzt hat, faellt sonst erst im Betrieb auf.
     */
    public static function title(string $schluessel): string
    {
        return match ($schluessel) {
            'impressum'   => translate('pp_tip.legal.imprint'),
            'datenschutz' => translate('pp_tip.legal.privacy'),
            'agb'         => translate('pp_tip.legal.terms'),
            default       => $schluessel,
        };
    }

    public static function isPage(string $schluessel): bool
    {
        return array_key_exists($schluessel, self::PAGES);
    }

    public static function text(App $app, string $schluessel): string
    {
        if (!self::isPage($schluessel)) {
            return '';
        }

        return trim($app->settings->string('legal_' . $schluessel, '', TipGoals::scope()));
    }

    public static function save(App $app, string $schluessel, string $text): void
    {
        if (!self::isPage($schluessel)) {
            return;
        }

        $text = trim($text);

        if (preg_match('/^.{0,' . self::MAX_LENGTH . '}/us', $text, $treffer) === 1) {
            $text = $treffer[0];
        }

        $app->settings->set('legal_' . $schluessel, $text, TipGoals::scope());
    }

    /**
     * Welche Texte da sind - fuer den Fuss der oeffentlichen Seite.
     *
     * Ein Link auf eine leere Seite ist schlechter als kein Link.
     *
     * @return list<string>
     */
    public static function available(App $app): array
    {
        $da = [];

        foreach (array_keys(self::PAGES) as $schluessel) {
            if (self::text($app, $schluessel) !== '') {
                $da[] = $schluessel;
            }
        }

        return $da;
    }

    /**
     * Darf die oeffentliche Seite ueberhaupt online?
     *
     * Nur mit Impressum. Der Rest der Einrichtung - PayPal, Ziele -
     * entscheidet darueber, ob man SPENDEN kann; hieran haengt, ob die
     * Seite ueberhaupt etwas anzeigt.
     */
    public static function pageReady(App $app): bool
    {
        return self::text($app, 'impressum') !== '';
    }

    /**
     * Muss der Spender AGB und Datenschutz zustimmen?
     *
     * Nur wenn es beide Texte wirklich gibt. Ein Haekchen mit einem
     * Link auf eine leere Seite verlangte eine Zustimmung zu nichts -
     * und ein Pflichtfeld, an dem der Spender haengenbliebe, ohne dass
     * er etwas dagegen tun koennte.
     */
    public static function termsRequired(App $app): bool
    {
        return self::text($app, 'agb') !== '' && self::text($app, 'datenschutz') !== '';
    }

    /**
     * Ein Text als HTML.
     *
     * Durch denselben Markdown-Wandler wie die Plugin-Beschreibungen:
     * eine Anschrift braucht Zeilenumbrueche, ein Datenschutztext
     * Ueberschriften und Listen - und roher HTML-Text in einer
     * Einstellung waere eine Einladung.
     */
    public static function html(App $app, string $schluessel): string
    {
        $text = self::text($app, $schluessel);

        return $text === '' ? '' : Markdown::render($text);
    }
}
