<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TwitchAlerts;

/**
 * ===================================================================
 *  Die sechs Alert-Typen
 * ===================================================================
 *
 * Eine Tabelle, zwei Verwendungen: sie baut die Reiter in der
 * Oberflaeche UND entscheidet, welcher Alert zu einem Twitch-Event
 * gehoert. Beides aus derselben Quelle - sonst zeigt die Oberflaeche
 * einen Fall an, den die Auswertung nie erreicht.
 *
 * Zwei Bauformen:
 *
 *   cases  feste Faelle (Erster Sub / Resub / Resub mit Streak).
 *          Welcher gilt, entscheidet die Auswertung am Event.
 *
 *   tiers  Stufen nach einer Zahl ("ab 100 Bits"). Der Betreiber legt
 *          sie selbst an, die hoechste passende gewinnt.
 *
 * Die Vorgabetexte sind die des alten Systems.
 */
final class Types
{
    /**
     * @return array<string, array{
     *     label: string,
     *     order: int,
     *     mode: string,
     *     unit: string,
     *     cases: array<string, string>,
     *     placeholders: list<string>,
     *     defaults: array<string, string>,
     *     preview: array<string, string>
     * }>
     */
    public static function all(): array
    {
        return [
            'follow' => [
                'label'        => translate('twitch_alerts.follow'),
                'order'        => 10,
                'mode'         => 'cases',
                'unit'         => '',
                'cases'        => ['default' => translate('twitch_alerts.case.follow')],
                'placeholders' => ['username'],
                'defaults'     => ['default' => '{{ username }} folgt dir jetzt'],
                'preview'      => ['username' => 'Talutah'],
            ],

            'cheer' => [
                'label'        => translate('twitch_alerts.cheer'),
                'order'        => 20,
                'mode'         => 'tiers',
                'unit'         => translate('twitch_alerts.unit.bits'),
                'cases'        => [],
                'placeholders' => ['username', 'amount', 'message'],
                'defaults'     => ['tier' => '{{ username }} hat {{ amount }} Bits gespendet'],
                'preview'      => [
                    'username' => 'Talutah',
                    'amount'   => '100',
                    'message'  => 'Das ist ein Test-Cheer',
                ],
            ],

            'sub' => [
                'label'        => translate('twitch_alerts.sub'),
                'order'        => 30,
                'mode'         => 'cases',
                'unit'         => '',
                'cases'        => [
                    'first'        => translate('twitch_alerts.case.first'),
                    'resub'        => translate('twitch_alerts.case.resub'),
                    'resub_streak' => translate('twitch_alerts.case.resub_streak'),
                ],
                'placeholders' => ['username', 'totalsubs', 'consecutive', 'tier'],
                'defaults'     => [
                    'first'        => '{{ username }} hat neu abonniert',
                    'resub'        => '{{ username }} hat zum {{ totalsubs }}. Mal abonniert',
                    'resub_streak' => '{{ username }} ist seit {{ consecutive }} Monaten dabei',
                ],
                'preview' => [
                    'username'    => 'Talutah',
                    'totalsubs'   => '3',
                    'consecutive' => '2',
                    'tier'        => 'Tier 1',
                ],
            ],

            'subgift' => [
                'label'        => translate('twitch_alerts.subgift'),
                'order'        => 40,
                'mode'         => 'cases',
                'unit'         => '',
                'cases'        => [
                    'single'      => translate('twitch_alerts.case.single'),
                    'multi'       => translate('twitch_alerts.case.multi'),
                    'anon_single' => translate('twitch_alerts.case.anon_single'),
                    'anon_multi'  => translate('twitch_alerts.case.anon_multi'),
                ],
                'placeholders' => ['username', 'receiver', 'amount', 'tier'],
                'defaults'     => [
                    'single'      => '{{ username }} hat {{ receiver }} ein Abo geschenkt',
                    'multi'       => '{{ username }} hat {{ amount }} Abos verschenkt',
                    'anon_single' => 'Jemand hat {{ receiver }} ein Abo geschenkt',
                    'anon_multi'  => 'Jemand hat {{ amount }} Abos verschenkt',
                ],
                'preview' => [
                    'username' => 'Talutah',
                    'receiver' => 'Chirox',
                    'amount'   => '5',
                    'tier'     => 'Tier 1',
                ],
            ],

            'subprime' => [
                'label'        => translate('twitch_alerts.subprime'),
                'order'        => 50,
                'mode'         => 'cases',
                'unit'         => '',
                'cases'        => [
                    'first'        => translate('twitch_alerts.case.first'),
                    'resub'        => translate('twitch_alerts.case.resub'),
                    'resub_streak' => translate('twitch_alerts.case.resub_streak'),
                ],
                'placeholders' => ['username', 'totalsubs', 'consecutive'],
                'defaults'     => [
                    'first'        => '{{ username }} hat mit Prime abonniert',
                    'resub'        => '{{ username }} hat zum {{ totalsubs }}. Mal mit Prime abonniert',
                    'resub_streak' => '{{ username }} ist seit {{ consecutive }} Monaten mit Prime dabei',
                ],
                'preview' => [
                    'username'    => 'Talutah',
                    'totalsubs'   => '3',
                    'consecutive' => '2',
                ],
            ],

            'raid' => [
                'label'        => translate('twitch_alerts.raid'),
                'order'        => 60,
                'mode'         => 'tiers',
                'unit'         => translate('twitch_alerts.unit.raiders'),
                'cases'        => [],
                'placeholders' => ['username', 'amount'],
                'defaults'     => ['tier' => '{{ username }} raidet mit {{ amount }} Zuschauern'],
                'preview'      => [
                    'username' => 'Talutah',
                    'amount'   => '10',
                ],
            ],
        ];
    }

    /**
     * Klarnamen der Testdaten-Felder.
     *
     * Ausgeschrieben und nicht per translate('…field.' . $key)
     * zusammengesetzt: nur so sieht bin/lang.php die Schluessel und
     * kann fehlende melden. Sonst faellt eine Luecke erst im Betrieb
     * auf - als nackter Schluessel mitten im Formular.
     *
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return [
            'username'    => translate('twitch_alerts.field.username'),
            'receiver'    => translate('twitch_alerts.field.receiver'),
            'amount'      => translate('twitch_alerts.field.amount'),
            'message'     => translate('twitch_alerts.field.message'),
            'tier'        => translate('twitch_alerts.field.tier'),
            'totalsubs'   => translate('twitch_alerts.field.totalsubs'),
            'consecutive' => translate('twitch_alerts.field.consecutive'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /**
     * Die Vorgabestufe eines Stufen-Typs: eine Stufe ab 1, damit nach
     * der Installation ueberhaupt etwas kommt.
     *
     * @return array<string, mixed>
     */
    public static function defaultTier(string $type): array
    {
        $definition = self::get($type);

        return [
            'min'      => 1,
            'text'     => (string) ($definition['defaults']['tier'] ?? ''),
            'video'    => '',
            'audio'    => '',
            'duration' => 6,
        ];
    }

    /**
     * Welcher Alert-Typ gehoert zu welchem Twitch-Event?
     *
     * Die Zuordnung ist nicht eins zu eins: "channel.subscribe" wird je
     * nach Nutzlast zu einem Sub, einem Prime-Sub oder gar keinem Alert
     * (bei einem geschenkten Abo meldet Twitch zusaetzlich
     * "subscription.gift" - sonst gaebe es zwei Alerts fuer ein
     * Ereignis).
     *
     * @return list<string> Event-Typen, auf die dieses Plugin hoert
     */
    public static function eventTypes(): array
    {
        return [
            'twitch.channel.follow',
            'twitch.channel.cheer',
            'twitch.channel.subscribe',
            'twitch.channel.subscription.message',
            'twitch.channel.subscription.gift',
            'twitch.channel.raid',
        ];
    }
}
