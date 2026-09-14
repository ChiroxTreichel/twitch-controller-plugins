<?php

declare(strict_types=1);

namespace TwitchController\Plugin\StreamelementsTipGoals;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
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
    public const SLUG = 'streamelements-tip-goals';

    /** Die Tabelle. Je Plugin eine eigene - sie schliessen sich aus. */
    public const TABLE = 'se_tip_goals';

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
     * Das Geruest im Overlay.
     *
     * Wortgleich aus dem alten System uebernommen, damit der Balken
     * genauso aussieht: dieselben Klassen, dieselben Bindungen.
     */
    public static function html(): string
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

    public static function css(): string
    {
        return <<<'CSS'
.goal-tip .goal-fill { background: linear-gradient(90deg, #ffd34d, #ff9f1c); }
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
