<?php

declare(strict_types=1);

namespace TwitchController\Plugin\StreamlabsTipGoals;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Overlay\Bus;
use TwitchController\Plugin\Goals\Goals;

/**
 * ===================================================================
 *  Tip-Goals
 * ===================================================================
 *
 * Eine Liste von Spendenzielen und ein Balken im Overlay. Aus dem
 * alten System uebernommen, mit einer Vereinfachung: Spenden gehen
 * IMMER auf das erste Ziel.
 *
 * Im alten System gab es dafuer einen eigenen "aktiv"-Zeiger, der
 * irgendwo in der Liste stehen konnte. Das war eine zweite Sache, die
 * man pflegen musste, und man sah ihr nicht an, warum ein Betrag beim
 * dritten Eintrag landete. Jetzt entscheidet die Reihenfolge: das
 * oberste Ziel ist das laufende. Ist es voll, schiebt man es nach
 * unten oder loescht es.
 *
 * Betraege stehen als NUMERIC in der Datenbank und nicht als
 * Fliesskomma. Bei Geld ist 0.1 + 0.2 kein akademisches Problem: der
 * Balken stuende irgendwann auf 49,999999 statt auf 50.
 *
 * Diese Klasse kennt die Quelle der Spenden NICHT. Was von
 * StreamElements oder Streamlabs kommt, holt Source ab und reicht hier
 * eine Zahl herein - so ist der Unterschied zwischen den beiden
 * Plugins auf eine Datei beschraenkt.
 */
final class TipGoals
{
    public const SLUG = 'streamlabs-tip-goals';

    /** Die Tabelle. Je Plugin eine eigene - sie schliessen sich aus. */
    public const TABLE = 'sl_tip_goals';

    /**
     * Wie oft nachgefragt wird.
     *
     * Fuenf Sekunden, wie gewuenscht. Wirksam wird das aber erst mit
     * einem Worker, der schnell genug tickt: er ruft cron.tick alle
     * WORKER_INTERVAL Sekunden auf, und das sind ohne Zutun 15. Wer
     * die fuenf wirklich will, setzt WORKER_INTERVAL=5 in der .env -
     * alle anderen Plugins bremsen sich ohnehin selbst.
     */
    public const POLL_SECONDS = 5;

    /** Mehr Ziele als das sind keine Liste mehr. */
    public const MAX_GOALS = 20;

    /** Laenger als das passt in keinen Balken. */
    public const MAX_TITLE = 80;

    /** Grenzen fuer das selbst geschriebene Aussehen. */
    public const MAX_HTML = 20000;
    public const MAX_CSS = 20000;

    /**
     * Was im Geruest vorkommen MUSS.
     *
     * Ohne diese Elemente zeigt der Balken im Overlay nichts an - und
     * das faellt erst mitten im Stream auf. Darum wird beim Speichern
     * gemeldet, was fehlt.
     *
     * @var list<string>
     */
    public const REQUIRED_BINDINGS = ['tip_title', 'tip_current', 'tip_goal'];

    /**
     * Der Balken, der vorkommen muss.
     *
     * data-fill="tip" rechnet aus tip_current und tip_goal die Breite.
     * Ohne ihn bleibt die Anzeige eine Zeile Text ohne Fortschritt.
     *
     * @var list<string>
     */
    public const REQUIRED_FILLS = ['tip'];

    /**
     * Wann sich Geruest oder Aussehen zuletzt geaendert haben.
     *
     * Der Wert steckt in der Adresse des Overlay-Stylesheets. Ohne
     * Aenderung behaelt OBS das alte - also MUSS er mitwachsen, wenn
     * html() oder css() angefasst werden. Genau das war beim ersten
     * Anlauf der Grund, warum eine Korrektur am Balken im Overlay nicht
     * ankam.
     *
     * Mit Uhrzeit, weil an einem Tag mehr als eine Aenderung passieren
     * kann - und lesbar als Datum, weil strtotime() daraus die Zahl
     * macht. Ein "2026-09-15 2" waere KEIN Datum mehr und ergaebe
     * stillschweigend 0; die Pruefsammlung nagelt das fest.
     */
    public const STAMP = '2026-09-15 14:00';

    /**
     * Der Abdruck von Geruest und Aussehen, zum Zeitpunkt des Stempels.
     *
     * Nur die Pruefsammlung liest das. Sie vergleicht ihn mit dem, was
     * defaultHtml() und defaultCss() JETZT ergeben - stimmt er nicht
     * mehr, wurde am Aussehen gearbeitet, ohne den Stempel
     * mitzuziehen. Dann behaelt OBS das alte Stylesheet, und die
     * Korrektur kommt bei niemandem an.
     *
     * Genau das ist hier passiert, und man merkt es nicht: der Code ist
     * richtig, das Paket ist richtig, und im Browser steht das Alte.
     */
    public const STAMP_FINGERPRINT = '90f53d2ec019468b';

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Die Liste
    // -----------------------------------------------------------------

    /**
     * Alle Ziele, das laufende zuerst.
     *
     * @return list<array{id: int, position: int, title: string, current: float, target: float, percent: float}>
     */
    public static function all(App $app): array
    {
        $rows = $app->db->all(
            'SELECT id, position, title, current, target
               FROM ' . self::TABLE . '
              ORDER BY position, id'
        );

        return array_map(static function (array $row): array {
            $current = (float) $row['current'];
            $target = (float) $row['target'];

            return [
                'id'       => (int) $row['id'],
                'position' => (int) $row['position'],
                'title'    => (string) $row['title'],
                'current'  => $current,
                'target'   => $target,
                'percent'  => self::percent($current, $target),
            ];
        }, $rows);
    }

    /**
     * Das laufende Ziel - das oberste der Liste.
     *
     * @return array{id: int, position: int, title: string, current: float, target: float, percent: float}|null
     */
    public static function first(App $app): ?array
    {
        return self::all($app)[0] ?? null;
    }

    public static function count(App $app): int
    {
        return (int) $app->db->value('SELECT count(*) FROM ' . self::TABLE);
    }

    /**
     * Ein leeres Ziel anhaengen.
     *
     * Ohne Namen und ohne Betrag: ausgefuellt wird es im Formular, und
     * ein Ziel, das beim Anlegen schon nach etwas aussieht, verleitet
     * dazu, es so stehen zu lassen.
     */
    public static function add(App $app): bool
    {
        if (self::count($app) >= self::MAX_GOALS) {
            return false;
        }

        $app->db->run(
            'INSERT INTO ' . self::TABLE . ' (position, title, current, target)
                  VALUES (COALESCE((SELECT max(position) FROM ' . self::TABLE . '), 0) + 1, \'\', 0, 0)'
        );

        return true;
    }

    public static function remove(App $app, int $id): void
    {
        $app->db->run('DELETE FROM ' . self::TABLE . ' WHERE id = :id', ['id' => (string) $id]);
    }

    /**
     * Ein Ziel um einen Platz verschieben.
     *
     * Getauscht werden die Plaetze zweier Zeilen, nicht die Inhalte:
     * so behaelt jedes Ziel seine id, und ein gleichzeitig laufender
     * Spendeneingang trifft weiter dasselbe Ziel.
     */
    public static function move(App $app, int $id, int $richtung): void
    {
        $liste = self::all($app);

        $stelle = null;
        foreach ($liste as $i => $ziel) {
            if ($ziel['id'] === $id) {
                $stelle = $i;
                break;
            }
        }

        if ($stelle === null) {
            return;
        }

        $ziel = $stelle + ($richtung < 0 ? -1 : 1);
        if (!isset($liste[$ziel])) {
            return;
        }

        $app->db->transaction(static function () use ($app, $liste, $stelle, $ziel): void {
            // Ueber einen freien Platz, damit der eindeutige Index -
            // falls einer dazukommt - nie zwei gleiche Plaetze sieht.
            $app->db->run(
                'UPDATE ' . self::TABLE . ' SET position = -1 WHERE id = :id',
                ['id' => (string) $liste[$stelle]['id']]
            );
            $app->db->run(
                'UPDATE ' . self::TABLE . ' SET position = :p WHERE id = :id',
                ['p' => (string) $liste[$stelle]['position'], 'id' => (string) $liste[$ziel]['id']]
            );
            $app->db->run(
                'UPDATE ' . self::TABLE . ' SET position = :p WHERE id = :id',
                ['p' => (string) $liste[$ziel]['position'], 'id' => (string) $liste[$stelle]['id']]
            );
        });
    }

    /**
     * Die Liste speichern.
     *
     * Nur Name und Betraege - die Reihenfolge haengt an eigenen
     * Knoepfen. Ein Formular, das beides gleichzeitig kann, muesste
     * raten, was zuerst gilt.
     *
     * @param array<int|string, array<string, mixed>> $eingaben id => Felder
     */
    public static function save(App $app, array $eingaben): void
    {
        foreach ($eingaben as $id => $felder) {
            $id = (int) $id;
            if ($id <= 0 || !is_array($felder)) {
                continue;
            }

            $app->db->run(
                'UPDATE ' . self::TABLE . '
                    SET title = :titel, current = :aktuell, target = :ziel
                  WHERE id = :id',
                [
                    'titel'   => self::normalizeTitle((string) ($felder['title'] ?? '')),
                    'aktuell' => self::money($felder['current'] ?? 0),
                    'ziel'    => self::money($felder['target'] ?? 0),
                    'id'      => (string) $id,
                ]
            );
        }
    }

    // -----------------------------------------------------------------
    //  Eine Spende verbuchen
    // -----------------------------------------------------------------

    /**
     * Einen Betrag auf das laufende Ziel schreiben.
     *
     * In EINER Abfrage, ohne vorher zu lesen: zwischen Lesen und
     * Schreiben koennte eine zweite Spende liegen, und die waere dann
     * weg. Geld darf nicht an einer Reihenfolge haengen.
     *
     * Gibt es kein Ziel, passiert nichts - und das ist richtig: die
     * Spende ist trotzdem angekommen, sie zaehlt nur in keinen Balken.
     * Eine Zeile im Log sagt es.
     */
    public static function applyDonation(App $app, float $betrag): bool
    {
        if ($betrag <= 0) {
            return false;
        }

        $getroffen = $app->db->run(
            'UPDATE ' . self::TABLE . '
                SET current = current + CAST(:betrag AS NUMERIC)
              WHERE id = (SELECT id FROM ' . self::TABLE . ' ORDER BY position, id LIMIT 1)',
            ['betrag' => self::money($betrag)]
        )->rowCount();

        if ($getroffen === 0) {
            $app->log(self::SLUG . ': Spende ueber ' . self::money($betrag) . ' kam an, aber es gibt kein Ziel.');

            return false;
        }

        self::push($app);

        return true;
    }

    // -----------------------------------------------------------------
    //  Ins Overlay
    // -----------------------------------------------------------------

    /**
     * Der aktuelle Stand als Wertesatz.
     *
     * @return array<string, string|float>
     */
    public static function values(App $app): array
    {
        $ziel = self::first($app);

        if ($ziel === null) {
            return ['tip_title' => '', 'tip_current' => 0.0, 'tip_goal' => 0.0];
        }

        return [
            'tip_title'   => $ziel['title'],
            'tip_current' => $ziel['current'],
            'tip_goal'    => $ziel['target'],
        ];
    }

    /** Den Stand ins Overlay schicken. */
    public static function push(App $app): void
    {
        Goals::send($app, self::values($app));
    }

    /**
     * Das Geruest im Overlay - eigenes, sonst die Vorgabe.
     */
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

    /** Steht in den Einstellungen ein eigenes Aussehen? */
    public static function isCustom(App $app): bool
    {
        return trim($app->settings->string('html', '', self::scope())) !== ''
            || trim($app->settings->string('css', '', self::scope())) !== '';
    }

    /**
     * Aussehen speichern - und melden, was am Geruest fehlt.
     *
     * Gespeichert wird TROTZDEM: ein halb fertiges Geruest soll man
     * stehen lassen und weiterschreiben koennen. Gemeldet wird es
     * sofort, denn ein fehlendes Element sieht man im Overlay nicht -
     * dort ist dann nur nichts.
     *
     * @return list<string> die fehlenden Bindungen
     */
    public static function saveAppearance(App $app, string $html, string $css): array
    {
        $html = self::cut(trim($html), self::MAX_HTML);
        $css = self::cut(trim($css), self::MAX_CSS);

        $app->settings->setMany([
            'html'       => $html,
            'css'        => $css,
            // Treibt die Adresse des Stylesheets - ohne den Stempel
            // behaelt OBS das alte.
            'updated_at' => time(),
        ], self::scope());

        // Und die laufende Browserquelle haelt ihre alte Adresse fest.
        (new Bus($app))->invalidate();

        return Goals::missing(
            $html === '' ? self::defaultHtml() : $html,
            self::REQUIRED_BINDINGS,
            self::REQUIRED_FILLS
        );
    }

    /** Zurueck auf die Vorgabe. */
    public static function resetAppearance(App $app): void
    {
        $app->settings->setMany([
            'html'       => '',
            'css'        => '',
            'updated_at' => time(),
        ], self::scope());

        (new Bus($app))->invalidate();
    }

    /**
     * Der Stempel fuer die Adresse des Stylesheets.
     *
     * Das Groessere von beidem: der Zeitpunkt der letzten eigenen
     * Aenderung und der Stand der mitgelieferten Vorgabe. Ohne den
     * zweiten Teil kaeme eine Korrektur an der VORGABE bei niemandem
     * an, der nie etwas gespeichert hat - und genau das ist hier schon
     * einmal passiert.
     */
    public static function stamp(App $app): int
    {
        return max(
            $app->settings->int('updated_at', 0, self::scope()),
            (int) strtotime(self::STAMP)
        );
    }

    /**
     * Zeichenweise kuerzen, nicht byteweise.
     *
     * substr() schnitte mitten in ein mehrbyte-Zeichen und
     * hinterliesse ein kaputtes; mb_substr() gibt es nicht ueberall.
     */
    private static function cut(string $text, int $laenge): string
    {
        return preg_match('/^.{0,' . $laenge . '}/us', $text, $treffer) === 1 ? $treffer[0] : '';
    }

    /**
     * Das mitgelieferte Geruest.
     *
     * Wortgleich aus dem alten System uebernommen, damit der Balken
     * genauso aussieht: dieselben Klassen, dieselben Bindungen.
     */
    public static function defaultHtml(): string
    {
        return <<<'HTML'
<section class="goal goal-tip">
  <div class="goal-bar bg-primary">
    <span class="goal-fill fg-tip" data-fill="tip"></span>
    <div class="goal-row">
      <p class="goal-label"   data-bind="tip_title"></p>
      <p class="goal-current" data-bind="tip_current" data-format="euro"></p>
      <p class="goal-amount"  data-bind="tip_goal"    data-format="euro"></p>
    </div>
  </div>
</section>
HTML;
    }

    /**
     * Das Aussehen - VOLLSTAENDIG und auf .goal-tip beschraenkt.
     *
     * Beides ist noetig, und beides war beim ersten Versuch falsch:
     *
     * Vollstaendig, weil dieses Plugin nur Goals voraussetzt und nicht
     * Goals - Twitch. Die Klassen .goal, .goal-bar, .goal-row und so
     * weiter kommen aus DESSEN Stylesheet; ohne es haette der Balken
     * gar kein Aussehen. Der erste Versuch setzte nur die Farbe der
     * Fuellung und verliess sich auf den Rest - und selbst MIT
     * Goals - Twitch blieb der Balken unsichtbar, weil .goal dort keine
     * Hoehe hat: die holen sich die kleinen Balken aus .goal-small, und
     * .goal-tip gab es nicht.
     *
     * Beschraenkt auf .goal-tip, weil sonst zwei Plugins dieselben
     * Klassen beschreiben und das letzte gewinnt - je nach
     * Ladereihenfolge saehen die Twitch-Ziele dann anders aus.
     *
     * Die Werte sind die des alten Systems, Farbe fuer Farbe.
     */
    public static function defaultCss(): string
    {
        return <<<'CSS'
/* Beide Klassen im Selektor: das Geruest traegt "goal goal-tip", und
   so ist jede benutzte Klasse auch wirklich beschrieben - ohne eine
   unbeschraenkte Regel, die den Twitch-Zielen dazwischenfunkt. */
.goal.goal-tip {
    position: relative;
    width: 100%;

    /* Die Hoehe des grossen Balkens - im alten System 30 Pixel. Ohne
       sie faellt der ganze Balken auf null zusammen: .goal-bar ist
       height: 100%, und 100% von nichts ist nichts. */
    height: 30px;
}

.goal-tip .goal-bar {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 4px;
    overflow: hidden;
}

/* Untergrund und Fuellung als eigene Klassen, wie im alten System -
   so passt ein von dort kopiertes Geruest ohne Aenderung. */
.goal-tip .bg-primary { background: #7384e5; }
.goal-tip .fg-tip     { background: #b21edb; }

.goal-tip .goal-fill {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0%;

    transition: width .2s linear;
}

.goal-tip .goal-row {
    position: relative;
    z-index: 1;

    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 100%;
    padding: 0 8px;

    color: #fff;
    font-family: Nunito, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    text-shadow: 3px 3px 3px rgba(0, 0, 0, .9);
}

.goal-tip .goal-row p {
    margin: 0;
    padding: 0;
    font-size: 24px;

    /* Das ist der Punkt, an dem der Text im Balken sitzt oder nicht.
     *
     * Ohne Angabe rechnet der Browser die Zeilenhoehe aus der Schrift:
     * bei 24 Pixeln sind das rund 33 - MEHR als die 30 des Balkens.
     * Der Text ragt dann oben und unten heraus, und .goal-bar schneidet
     * ihn mit seinem overflow: hidden ab.
     *
     * Das alte System half sich mit margin-top: -4px, also einem
     * Schubs. Ein Schubs verschiebt aber nur, was zu gross ist; hier
     * wird es passend gemacht, und dann zentriert das flex von
     * .goal-row von selbst.
     *
     * (Mein erster Versuch schrieb den Schubs als "margin: 0 0 -4px" -
     * das ist margin-BOTTOM und damit die Gegenrichtung. Der Text
     * rutschte nach unten aus dem Balken.)
     */
    line-height: 1;
}

.goal-tip .goal-label   { position: absolute; left: 5px; }
.goal-tip .goal-current { opacity: .95; width: 100%; text-align: center; }
.goal-tip .goal-amount  { opacity: .95; text-align: right; position: absolute; right: 5px; }
CSS;
    }

    // -----------------------------------------------------------------
    //  Kleinigkeiten
    // -----------------------------------------------------------------

    public static function percent(float $current, float $target): float
    {
        if ($target <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, ($current / $target) * 100.0));
    }

    /**
     * Ein Betrag als Zeichenkette mit zwei Nachkommastellen.
     *
     * Als Zeichenkette, weil er so in die NUMERIC-Spalte geht: ein
     * Fliesskomma-Wert durchliefe unterwegs eine Rundung, und bei Geld
     * sieht man die.
     *
     * Komma statt Punkt wird angenommen - jemand tippt "12,50" in ein
     * Feld, und daran soll es nicht scheitern.
     *
     * Die Regel dafuer ist: DER LETZTE Trenner ist der Dezimalpunkt,
     * alles davor ist Tausendergruppe. Damit stimmen alle vier
     * Schreibweisen, die hier ankommen koennen:
     *
     *   12.50      aus dem Zahlenfeld und von den Spenden-APIs
     *   12,50      von Hand, deutsch
     *   1.234,50   von Hand, deutsch mit Gruppe
     *   1,234.50   von Hand, englisch mit Gruppe
     *
     * Der erste Versuch hier nahm einen einzelnen Punkt als Gruppe -
     * aus 12.50 wurden 1250, und das faellt erst auf, wenn ein Ziel
     * hundertmal zu hoch steht.
     */
    public static function money(mixed $wert): string
    {
        if (is_string($wert)) {
            $wert = str_replace([' ', "\u{00a0}", "'"], '', $wert);

            $letzter = max(strrpos($wert, ',') ?: -1, strrpos($wert, '.') ?: -1);

            if ($letzter >= 0) {
                $vorn = str_replace([',', '.'], '', substr($wert, 0, $letzter));
                $wert = $vorn . '.' . substr($wert, $letzter + 1);
            }
        }

        $zahl = is_numeric($wert) ? (float) $wert : 0.0;

        return number_format(max(0.0, $zahl), 2, '.', '');
    }

    public static function normalizeTitle(string $titel): string
    {
        $titel = trim((string) preg_replace('/\s+/u', ' ', $titel));

        return preg_match('/^.{0,' . self::MAX_TITLE . '}/us', $titel, $treffer) === 1 ? $treffer[0] : '';
    }
}
