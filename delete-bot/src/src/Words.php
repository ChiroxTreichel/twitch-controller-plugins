<?php

declare(strict_types=1);

namespace TwitchController\Plugin\DeleteBot;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Die Musterliste und die Pruefung einer Nachricht.
 *
 * Ein Muster ist ein regulaerer Ausdruck OHNE Begrenzer - gespeichert
 * wird "cutt\s*\.\s*ly", geprueft wird damit "/cutt\s*\.\s*ly/mi". So
 * war es im alten System, und so bleiben die vorhandenen Listen
 * gueltig.
 *
 * Wichtig an dieser Klasse: sie ist die EINZIGE Stelle, an der
 * entschieden wird, ob eine Nachricht faellt. Die Pruefung im Betrieb
 * und das Testfeld in der Oberflaeche rufen dieselbe Methode auf -
 * sonst wuerde das Testfeld etwas anderes sagen als der Bot tut, und
 * das waere schlimmer als gar kein Testfeld.
 */
final class Words
{
    public const SLUG = 'delete-bot';

    /** Mehr wird unuebersichtlich, und jede Nachricht prueft alle. */
    public const MAX_WORDS = 500;

    public const MAX_LENGTH = 200;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    /**
     * Voreinstellung AUS.
     *
     * Anders als bei den anderen Plugins: dieses hier loescht fremde
     * Nachrichten. Ein Werkzeug, das nach der Installation ungefragt
     * anfaengt zu moderieren, waere eine unangenehme Ueberraschung -
     * zumal die Liste dann noch leer ist und man erst beim ersten
     * Muster merkt, was passiert.
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
    //  Die Liste
    // -----------------------------------------------------------------

    /**
     * Alle Muster, so wie sie gespeichert sind.
     *
     * @return list<string>
     */
    public static function all(App $app): array
    {
        $gespeichert = $app->settings->get('words', null, self::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $muster = [];
        foreach ($gespeichert as $wort) {
            $text = trim((string) $wort);
            if ($text !== '') {
                $muster[] = self::cut($text);
            }
        }

        return $muster;
    }

    /**
     * Die Liste ersetzen.
     *
     * @param list<string>|string $eingabe Zeilen oder ein Textfeld
     */
    public static function save(App $app, array|string $eingabe): void
    {
        $app->settings->set('words', self::normalize($eingabe), self::scope());
    }

    /**
     * Aus einem Textfeld oder einer Liste eine saubere Liste.
     *
     * Doppelte fliegen raus: zweimal dasselbe Muster kostet bei jeder
     * Nachricht Rechenzeit und aendert nichts.
     *
     * @param list<string>|string $eingabe
     * @return list<string>
     */
    public static function normalize(array|string $eingabe): array
    {
        $zeilen = is_array($eingabe)
            ? $eingabe
            : (preg_split('/\r\n|\r|\n/', $eingabe) ?: []);

        $muster = [];
        $gesehen = [];

        // Leere Zeilen bleiben stehen - siehe all(). Doppelte fliegen
        // raus: zweimal dasselbe Muster kostet bei jeder Nachricht
        // Rechenzeit und aendert nichts.
        foreach ($zeilen as $zeile) {
            $text = self::cut(trim((string) $zeile));

            if ($text !== '') {
                if (isset($gesehen[$text])) {
                    continue;
                }

                $gesehen[$text] = true;
            }

            $muster[] = $text;

            if (count($muster) >= self::MAX_WORDS) {
                break;
            }
        }

        return $muster;
    }

    /**
     * Eine leere Zeile anhaengen - fuer den Knopf "Muster hinzufuegen".
     *
     * @param list<string> $muster
     * @return list<string>
     */
    public static function withEmptyRow(array $muster): array
    {
        if (count($muster) >= self::MAX_WORDS) {
            return $muster;
        }

        $muster[] = '';

        return $muster;
    }

    /**
     * Eine Zeile herausnehmen.
     *
     * @param list<string> $muster
     * @return list<string>
     */
    public static function without(array $muster, int $stelle): array
    {
        unset($muster[$stelle]);

        return array_values($muster);
    }

    /**
     * Taugt dieses Muster als regulaerer Ausdruck?
     *
     * Das alte System hat den Fehler verschluckt (@preg_match): ein
     * Tippfehler im Muster fiel damit nie auf, es passte einfach nie.
     * Wer eine Sperre einrichtet und glaubt, sie greife, ist schlechter
     * dran als wer weiss, dass sie fehlt.
     */
    public static function isValid(string $muster): bool
    {
        set_error_handler(static fn (): bool => true);
        $ergebnis = preg_match(self::wrap($muster), '');
        restore_error_handler();

        return $ergebnis !== false;
    }

    /**
     * Alle Muster, die nicht als regulaerer Ausdruck taugen.
     *
     * @param list<string> $muster
     * @return list<string>
     */
    public static function invalid(array $muster): array
    {
        return array_values(array_filter(
            $muster,
            static fn (string $eines): bool => !self::isValid($eines)
        ));
    }

    // -----------------------------------------------------------------
    //  Die Pruefung
    // -----------------------------------------------------------------

    /**
     * Wuerde diese Nachricht geloescht?
     *
     * Genau diese Methode benutzen der Betrieb und das Testfeld. Das
     * Ergebnis nennt auch das TREFFENDE Muster und den normalisierten
     * Text - ohne beides raet man beim Einrichten.
     *
     * @param list<string> $muster
     * @return array{blocked: bool, pattern: string, normalized: string, invalid: list<string>}
     */
    public static function check(string $nachricht, array $muster): array
    {
        $normalisiert = self::normalizeMessage($nachricht);
        $kaputt = [];

        foreach ($muster as $eines) {
            // Ein leeres Muster ergibt "//miu" - und das trifft JEDE
            // Nachricht. Gefiltert wird es schon in active(), aber hier
            // noch einmal: der Preis eines Fehlers waere, dass der Bot
            // den ganzen Chat leert.
            if (trim($eines) === '') {
                continue;
            }

            if (!self::isValid($eines)) {
                $kaputt[] = $eines;
                continue;
            }

            if (preg_match(self::wrap($eines), $normalisiert) === 1) {
                return [
                    'blocked'    => true,
                    'pattern'    => $eines,
                    'normalized' => $normalisiert,
                    'invalid'    => array_values(array_unique(array_merge($kaputt, self::invalid($muster)))),
                ];
            }
        }

        return [
            'blocked'    => false,
            'pattern'    => '',
            'normalized' => $normalisiert,
            'invalid'    => $kaputt,
        ];
    }

    /**
     * Akzente und Zeichen, mit denen ein Filter umgangen wird.
     *
     * Zuerst die kombinierenden Zeichen: "a" plus U+0308 sieht aus wie
     * "ä", ist aber etwas anderes. Danach die zusammengesetzten
     * Buchstaben auf ihre Grundform.
     *
     * Das flacht auch echte Umlaute ab - "schön" wird zu "schon". Das
     * ist Absicht: ein Muster soll beide Schreibweisen fangen. Im
     * Testfeld steht der normalisierte Text deshalb mit dabei, sonst
     * wundert man sich, warum ein Muster ohne Umlaut trifft.
     */
    public static function normalizeMessage(string $text): string
    {
        $text = preg_replace('/[\x{0300}-\x{036F}]/u', '', $text) ?? $text;

        return strtr($text, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        ]);
    }

    /**
     * Aus dem gespeicherten Muster der fertige Ausdruck.
     *
     * Zwei bewusste Abweichungen vom alten System:
     *
     * Der Schraegstrich wird maskiert. Vorher zerbrach ein Muster wie
     * "cutt.ly/abc" am Begrenzer - und weil der Fehler verschluckt
     * wurde, passte es einfach nie. Maskiert wird nur ein Schraegstrich
     * ohne Gegenschraegstrich davor: wer bereits "\/" geschrieben hat,
     * bekommt daraus sonst "\\/" und damit erst recht Bruch.
     *
     * Und der Schalter "u" kam dazu. Ohne ihn arbeitet der Ausdruck auf
     * Bytes, und ein "." trifft ein einzelnes Byte statt eines
     * Umlauts - bei deutschem Text ist das selten das, was gemeint war.
     *
     * "mi" ist wie vorher: mehrzeilig, ohne Ruecksicht auf Gross- und
     * Kleinschreibung.
     */
    private static function wrap(string $muster): string
    {
        $maskiert = preg_replace('~(?<!\\\\)/~', '\\/', $muster) ?? $muster;

        return '/' . $maskiert . '/miu';
    }

    private static function cut(string $text): string
    {
        return function_exists('mb_substr')
            ? mb_substr($text, 0, self::MAX_LENGTH)
            : substr($text, 0, self::MAX_LENGTH);
    }
}
