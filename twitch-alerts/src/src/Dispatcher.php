<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TwitchAlerts;

use TwitchController\Core\App;
use TwitchController\Core\Obs\Payload;
use TwitchController\Plugin\Alerts\Alerts;

/**
 * ===================================================================
 *  Vom Twitch-Event zum Alert
 * ===================================================================
 *
 * Laeuft im Webhook-Request. Twitch erwartet eine schnelle Antwort,
 * also passiert hier nur das Noetigste: Typ und Fall bestimmen, Werte
 * einsammeln, an Alerts weitergeben. Das Anzeigen macht das Overlay.
 *
 * Die Einordnung der Nutzlast - Tier, Prime, geschenkt - kommt aus
 * Obs\Payload des Kerns. Twitch schreibt dasselbe je nach Quelle
 * unterschiedlich ("tier": "1000" oder "sub_plan": "Prime"), und diese
 * Faelle sind dort schon abgedeckt.
 */
final class Dispatcher
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array<string, mixed> $event Zeile aus core.event.stored
     */
    public function handle(array $event): void
    {
        $eventType = (string) ($event['event_type'] ?? '');
        if (!in_array($eventType, Types::eventTypes(), true)) {
            return;
        }

        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        $entschieden = $this->decide($eventType, $event, $payload);
        if ($entschieden === null) {
            return;
        }

        [$type, $case, $values, $amount] = $entschieden;

        $config = Config::of($this->app, $type);
        if (!$config['enabled']) {
            return;
        }

        $definition = Types::get($type);
        if ($definition === null) {
            return;
        }

        if ($definition['mode'] === 'tiers') {
            $stufe = Config::matchTier($config['tiers'], $amount);
            if ($stufe === null) {
                return;
            }

            $quelle = $stufe;
        } else {
            if (!isset($config['cases'][$case])) {
                return;
            }

            $quelle = $config['cases'][$case];
        }

        Alerts::send($this->app, [
            'kind'     => $type . ($case !== '' ? '.' . $case : ''),
            'text'     => (string) $quelle['text'],
            'values'   => $values,
            'video'    => (string) $quelle['video'],
            'audio'    => (string) $quelle['audio'],
            'duration' => (int) $quelle['duration'],
        ]);
    }

    /**
     * Typ, Fall, Werte und die Zahl fuer die Stufenwahl.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $payload
     * @return array{0: string, 1: string, 2: array<string, string>, 3: int}|null
     */
    private function decide(string $eventType, array $event, array $payload): ?array
    {
        $name = trim((string) ($event['actor_name'] ?? ''));
        $betrag = (int) round((float) ($event['amount'] ?? 0));

        switch ($eventType) {
            case 'twitch.channel.follow':
                return ['follow', 'default', ['username' => $name], 0];

            case 'twitch.channel.cheer':
                return ['cheer', '', [
                    'username' => $name !== '' ? $name : translate('twitch_alerts.anonymous'),
                    'amount'   => (string) $betrag,
                    'message'  => trim((string) ($event['message'] ?? '')),
                ], $betrag];

            case 'twitch.channel.raid':
                return ['raid', '', [
                    'username' => $name,
                    'amount'   => (string) $betrag,
                ], $betrag];

            case 'twitch.channel.subscription.gift':
                $anonym = Payload::bool($payload, ['is_anonymous', 'anonymous']);
                $anzahl = max(1, (int) round((float) ($event['amount'] ?? 1)));

                // Twitch nennt den Beschenkten nur bei einem einzelnen
                // Geschenk. Bei mehreren gibt es keine Namensliste.
                $empfaenger = Payload::string($payload, ['recipient_user_name', 'recipient_name']);

                $fall = ($anzahl > 1 ? 'multi' : 'single');
                if ($anonym) {
                    $fall = 'anon_' . $fall;
                }

                return ['subgift', $fall, [
                    'username' => $anonym ? translate('twitch_alerts.anonymous') : $name,
                    'receiver' => $empfaenger,
                    'amount'   => (string) $anzahl,
                    'tier'     => self::tierLabel($payload),
                ], $anzahl];

            case 'twitch.channel.subscribe':
                // Ein geschenktes Abo meldet Twitch zweimal: als
                // "subscribe" mit is_gift und als "subscription.gift".
                // Nur der zweite Weg wird zum Alert, sonst erscheinen
                // zwei fuer ein Ereignis.
                if (Payload::bool($payload, ['is_gift', 'isGift'])) {
                    return null;
                }

                $typ = Payload::isPrime($event, $payload) ? 'subprime' : 'sub';

                return [$typ, 'first', [
                    'username'    => $name,
                    'totalsubs'   => '1',
                    'consecutive' => '1',
                    'tier'        => self::tierLabel($payload),
                ], 0];

            case 'twitch.channel.subscription.message':
                $typ = Payload::isPrime($event, $payload) ? 'subprime' : 'sub';

                $gesamt = Payload::number($payload, ['cumulative_months', 'cumulativeMonths'], 0);
                $streak = Payload::number($payload, ['streak_months', 'streakMonths'], 0);

                // Mit Streak gibt es einen eigenen Text - aber nur,
                // wenn Twitch ihn mitgeschickt hat. Der Zuschauer kann
                // das Teilen abschalten; dann kommt 0, und ein Text
                // "seit 0 Monaten dabei" waere falsch.
                $fall = $streak > 1 ? 'resub_streak' : 'resub';

                return [$typ, $fall, [
                    'username'    => $name,
                    'totalsubs'   => (string) max(1, $gesamt),
                    'consecutive' => (string) max(1, $streak),
                    'tier'        => self::tierLabel($payload),
                ], 0];
        }

        return null;
    }

    /**
     * Ein Test-Alert.
     *
     * Die Werte kommen aus dem Formular und werden nicht gespeichert -
     * sie sind zum Wegwerfen. Was fehlt, wird aus der Vorgabe des Typs
     * ergaenzt, damit ein leeres Feld nicht zu einem leeren Alert
     * fuehrt.
     *
     * @param array<string, string> $werte
     */
    public function test(string $type, string $case = '', array $werte = []): bool
    {
        $definition = Types::get($type);
        if ($definition === null) {
            return false;
        }

        $config = Config::of($this->app, $type);

        // Leere Felder aus der Vorgabe fuellen. Ein Test ohne Namen
        // zeigte sonst einen Alert ohne Namen - und man haelt es fuer
        // einen Fehler im Alert.
        $werte = array_filter($werte, static fn (string $w): bool => trim($w) !== '');
        $werte += $config['preview'];

        if ($definition['mode'] === 'tiers') {
            $betrag = (int) round((float) ($werte['amount'] ?? 0));
            $stufe = Config::matchTier($config['tiers'], $betrag);

            if ($stufe === null) {
                return false;
            }

            $quelle = $stufe;
        } else {
            if ($case === '' || !isset($config['cases'][$case])) {
                $case = (string) array_key_first($config['cases']);
            }

            if (!isset($config['cases'][$case])) {
                return false;
            }

            $quelle = $config['cases'][$case];
        }

        return Alerts::send($this->app, [
            'kind'     => 'test.' . $type . ($case !== '' ? '.' . $case : ''),
            'text'     => (string) $quelle['text'],
            'values'   => $werte,
            'video'    => (string) $quelle['video'],
            'audio'    => (string) $quelle['audio'],
            'duration' => (int) $quelle['duration'],
        ]);
    }

    /**
     * "Tier 1" / "Tier 2" / "Tier 3" / "Prime" - so, wie es im Text
     * stehen soll.
     *
     * @param array<string, mixed> $payload
     */
    private static function tierLabel(array $payload): string
    {
        return match (Payload::tier($payload)) {
            '2000' => 'Tier 2',
            '3000' => 'Tier 3',
            '1000' => 'Tier 1',
            default => '',
        };
    }
}
