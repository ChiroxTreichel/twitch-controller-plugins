<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

use TwitchController\Core\App;

/**
 * Der Abgleich mit Twitch.
 *
 * Drei Wege:
 *
 *   import()    holt, was im Kanal existiert - der Knopf
 *               "Kanalpunktbelohnungen laden"
 *   push()      legt eine hier vorhandene, aber bei Twitch fehlende
 *               Belohnung an
 *   recreate()  legt eine fremde Belohnung neu an, nachdem der
 *               Benutzer sie im Dashboard von Hand geloescht hat
 *
 * recreate() ist der einzige Weg, eine im Creator-Dashboard angelegte
 * Belohnung jemals schalten zu koennen: die Client-ID des Erstellers
 * laesst sich nicht nachtraeglich aendern, also muss die Belohnung
 * einmal von uns angelegt werden.
 *
 * Geloescht wird dabei NICHT von hier. Twitch liesse es bei einer
 * fremden Belohnung ohnehin nicht zu, und selbst wenn - eine
 * Reihenfolge "erst loeschen, dann anlegen" hat ein Fenster, in dem
 * die Belohnung nirgends mehr steht. Der Benutzer loescht sie im
 * Dashboard und sagt hinterher Bescheid.
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
     * lokal steht - weil das Anlegen fehlschlug - bleibt: das ist
     * Arbeit, die sonst verloren ginge.
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
    //  Neu anlegen
    // -----------------------------------------------------------------
    /**
     * Eine fremde Belohnung als eigene neu anlegen.
     *
     * Voraussetzung: sie ist bei Twitch von Hand geloescht worden.
     * Das kann dieses System nicht nachpruefen - aber es muss auch
     * nicht: steht sie noch, lehnt Twitch wegen des doppelten Namens
     * ab, und die Meldung sagt genau das.
     *
     * Die Bedingungen wandern mit. Sie sind das, was hier ueberhaupt
     * gepflegt wurde.
     */
    public function recreate(string $id): bool
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
        $antwort = $api->create($belohnung);

        if ($antwort === null) {
            $this->fehler = translate('channel_points.error.recreate_failed', [
                'reason' => $api->error(),
            ]);

            return false;
        }

        $neu = Rewards::fromTwitch($antwort, $belohnung, true);

        if ($neu === null) {
            $this->fehler = translate('channel_points.error.unknown');

            return false;
        }

        // Erst jetzt den alten Eintrag weg: bis hierher konnte noch
        // etwas schiefgehen, und dann waere er das Einzige gewesen,
        // was die Bedingungen noch hatte.
        Rewards::forget($this->app, $id);
        Rewards::put($this->app, $neu);

        $this->app->log(sprintf(
            'Kanalpunkte: "%s" als eigene Belohnung neu angelegt.',
            (string) $belohnung['title']
        ));

        return true;
    }
}
