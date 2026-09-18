<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

use TwitchController\Core\App;

/**
 * Der Abgleich mit Twitch.
 *
 * Drei Wege:
 *
 *   import()  holt, was im Kanal existiert - der Knopf
 *             "Kanalpunktbelohnungen laden"
 *   push()    legt eine hier vorhandene, aber bei Twitch fehlende
 *             Belohnung an
 *   adopt()   macht eine fremde Belohnung zu einer eigenen, indem sie
 *             geloescht und neu angelegt wird
 *
 * adopt() ist der einzige Weg, eine im Creator-Dashboard angelegte
 * Belohnung jemals schalten zu koennen - und er kostet ihr Symbol und
 * ihre Einloese-Historie. Twitch bietet nichts Sanfteres an: die
 * Client-ID des Erstellers laesst sich nicht nachtraeglich aendern.
 */
final class Sync
{
    private string $fehler = '';

    public function __construct(private readonly App $app)
    {
    }

    public function error(): string
    {
        return $this->fehler;
    }

    // -----------------------------------------------------------------
    //  Laden
    // -----------------------------------------------------------------

    /**
     * Alles holen, was der Kanal an Belohnungen hat.
     *
     * Was hier schon steht, behaelt seine Bedingungen - die kennt
     * Twitch nicht, und ein Laden, das sie wegwirft, waere eine Falle.
     *
     * Was bei Twitch nicht mehr existiert, faellt raus. Was hier nur
     * lokal steht (noch nie angelegt oder beim Uebernehmen
     * steckengeblieben), bleibt: das ist Arbeit, die sonst verloren
     * ginge.
     *
     * @return array{added: int, updated: int, removed: int}|null
     */
    public function import(): ?array
    {
        $api = new RewardApi($this->app);

        $vonTwitch = $api->list();

        if ($vonTwitch === null) {
            $this->fehler = $api->error();

            return null;
        }

        $bisher = [];
        $nurLokal = [];

        foreach (Rewards::all($this->app) as $eintrag) {
            $id = (string) $eintrag['id'];

            if (Rewards::isRemote($id)) {
                $bisher[$id] = $eintrag;
            } else {
                $nurLokal[] = $eintrag;
            }
        }

        $neu = [];
        $dazu = 0;
        $geaendert = 0;

        foreach ($vonTwitch as $zeile) {
            $id = (string) ($zeile['raw']['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $sauber = Rewards::fromTwitch(
                $zeile['raw'],
                $bisher[$id] ?? [],
                (bool) $zeile['manageable']
            );

            if ($sauber === null) {
                continue;
            }

            $neu[] = $sauber;

            if (isset($bisher[$id])) {
                $geaendert++;
                unset($bisher[$id]);
            } else {
                $dazu++;
            }
        }

        // Die lokalen bleiben hinten stehen, damit sie auffallen.
        Rewards::store($this->app, array_merge($neu, $nurLokal));

        return [
            'added'   => $dazu,
            'updated' => $geaendert,
            'removed' => count($bisher),
        ];
    }

    // -----------------------------------------------------------------
    //  Anlegen
    // -----------------------------------------------------------------

    /**
     * Eine nur hier vorhandene Belohnung bei Twitch anlegen.
     *
     * Danach traegt sie die Twitch-ID und gilt als schaltbar - sie ist
     * ja von uns.
     */
    public function push(string $id): bool
    {
        $belohnung = Rewards::find($this->app, $id);

        if ($belohnung === null) {
            $this->fehler = translate('channel_points.error.unknown');

            return false;
        }

        if (Rewards::isRemote($id)) {
            $this->fehler = translate('channel_points.error.already_there');

            return false;
        }

        $api = new RewardApi($this->app);
        $antwort = $api->create($belohnung);

        if ($antwort === null) {
            $this->fehler = $api->error();

            return false;
        }

        $neu = Rewards::fromTwitch($antwort, $belohnung, true);

        if ($neu === null) {
            $this->fehler = translate('channel_points.error.unknown');

            return false;
        }

        Rewards::forget($this->app, $id);
        Rewards::put($this->app, $neu);

        return true;
    }

    // -----------------------------------------------------------------
    //  Uebernehmen
    // -----------------------------------------------------------------

    /**
     * Eine fremde Belohnung zu einer eigenen machen.
     *
     * Loeschen, dann neu anlegen - in dieser Reihenfolge, weil Twitch
     * den Namen je Kanal nur einmal erlaubt. Andersherum waere es
     * sicherer, geht aber nicht.
     *
     * Deshalb der Notausgang: schlaegt das Anlegen fehl, ist die
     * Belohnung bei Twitch weg, aber NICHT hier. Sie bleibt als
     * lokaler Eintrag stehen und laesst sich mit "Bei Twitch anlegen"
     * nachholen. Ohne das waere ein Netzfehler zwischen zwei Aufrufen
     * gleichbedeutend mit Datenverlust.
     */
    public function adopt(string $id): bool
    {
        $belohnung = Rewards::find($this->app, $id);

        if ($belohnung === null) {
            $this->fehler = translate('channel_points.error.unknown');

            return false;
        }

        if (!empty($belohnung['manageable'])) {
            $this->fehler = translate('channel_points.error.already_ours');

            return false;
        }

        $api = new RewardApi($this->app);

        if (!$api->delete($id)) {
            $this->fehler = $api->error();

            return false;
        }

        $this->app->log(sprintf(
            'Kanalpunkte: "%s" bei Twitch geloescht, um sie zu uebernehmen.',
            (string) $belohnung['title']
        ));

        $antwort = $api->create($belohnung);

        if ($antwort === null) {
            // Der Notausgang. Die Belohnung ist bei Twitch weg - hier
            // bleibt sie, mit einer lokalen Kennung.
            $belohnung['id'] = Rewards::localId();
            $belohnung['manageable'] = false;

            Rewards::forget($this->app, $id);
            Rewards::put($this->app, $belohnung);

            $this->app->log(sprintf(
                'Kanalpunkte: "%s" konnte nach dem Loeschen NICHT neu angelegt werden: %s',
                (string) $belohnung['title'],
                $api->error()
            ));

            $this->fehler = translate('channel_points.error.adopt_stuck', [
                'reason' => $api->error(),
            ]);

            return false;
        }

        $neu = Rewards::fromTwitch($antwort, $belohnung, true);

        if ($neu === null) {
            $this->fehler = translate('channel_points.error.unknown');

            return false;
        }

        Rewards::forget($this->app, $id);
        Rewards::put($this->app, $neu);

        return true;
    }
}
