<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

use TwitchController\Core\App;

/**
 * Gruppen von Belohnungen.
 *
 * Eine Gruppe fasst mehrere Belohnungen zusammen und traegt dieselben
 * vier Bedingungsfelder wie eine einzelne. Sie ersetzt deren
 * Bedingungen nicht, sondern kommt dazu:
 *
 *   "An, wenn Minecraft" an der Gruppe und "Aus, wenn Just Chatting"
 *   an einer einzelnen Belohnung gelten beide.
 *
 * Und dabei gilt: WER AUS SAGT, GEWINNT. Eine Aus-Bedingung - egal ob
 * an der Belohnung oder an einer ihrer Gruppen - schaltet ab, auch
 * wenn alles andere passt. Zusammengerechnet wird das in
 * Conditions::combine().
 *
 * Gespeichert unter "groups" im Scope "plugin:channel-points".
 *
 * Mitglieder stehen als Twitch-Kennung drin. Die kann sich aendern -
 * eine fremde Belohnung, die neu angelegt wird, bekommt eine neue.
 * Darum gibt es rename(): ohne das faende sich die Belohnung nach dem
 * Neuanlegen in keiner Gruppe mehr wieder, und niemand wuesste, warum
 * sie nicht mehr geschaltet wird.
 */
final class Groups
{
    /** Mehr Gruppen macht die Sache unuebersichtlicher, nicht besser. */
    public const MAX_GROUPS = 30;

    public const MAX_NAME = 60;

    /**
     * Alle Gruppen, alphabetisch.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(App $app): array
    {
        $gespeichert = $app->settings->get('groups', null, Rewards::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $gruppen = [];
        foreach ($gespeichert as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }

            $sauber = self::normalize($eintrag);
            if ($sauber !== null) {
                $gruppen[] = $sauber;
            }
        }

        usort($gruppen, static fn (array $a, array $b): int => strnatcasecmp(
            Rewards::sortKey((string) $a['name']),
            Rewards::sortKey((string) $b['name'])
        ) ?: strcmp((string) $a['name'], (string) $b['name']));

        return array_values($gruppen);
    }

    /**
     * @param list<array<string, mixed>> $liste
     */
    public static function store(App $app, array $liste): void
    {
        $app->settings->set('groups', array_values($liste), Rewards::scope());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(App $app, string $id): ?array
    {
        foreach (self::all($app) as $gruppe) {
            if ((string) $gruppe['id'] === $id && $id !== '') {
                return $gruppe;
            }
        }

        return null;
    }

    /**
     * Anlegen oder ersetzen.
     *
     * @param array<string, mixed> $gruppe
     */
    public static function put(App $app, array $gruppe): bool
    {
        $sauber = self::normalize($gruppe);

        if ($sauber === null) {
            return false;
        }

        $liste = self::all($app);

        foreach ($liste as $stelle => $vorhanden) {
            if ((string) $vorhanden['id'] === (string) $sauber['id']) {
                $liste[$stelle] = $sauber;
                self::store($app, $liste);

                return true;
            }
        }

        if (count($liste) >= self::MAX_GROUPS) {
            return false;
        }

        $liste[] = $sauber;
        self::store($app, $liste);

        return true;
    }

    public static function forget(App $app, string $id): bool
    {
        $alle = self::all($app);

        $uebrig = array_values(array_filter(
            $alle,
            static fn (array $gruppe): bool => (string) $gruppe['id'] !== $id
        ));

        if (count($uebrig) === count($alle)) {
            return false;
        }

        self::store($app, $uebrig);

        return true;
    }

    // -----------------------------------------------------------------
    //  Mitglieder
    // -----------------------------------------------------------------

    /**
     * Die Gruppen, in denen diese Belohnung steckt.
     *
     * @param list<array<string, mixed>> $gruppen
     * @return list<array<string, mixed>>
     */
    public static function forReward(array $gruppen, string $belohnungId): array
    {
        if ($belohnungId === '') {
            return [];
        }

        $treffer = [];

        foreach ($gruppen as $gruppe) {
            $mitglieder = is_array($gruppe['members'] ?? null) ? $gruppe['members'] : [];

            if (in_array($belohnungId, array_map('strval', $mitglieder), true)) {
                $treffer[] = $gruppe;
            }
        }

        return $treffer;
    }

    /**
     * Eine Belohnung hat eine neue Kennung bekommen.
     *
     * Passiert beim Neuanlegen einer fremden Belohnung und beim
     * Nachholen einer nur lokalen: Twitch vergibt dabei eine neue.
     * Ohne diesen Schritt faellt sie lautlos aus jeder Gruppe.
     */
    public static function rename(App $app, string $alt, string $neu): void
    {
        if ($alt === '' || $neu === '' || $alt === $neu) {
            return;
        }

        $liste = self::all($app);
        $geaendert = false;

        foreach ($liste as $stelle => $gruppe) {
            $mitglieder = array_map('strval', $gruppe['members']);
            $wo = array_search($alt, $mitglieder, true);

            if ($wo === false) {
                continue;
            }

            $mitglieder[$wo] = $neu;
            $liste[$stelle]['members'] = array_values(array_unique($mitglieder));
            $geaendert = true;
        }

        if ($geaendert) {
            self::store($app, $liste);
        }
    }

    /**
     * Eine Belohnung ist weg - aus allen Gruppen nehmen.
     *
     * Eine Kennung, die niemandem mehr gehoert, wuerde sonst ewig in
     * der Mitgliederliste stehen und beim naechsten Anlegen womoeglich
     * wieder passen.
     */
    public static function dropMember(App $app, string $belohnungId): void
    {
        if ($belohnungId === '') {
            return;
        }

        $liste = self::all($app);
        $geaendert = false;

        foreach ($liste as $stelle => $gruppe) {
            $uebrig = array_values(array_filter(
                array_map('strval', $gruppe['members']),
                static fn (string $id): bool => $id !== $belohnungId
            ));

            if (count($uebrig) !== count($gruppe['members'])) {
                $liste[$stelle]['members'] = $uebrig;
                $geaendert = true;
            }
        }

        if ($geaendert) {
            self::store($app, $liste);
        }
    }

    // -----------------------------------------------------------------
    //  Pruefen und Zurechtruecken
    // -----------------------------------------------------------------

    /**
     * Eine Gruppe, wie sie gespeichert wird - oder null, wenn sie
     * unbrauchbar ist.
     *
     * Unbrauchbar heisst: ohne Namen. Eine namenlose Gruppe laesst
     * sich in der Liste nicht zuordnen.
     *
     * @param array<string, mixed> $eingabe
     * @return array<string, mixed>|null
     */
    public static function normalize(array $eingabe): ?array
    {
        $name = trim((string) ($eingabe['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $roh = is_array($eingabe['members'] ?? null) ? $eingabe['members'] : [];
        $mitglieder = [];

        foreach ($roh as $id) {
            $id = trim((string) $id);

            if ($id !== '' && !in_array($id, $mitglieder, true)) {
                $mitglieder[] = $id;
            }
        }

        return [
            'id'        => self::normalizeId((string) ($eingabe['id'] ?? '')),
            'name'      => Rewards::cut($name, self::MAX_NAME),
            'members'   => $mitglieder,

            /*
             * Ausgeschaltet heisst: DIESE REGEL ZAEHLT NICHT MIT.
             *
             * Nicht etwa, dass die Belohnungen darin ausgehen - das
             * waere das Gegenteil dessen, wofuer man den Schalter
             * umlegt. Eine ausgeschaltete Gruppe ist eine, die man
             * fuer heute beiseite legt, ohne sie zu loeschen; ihre
             * Mitglieder richten sich dann nach ihren eigenen
             * Bedingungen.
             *
             * Neu ist eine Gruppe an - sonst legt man sie an, traegt
             * alles ein und wundert sich, dass nichts geschieht.
             */
            'enabled'   => !array_key_exists('enabled', $eingabe) || !empty($eingabe['enabled']),

            // Dieselben vier Felder wie bei einer Belohnung - und
            // dieselbe Form: An als Komma-Zeile, Aus als Liste.
            'title_on'  => Rewards::cut(trim((string) ($eingabe['title_on'] ?? '')), 200),
            'game_on'   => Rewards::cut(trim((string) ($eingabe['game_on'] ?? '')), 200),
            'title_off' => Rewards::entries($eingabe['title_off'] ?? null),
            'game_off'  => Rewards::entries($eingabe['game_off'] ?? null),
        ];
    }

    /**
     * Eine Kennung fuer eine Gruppe.
     *
     * Sie gehoert uns und nicht Twitch, darf also frei vergeben
     * werden - das Praefix haelt sie nur von einer Belohnungskennung
     * auseinander.
     */
    public static function normalizeId(string $roh): string
    {
        $roh = trim($roh);

        return preg_match('/^g:[0-9a-f]{16}$/', $roh) === 1
            ? $roh
            : 'g:' . bin2hex(random_bytes(8));
    }
}
