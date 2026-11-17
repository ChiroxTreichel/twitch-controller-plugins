<?php

declare(strict_types=1);

namespace TwitchController\Plugin\BlurChats;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Overlay\Bus;

/**
 * Was das Overlay zeigt: die Bereiche der laufenden Kategorie.
 *
 * Zwei Wege dorthin, mit demselben Inhalt:
 *
 *   state()  beim Laden der Browserquelle (state.js) - die Leitung
 *            spielt nichts nach, ohne das stuende nach einem Neuladen
 *            mitten im Stream der Ingame-Chat offen im Bild.
 *   push()   wenn sich etwas aendert: Kategorie, Bereiche, Schalter.
 *
 * Die Nachricht traegt immer die ganze Liste und nie eine Aenderung.
 * Eine verpasste Nachricht ist damit mit der naechsten erledigt.
 */
final class Overlay
{
    /**
     * Die laufende Kategorie, oder eine leere.
     *
     * Der Kern fragt Twitch nach, wenn er noch nichts weiss - das kann
     * scheitern. Hier wird es abgefangen: eine Browserquelle soll
     * wegen eines Twitch-Aussetzers nicht ohne Antwort dastehen.
     *
     * @return array{id: string, name: string}
     */
    public static function currentCategory(App $app): array
    {
        try {
            $jetzt = $app->twitch->category()->current();
        } catch (Throwable $e) {
            $app->log('Blur Chats: aktuelle Kategorie nicht lesbar: ' . $e->getMessage());

            return ['id' => '', 'name' => ''];
        }

        return [
            'id'   => (string) ($jetzt['id'] ?? ''),
            'name' => (string) ($jetzt['name'] ?? ''),
        ];
    }

    /**
     * Abgeschaltet oder ohne Kategorie (offline, noch nichts gesetzt):
     * keine Bereiche. Der Platz ist dann leer und durchsichtig.
     *
     * @return array{regions: list<array<string, mixed>>}
     */
    public static function state(App $app): array
    {
        if (!Regions::enabled($app)) {
            return ['regions' => []];
        }

        $kategorie = self::currentCategory($app)['id'];

        if ($kategorie === '') {
            return ['regions' => []];
        }

        // Ohne Namen: die stehen auf der Verwaltungsseite und haben im
        // Overlay nichts zu suchen.
        $bereiche = array_map(static function (array $b): array {
            unset($b['label']);

            return $b;
        }, Regions::forCategory($app, $kategorie));

        return ['regions' => $bereiche];
    }

    /** Den Stand an alle offenen Browserquellen schicken. */
    public static function push(App $app): void
    {
        try {
            (new Bus($app))->send(Regions::SLOT, self::state($app));
        } catch (Throwable $e) {
            // Gespeichert ist gespeichert. Die Quelle holt den Stand
            // spaetestens beim naechsten Laden ueber state.js.
            $app->log('Blur Chats: Overlay nicht erreicht: ' . $e->getMessage());
        }
    }
}
