<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Streaminfo;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * Titel und Kategorie des Kanals bei Twitch.
 *
 * Drei Wege zu Twitch:
 *
 *   info()       liest, was gerade eingestellt ist
 *   update()     schreibt Titel und/oder Kategorie
 *   search()     sucht Kategorien fuer die Vorschlagsliste
 *
 * Alles mit dem Token des Kanalinhabers. Ein App-Token duerfte lesen,
 * aber nicht schreiben, und zwei Wege fuer eine Seite waeren eine
 * Fehlerquelle ohne Gegenwert.
 */
final class Channel
{
    /**
     * Ohne diese Freigabe laesst sich nichts aendern.
     *
     * Lesen geht auch ohne - deshalb zeigt die Seite den aktuellen
     * Stand selbst dann, wenn das Speichern gesperrt ist. Ein leeres
     * Formular mit einer Warnung waere weniger wert als der Blick auf
     * das, was gerade laeuft.
     */
    public const SCOPE = 'channel:manage:broadcast';

    /** Wie viele Vorschlaege die Kategorie-Suche hoechstens liefert. */
    public const SUGGESTIONS = 10;

    public function __construct(private readonly App $app)
    {
    }

    public function broadcasterId(): string
    {
        return $this->app->settings->string('twitch_broadcaster_id');
    }

    /**
     * Darf geschrieben werden?
     *
     * Gefragt wird das gespeicherte Token, nicht Twitch - die Freigaben
     * stehen beim Token, und eine Seite soll fuer eine Ja/Nein-Frage
     * keinen Netzaufruf machen.
     */
    public function canManage(): bool
    {
        try {
            return $this->app->twitch->tokens()->missingScopes(
                TokenStore::BROADCASTER,
                [self::SCOPE]
            ) === [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Was gerade eingestellt ist.
     *
     * @return array{title: string, game_id: string, game_name: string, language: string}|null
     *         null, wenn Twitch nicht antwortet - siehe error()
     */
    public function info(): ?array
    {
        $kanalId = $this->broadcasterId();
        if ($kanalId === '') {
            $this->fehler = translate('streaminfo.error.no_channel');

            return null;
        }

        try {
            $antwort = $this->app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->get('channels', ['broadcaster_id' => $kanalId]);
        } catch (Throwable $e) {
            $this->fehler = $e->getMessage();

            return null;
        }

        if (!$antwort->ok()) {
            $this->fehler = $antwort->error();

            return null;
        }

        $zeile = $antwort->json['data'][0] ?? null;
        if (!is_array($zeile)) {
            // Antwort ohne Daten. Das passiert, wenn die Kanal-ID nicht
            // zum Token gehoert - und dann ist jede weitere Anzeige
            // geraten.
            $this->fehler = translate('streaminfo.error.no_data');

            return null;
        }

        return [
            'title'     => (string) ($zeile['title'] ?? ''),
            'game_id'   => (string) ($zeile['game_id'] ?? ''),
            'game_name' => (string) ($zeile['game_name'] ?? ''),
            'language'  => (string) ($zeile['broadcaster_language'] ?? ''),
        ];
    }

    /**
     * Titel und/oder Kategorie setzen.
     *
     * Nur was uebergeben wird, geht mit. Wer allein den Titel aendert,
     * soll nicht ungefragt die Kategorie mitschreiben - Twitch nimmt
     * ein Feld, das im Aufruf steht, als gewollt.
     *
     * @param array{title?: string, game_id?: string} $aenderungen
     * @return bool true bei Erfolg, sonst siehe error()
     */
    public function update(array $aenderungen): bool
    {
        if ($aenderungen === []) {
            $this->fehler = translate('streaminfo.error.nothing');

            return false;
        }

        $kanalId = $this->broadcasterId();
        if ($kanalId === '') {
            $this->fehler = translate('streaminfo.error.no_channel');

            return false;
        }

        try {
            $antwort = $this->app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->patch('channels', ['broadcaster_id' => $kanalId], $aenderungen);
        } catch (Throwable $e) {
            $this->fehler = $e->getMessage();

            return false;
        }

        if (!$antwort->ok()) {
            $this->fehler = $this->explain($antwort->status, $antwort->error());

            return false;
        }

        return true;
    }

    /**
     * Kategorien suchen - fuer die Vorschlagsliste unter dem Feld.
     *
     * @return list<array{id: string, name: string, box_art: string}>
     */
    public function search(string $frage): array
    {
        $frage = trim($frage);
        if ($frage === '') {
            return [];
        }

        try {
            $antwort = $this->app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->get('search/categories', [
                    'query' => $frage,
                    'first' => self::SUGGESTIONS,
                ]);
        } catch (Throwable $e) {
            $this->fehler = $e->getMessage();

            return [];
        }

        if (!$antwort->ok()) {
            $this->fehler = $antwort->error();

            return [];
        }

        $treffer = [];

        foreach (($antwort->json['data'] ?? []) as $zeile) {
            if (!is_array($zeile)) {
                continue;
            }

            $id = (string) ($zeile['id'] ?? '');
            $name = (string) ($zeile['name'] ?? '');

            if ($id === '' || $name === '') {
                continue;
            }

            $treffer[] = [
                'id'   => $id,
                'name' => $name,
                // Twitch liefert die Adresse mit Platzhaltern fuer die
                // Groesse. Eingesetzt wird hier und nicht im Browser:
                // die Groesse gehoert zur Darstellung, und die steht im
                // Stylesheet.
                'box_art' => str_replace(
                    ['{width}', '{height}'],
                    ['56', '76'],
                    (string) ($zeile['box_art_url'] ?? '')
                ),
            ];
        }

        return $treffer;
    }

    // -----------------------------------------------------------------
    //  Fehler
    // -----------------------------------------------------------------

    private string $fehler = '';

    public function error(): string
    {
        return $this->fehler;
    }

    /**
     * Twitchs knappe Ablehnungen in einen brauchbaren Satz.
     *
     * Die API antwortet auf einen abgelehnten Titel mit 400 und einem
     * englischen Halbsatz. Wer im Stream steht, kann damit nichts
     * anfangen.
     */
    private function explain(int $status, string $roh): string
    {
        return match ($status) {
            400 => translate('streaminfo.error.rejected', ['reason' => $roh]),
            401 => translate('streaminfo.error.unauthorized'),
            403 => translate('streaminfo.error.forbidden'),
            429 => translate('streaminfo.error.rate_limit'),
            default => $roh,
        };
    }
}
