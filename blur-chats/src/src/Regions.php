<?php

declare(strict_types=1);

namespace TwitchController\Plugin\BlurChats;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Die Bereiche je Kategorie und der Schalter.
 *
 * Ein Bereich ist ein Rechteck in PIXELN der Buehne - so gross, wie
 * unter Konto > Overlay eingestellt (Vorgabe 1920x1080), gezaehlt von
 * oben links. Aendert sich die Buehne, bleiben die Zahlen stehen: der
 * Ingame-Chat sitzt nach dem Umstellen auch nicht woanders, nur die
 * Quelle in OBS muss wieder so gross sein wie die Buehne.
 *
 * Gespeichert wird je Kategorie als ganze Liste (save): der Editor
 * schickt immer alle Zeilen, und einzeln anzulegen, zu aendern und zu
 * loeschen haette nur mehr Wege, auf denen Seite und Tabelle
 * auseinanderlaufen.
 */
final class Regions
{
    public const SLUG = 'blur-chats';
    public const SLOT = 'blur-chats';

    /** Vorgaben fuer einen neuen Bereich. */
    public const COLOR = '#1e1e24';
    public const OPACITY = 90;
    public const FEATHER = 24;
    public const RADIUS = 12;
    public const WIDTH = 400;
    public const HEIGHT = 200;

    /** Kleiner waere im Editor nicht mehr zu greifen. */
    public const MIN_SIZE = 10;

    public const MAX_FEATHER = 200;
    public const MAX_RADIUS = 500;
    public const MAX_LABEL = 60;

    /** Mehr braucht kein Bild - und mehr wird der Editor unbedienbar. */
    public const MAX_REGIONS = 30;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Hauptschalter
    // -----------------------------------------------------------------

    /**
     * An ist die Vorgabe. Ohne Bereiche ist ohnehin nichts zu sehen -
     * und wer den ersten anlegt, soll ihn auch gleich im Overlay sehen,
     * statt einen Fehler zu suchen, der ein Schalter ist.
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
    //  Die Buehne
    // -----------------------------------------------------------------

    /**
     * Groesse der Buehne - mit denselben Grenzen wie Bus::layout(),
     * sonst rechnete der Editor auf einer anderen Flaeche als das
     * Overlay.
     *
     * @return array{width: int, height: int}
     */
    public static function stage(App $app): array
    {
        return [
            'width'  => max(320, min(7680, $app->settings->int('overlay_width', 1920))),
            'height' => max(180, min(4320, $app->settings->int('overlay_height', 1080))),
        ];
    }

    // -----------------------------------------------------------------
    //  Ein Bereich, aufgeraeumt
    // -----------------------------------------------------------------

    /**
     * Einen Bereich auf erlaubte Werte bringen. Ohne Datenbank, damit
     * es sich allein pruefen laesst.
     *
     * Die Lage wird auf die Buehne gezogen, und zwar so, dass ein
     * Bereich der Mindestgroesse noch daraufpasst. Erst danach die
     * Groesse: hoechstens bis zum Rand. Andersherum schoebe ein zu
     * breiter Bereich seine linke Kante nach links - man zieht ihn an
     * den Rand, und er wandert.
     *
     * Was fehlt oder keine Zahl ist, bekommt die Vorgabe. Ein neuer
     * Bereich ohne Lage steht mittig.
     *
     * @param array<string, mixed> $roh
     * @return array{label: string, x: int, y: int, w: int, h: int, color: string, opacity: int, feather: int, radius: int}
     */
    public static function normalize(array $roh, int $stageW, int $stageH): array
    {
        $stageW = max(self::MIN_SIZE, $stageW);
        $stageH = max(self::MIN_SIZE, $stageH);

        $w = self::number($roh['w'] ?? null, self::WIDTH);
        $h = self::number($roh['h'] ?? null, self::HEIGHT);

        $x = self::number($roh['x'] ?? null, intdiv($stageW - min($w, $stageW), 2));
        $y = self::number($roh['y'] ?? null, intdiv($stageH - min($h, $stageH), 2));

        $x = self::clamp($x, 0, $stageW - self::MIN_SIZE);
        $y = self::clamp($y, 0, $stageH - self::MIN_SIZE);
        $w = self::clamp($w, self::MIN_SIZE, $stageW - $x);
        $h = self::clamp($h, self::MIN_SIZE, $stageH - $y);

        $farbe = strtolower(trim((string) ($roh['color'] ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $farbe) !== 1) {
            $farbe = self::COLOR;
        }

        $label = trim(preg_replace('/\s+/u', ' ', (string) ($roh['label'] ?? '')) ?? '');

        return [
            'label'   => mb_substr($label, 0, self::MAX_LABEL),
            'x'       => $x,
            'y'       => $y,
            'w'       => $w,
            'h'       => $h,
            'color'   => $farbe,
            'opacity' => self::clamp(self::number($roh['opacity'] ?? null, self::OPACITY), 0, 100),
            'feather' => self::clamp(self::number($roh['feather'] ?? null, self::FEATHER), 0, self::MAX_FEATHER),
            'radius'  => self::clamp(self::number($roh['radius'] ?? null, self::RADIUS), 0, self::MAX_RADIUS),
        ];
    }

    // -----------------------------------------------------------------
    //  Lesen
    // -----------------------------------------------------------------

    /**
     * Die Bereiche einer Kategorie, in ihrer Reihenfolge.
     *
     * @return list<array{id: int, label: string, x: int, y: int, w: int, h: int, color: string, opacity: int, feather: int, radius: int}>
     */
    public static function forCategory(App $app, string $categoryId): array
    {
        if ($categoryId === '') {
            return [];
        }

        $zeilen = $app->db->all(
            'SELECT id, label, x, y, w, h, color, opacity, feather, radius
               FROM blur_chats_regions
              WHERE category_id = :id
              ORDER BY sort, id',
            ['id' => $categoryId]
        );

        return array_map(static fn (array $z): array => [
            'id'      => (int) $z['id'],
            'label'   => (string) $z['label'],
            'x'       => (int) $z['x'],
            'y'       => (int) $z['y'],
            'w'       => (int) $z['w'],
            'h'       => (int) $z['h'],
            'color'   => (string) $z['color'],
            'opacity' => (int) $z['opacity'],
            'feather' => (int) $z['feather'],
            'radius'  => (int) $z['radius'],
        ], $zeilen);
    }

    /**
     * Alle Kategorien, die etwas haben: Bereiche oder einen Screenshot.
     *
     * Auch die ohne Bereiche: wer alle loescht, um neu anzufangen, soll
     * sein Bild nicht suchen muessen. Der Name kommt aus den Bereichen,
     * sonst vom Screenshot - beide halten ihn, weil keiner allein immer
     * da ist.
     *
     * @return list<array{id: string, name: string, count: int}>
     */
    public static function categories(App $app): array
    {
        $zeilen = $app->db->all(
            'SELECT k.category_id AS id,
                    COALESCE(NULLIF(r.name, \'\'), NULLIF(s.category_name, \'\'), k.category_id) AS name,
                    COALESCE(r.anzahl, 0) AS count
               FROM (SELECT category_id FROM blur_chats_regions
                     UNION
                     SELECT category_id FROM blur_chats_screens) k
               LEFT JOIN (SELECT category_id, MAX(category_name) AS name, COUNT(*) AS anzahl
                            FROM blur_chats_regions
                           GROUP BY category_id) r ON r.category_id = k.category_id
               LEFT JOIN blur_chats_screens s ON s.category_id = k.category_id
              ORDER BY LOWER(COALESCE(NULLIF(r.name, \'\'), NULLIF(s.category_name, \'\'), k.category_id))'
        );

        return array_map(static fn (array $z): array => [
            'id'    => (string) $z['id'],
            'name'  => (string) $z['name'],
            'count' => (int) $z['count'],
        ], $zeilen);
    }

    // -----------------------------------------------------------------
    //  Schreiben
    // -----------------------------------------------------------------

    /**
     * Alle Bereiche einer Kategorie ersetzen.
     *
     * In einer Transaktion: dazwischen gaebe es einen Augenblick ohne
     * Bereiche, und genau dann koennte eine Browserquelle neu laden -
     * der Ingame-Chat stuende fuer diesen Stream offen im Bild.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function save(App $app, string $categoryId, string $categoryName, array $rows): void
    {
        if ($categoryId === '') {
            return;
        }

        $buehne = self::stage($app);
        $rows = array_slice(array_values($rows), 0, self::MAX_REGIONS);
        $categoryName = trim($categoryName);

        $app->db->transaction(static function () use ($app, $categoryId, $categoryName, $rows, $buehne): void {
            $app->db->run('DELETE FROM blur_chats_regions WHERE category_id = :id', ['id' => $categoryId]);

            foreach ($rows as $stelle => $roh) {
                $b = self::normalize(is_array($roh) ? $roh : [], $buehne['width'], $buehne['height']);

                $app->db->run(
                    'INSERT INTO blur_chats_regions
                        (category_id, category_name, label, x, y, w, h, color, opacity, feather, radius, sort)
                     VALUES (:cat, :name, :label, :x, :y, :w, :h, :color, :opacity, :feather, :radius, :sort)',
                    [
                        'cat'  => $categoryId,
                        'name' => $categoryName,
                        'sort' => $stelle,
                    ] + $b
                );
            }

            // Der Screenshot haelt den Namen mit - siehe categories().
            if ($categoryName !== '') {
                $app->db->run(
                    'UPDATE blur_chats_screens SET category_name = :name WHERE category_id = :id',
                    ['id' => $categoryId, 'name' => $categoryName]
                );
            }
        });
    }

    /**
     * Eine Kategorie aufnehmen: mit einem Bereich in der Mitte.
     *
     * Ohne Bereich stuende sie nirgends - die Liste kennt nur
     * Kategorien, die etwas haben. Und der erste Bereich ist ohnehin
     * das, was man als Naechstes anlegen wuerde.
     *
     * @return bool false, wenn es sie schon gab
     */
    public static function addCategory(App $app, string $categoryId, string $categoryName): bool
    {
        if ($categoryId === '') {
            return false;
        }

        foreach (self::categories($app) as $kategorie) {
            if ($kategorie['id'] === $categoryId) {
                return false;
            }
        }

        self::save($app, $categoryId, $categoryName, [[]]);

        return true;
    }

    /** Eine Kategorie mit allem, was dazugehoert - auch dem Screenshot. */
    public static function removeCategory(App $app, string $categoryId): void
    {
        $app->db->transaction(static function () use ($app, $categoryId): void {
            $app->db->run('DELETE FROM blur_chats_regions WHERE category_id = :id', ['id' => $categoryId]);
            $app->db->run('DELETE FROM blur_chats_screens WHERE category_id = :id', ['id' => $categoryId]);
        });
    }

    // -----------------------------------------------------------------
    //  Hilfsmittel
    // -----------------------------------------------------------------

    /**
     * Eine Zahl aus einem Formularfeld. Gerundet, nicht abgeschnitten:
     * 7,6 Pixel aus einer skalierten Vorschau sind 8.
     */
    private static function number(mixed $wert, int $vorgabe): int
    {
        if (is_int($wert)) {
            return $wert;
        }

        if (is_float($wert) || (is_string($wert) && is_numeric(trim($wert)))) {
            return (int) round((float) $wert);
        }

        return $vorgabe;
    }

    private static function clamp(int $wert, int $min, int $max): int
    {
        return max($min, min($max, $wert));
    }
}
