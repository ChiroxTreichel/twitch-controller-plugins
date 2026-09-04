<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Streaminfo;

use TwitchController\Core\App;
use TwitchController\Core\Http\Request;

/**
 * ===================================================================
 *  Die Einhaengepunkte der Streaminfo-Seite
 * ===================================================================
 *
 * Streaminfo selbst kann Titel und Kategorie. Was daran haengt -
 * gespeicherte Titel zur Auswahl, Tags vor dem Titel - sind eigene
 * Plugins, und die brauchen drei Dinge:
 *
 *   fields()    Platz auf der Seite, ueber dem Titelfeld
 *   bare()      den Titel OHNE das, was sie ihm vorangestellt haben
 *   compose()   den Titel MIT dem, was sie ihm voranstellen wollen
 *
 * bare() und compose() muessen zueinander passen, und das ist der
 * empfindliche Teil: bliebe beim Lesen ein Vorsatz stehen, wuerde ihn
 * das naechste Speichern ein zweites Mal davorsetzen. Aus
 * "[VTuber] Titel" wuerde "[VTuber] [VTuber] Titel", und beim naechsten
 * Mal wieder eins mehr.
 *
 * Darum die Regel fuer jedes Plugin, das hier mitspielt: was compose()
 * anbaut, muss bare() abbauen - und zwar nur das Eigene. Was ein
 * Betreiber selbst in eckige Klammern geschrieben hat, gehoert zum
 * Titel und bleibt.
 */
final class Streaminfo
{
    /**
     * Die Bloecke, die andere Plugins ueber dem Titelfeld anzeigen.
     *
     * Geordnet wie bei den Zielen: jeder Beitrag nennt seinen Platz in
     * der Reihe, damit die Seite nicht von der Ladereihenfolge der
     * Plugins abhaengt.
     *
     * Der Inhalt wird NICHT geputzt. Er kommt aus Plugin-Code, nicht
     * aus einem Eingabefeld - anders als beim Aussehen der Ziele, das
     * der Betreiber selbst tippt. Ein Plugin, das hier Unfug einsetzt,
     * koennte ohnehin alles andere auch.
     *
     * @param array{title: string, bare: string, canEdit: bool} $kontext
     * @return list<string> fertige HTML-Bloecke, in ihrer Reihenfolge
     */
    public static function fields(App $app, array $kontext): array
    {
        $beitraege = $app->hooks->filter('streaminfo.fields', [], $kontext);
        if (!is_array($beitraege)) {
            return [];
        }

        $sauber = [];

        foreach ($beitraege as $slug => $beitrag) {
            if (!is_array($beitrag)) {
                continue;
            }

            $html = (string) ($beitrag['html'] ?? '');
            if (trim($html) === '') {
                continue;
            }

            $sauber[] = [
                'order' => (int) ($beitrag['order'] ?? 100),
                'slug'  => (string) $slug,
                'html'  => $html,
            ];
        }

        // Bei gleichem Platz entscheidet der Slug und nicht der Zufall:
        // zwei Plugins, die sich beide auf 10 stellen, sollen die Seite
        // nicht bei jedem Aufruf anders anordnen.
        usort(
            $sauber,
            static fn (array $a, array $b): int => [$a['order'], $a['slug']] <=> [$b['order'], $b['slug']]
        );

        return array_map(static fn (array $b): string => $b['html'], $sauber);
    }

    /**
     * Der Titel, wie er ins Textfeld gehoert.
     *
     * Twitch liefert den ganzen Titel, also samt allem, was Plugins ihm
     * vorangestellt haben. Im Textfeld soll aber nur der Teil stehen,
     * den man dort auch bearbeitet - sonst editiert man Vorsaetze von
     * Hand, und das naechste Speichern setzt sie ein zweites Mal davor.
     */
    public static function bare(App $app, string $voll): string
    {
        $blank = $app->hooks->filter('streaminfo.title_bare', $voll);

        return is_string($blank) ? $blank : $voll;
    }

    /**
     * Der Titel, wie er zu Twitch geht.
     *
     * Der Request geht mit, damit ein Plugin die eigenen Felder aus dem
     * Formular lesen kann - welche Tags angehakt sind, zum Beispiel.
     */
    public static function compose(App $app, string $blank, Request $request): string
    {
        $voll = $app->hooks->filter('streaminfo.title_compose', $blank, $request);

        return is_string($voll) ? $voll : $blank;
    }
}
