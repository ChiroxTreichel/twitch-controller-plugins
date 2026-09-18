<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * Der Weg zu den Kanalpunkt-Belohnungen bei Twitch.
 *
 * Immer mit dem Token des Kanalinhabers: Kanalpunkte gehoeren dem
 * Kanal, ein App-Token kommt hier nicht weit.
 *
 * Der wichtigste Punkt steht in der Beschreibung von Twitch zu
 * "Update Custom Reward":
 *
 *   "The custom reward's broadcaster must have created the reward
 *    using the same client ID that's used to update or delete the
 *    reward."
 *
 * Lesen darf diese App also alles, aendern nur die eigenen. Welche
 * das sind, sagt Twitch selbst - ueber only_manageable_rewards. Genau
 * darum fragt list() zweimal: einmal nach allem, einmal nach dem, was
 * wir anfassen duerfen. Der Unterschied ist die Liste, bei der jeder
 * Schaltversuch ein 403 waere.
 */
final class RewardApi
{
    private const ENDPOINT = 'channel_points/custom_rewards';

    private string $fehler = '';

    public function __construct(private readonly App $app)
    {
    }

    public function error(): string
    {
        return $this->fehler;
    }

    public function broadcasterId(): string
    {
        return $this->app->settings->string('twitch_broadcaster_id');
    }

    /**
     * Darf ueberhaupt geschaltet werden?
     *
     * Gefragt wird das gespeicherte Token, nicht Twitch: die Freigaben
     * stehen beim Token, und eine Seite soll fuer eine Ja/Nein-Frage
     * keinen Netzaufruf machen.
     */
    public function allowed(): bool
    {
        try {
            return $this->app->twitch->tokens()->missingScopes(
                TokenStore::BROADCASTER,
                [Rewards::SCOPE]
            ) === [];
        } catch (Throwable) {
            return false;
        }
    }

    // -----------------------------------------------------------------
    //  Lesen
    // -----------------------------------------------------------------

    /**
     * Alle Belohnungen des Kanals, jeweils mit der Angabe, ob wir sie
     * schalten duerfen.
     *
     * @return list<array{raw: array<string, mixed>, manageable: bool}>|null
     *         null, wenn Twitch nicht antwortet - siehe error()
     */
    public function list(): ?array
    {
        $alle = $this->fetch(false);
        if ($alle === null) {
            return null;
        }

        /*
         * Die zweite Abfrage darf fehlschlagen, ohne die erste
         * mitzureissen: dann steht die Liste da, und keine Belohnung
         * gilt als schaltbar. Das ist die vorsichtige Richtung - eine
         * faelschlich als schaltbar gefuehrte Belohnung wuerde bei
         * jedem Takt ein 403 erzeugen.
         */
        $eigene = $this->fetch(true) ?? [];

        $eigeneIds = [];
        foreach ($eigene as $zeile) {
            $eigeneIds[(string) ($zeile['id'] ?? '')] = true;
        }

        $liste = [];
        foreach ($alle as $zeile) {
            $id = (string) ($zeile['id'] ?? '');

            $liste[] = [
                'raw'        => $zeile,
                'manageable' => $id !== '' && isset($eigeneIds[$id]),
            ];
        }

        return $liste;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetch(bool $nurEigene): ?array
    {
        $kanal = $this->broadcasterId();
        if ($kanal === '') {
            $this->fehler = translate('channel_points.error.no_channel');

            return null;
        }

        $query = ['broadcaster_id' => $kanal];

        if ($nurEigene) {
            $query['only_manageable_rewards'] = 'true';
        }

        try {
            $antwort = $this->app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->get(self::ENDPOINT, $query);
        } catch (Throwable $e) {
            $this->fehler = $e->getMessage();

            return null;
        }

        if (!$antwort->ok()) {
            $this->fehler = $this->reason($antwort->status, $antwort->error());

            return null;
        }

        $daten = $antwort->json['data'] ?? [];

        return is_array($daten) ? array_values(array_filter($daten, 'is_array')) : [];
    }

    // -----------------------------------------------------------------
    //  Schreiben
    // -----------------------------------------------------------------

    /**
     * Eine Belohnung bei Twitch anlegen.
     *
     * @param array<string, mixed> $belohnung
     * @return array<string, mixed>|null die Antwort von Twitch
     */
    public function create(array $belohnung): ?array
    {
        $kanal = $this->broadcasterId();
        if ($kanal === '') {
            $this->fehler = translate('channel_points.error.no_channel');

            return null;
        }

        return $this->send(
            'post',
            ['broadcaster_id' => $kanal],
            Rewards::payload($belohnung)
        );
    }

    /**
     * Eine bestehende Belohnung aendern.
     *
     * @param array<string, mixed> $belohnung
     * @return array<string, mixed>|null
     */
    public function update(string $id, array $belohnung): ?array
    {
        $kanal = $this->broadcasterId();
        if ($kanal === '' || $id === '') {
            $this->fehler = translate('channel_points.error.no_channel');

            return null;
        }

        return $this->send(
            'patch',
            ['broadcaster_id' => $kanal, 'id' => $id],
            Rewards::payload($belohnung)
        );
    }

    /**
     * Nur den Schalter umlegen - fuer die Bedingungen.
     *
     * Getrennt von update(), weil hier wirklich nur ein Feld gehen
     * soll: wer im Takt den ganzen Rumpf schickt, ueberschreibt
     * nebenbei Aenderungen, die jemand im Dashboard gemacht hat.
     */
    public function setEnabled(string $id, bool $an): bool
    {
        $kanal = $this->broadcasterId();
        if ($kanal === '' || $id === '') {
            $this->fehler = translate('channel_points.error.no_channel');

            return false;
        }

        return $this->send(
            'patch',
            ['broadcaster_id' => $kanal, 'id' => $id],
            ['is_enabled' => $an]
        ) !== null;
    }

    public function delete(string $id): bool
    {
        $kanal = $this->broadcasterId();
        if ($kanal === '' || $id === '') {
            $this->fehler = translate('channel_points.error.no_channel');

            return false;
        }

        try {
            $antwort = $this->app->twitch->api()
                ->as(TokenStore::BROADCASTER)
                ->delete(self::ENDPOINT, ['broadcaster_id' => $kanal, 'id' => $id]);
        } catch (Throwable $e) {
            $this->fehler = $e->getMessage();

            return false;
        }

        if (!$antwort->ok()) {
            $this->fehler = $this->reason($antwort->status, $antwort->error());

            return false;
        }

        return true;
    }

    /**
     * @param array<string, string|int> $query
     * @param array<string, mixed>      $rumpf
     * @return array<string, mixed>|null
     */
    private function send(string $methode, array $query, array $rumpf): ?array
    {
        try {
            $api = $this->app->twitch->api()->as(TokenStore::BROADCASTER);

            $antwort = $methode === 'post'
                ? $api->post(self::ENDPOINT, $query, $rumpf)
                : $api->patch(self::ENDPOINT, $query, $rumpf);
        } catch (Throwable $e) {
            $this->fehler = $e->getMessage();

            return null;
        }

        if (!$antwort->ok()) {
            $this->fehler = $this->reason($antwort->status, $antwort->error());

            return null;
        }

        $zeile = $antwort->json['data'][0] ?? null;

        return is_array($zeile) ? $zeile : [];
    }

    /**
     * Aus einem Statuscode einen Satz machen, der weiterhilft.
     *
     * Vor allem 403: der steht hier fast immer fuer "die Belohnung
     * gehoert einer anderen App", und das ist die eine Sache, die man
     * bei Kanalpunkten wissen muss.
     */
    private function reason(int $status, string $roh): string
    {
        return match ($status) {
            401     => translate('channel_points.error.unauthorized'),
            403     => translate('channel_points.error.forbidden'),
            429     => translate('channel_points.error.rate_limit'),
            default => translate('channel_points.error.rejected', ['reason' => $roh]),
        };
    }
}
