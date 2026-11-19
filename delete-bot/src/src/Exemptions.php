<?php

declare(strict_types=1);

namespace TwitchController\Plugin\DeleteBot;

use DateTimeImmutable;
use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * Bei wem die Muster nicht gelten.
 *
 * Mods, Subs, VIPs und wer schon lange genug folgt - jedes einzeln
 * schaltbar. Voreinstellung: niemand ist ausgenommen. So verhaelt sich
 * der Bot nach dem Update genau wie vorher, und wer Ausnahmen will,
 * schaltet sie bewusst ein.
 *
 * Gefragt wird erst NACH einem Treffer (siehe Moderator). Die Rollen
 * stehen in der Nachricht selbst, aber "folgt seit" kostet eine Anfrage
 * bei Twitch - und die soll nicht bei jeder Chatzeile anfallen, sondern
 * nur bei den wenigen, die sonst geloescht wuerden.
 */
final class Exemptions
{
    public const MIN_WEEKS = 1;

    /** Zehn Jahre. Mehr ist kein sinnvoller Wert, sondern ein Tippfehler. */
    public const MAX_WEEKS = 520;

    public const DEFAULT_WEEKS = 4;

    /**
     * Welche Badges zu welcher Ausnahme gehoeren.
     *
     * "founder" ist ein Sub der ersten Stunde und traegt dann dieses
     * Badge statt "subscriber" - ohne den Eintrag galten ausgerechnet
     * die treuesten Subs nicht als Subs.
     */
    private const BADGES = [
        'mods' => ['moderator', 'broadcaster'],
        'subs' => ['subscriber', 'founder'],
        'vips' => ['vip'],
    ];

    /**
     * @return array{mods: bool, subs: bool, vips: bool, followers: bool, weeks: int}
     */
    public static function get(App $app): array
    {
        $scope = Words::scope();

        return [
            'mods'      => $app->settings->bool('exempt_mods', false, $scope),
            'subs'      => $app->settings->bool('exempt_subs', false, $scope),
            'vips'      => $app->settings->bool('exempt_vips', false, $scope),
            'followers' => $app->settings->bool('exempt_followers', false, $scope),
            'weeks'     => self::weeks($app->settings->int('exempt_follow_weeks', self::DEFAULT_WEEKS, $scope)),
        ];
    }

    /**
     * @param array{mods: bool, subs: bool, vips: bool, followers: bool, weeks: int} $werte
     */
    public static function save(App $app, array $werte): void
    {
        $scope = Words::scope();

        $app->settings->set('exempt_mods', $werte['mods'], $scope);
        $app->settings->set('exempt_subs', $werte['subs'], $scope);
        $app->settings->set('exempt_vips', $werte['vips'], $scope);
        $app->settings->set('exempt_followers', $werte['followers'], $scope);
        $app->settings->set('exempt_follow_weeks', self::weeks($werte['weeks']), $scope);
    }

    /** Auf den erlaubten Bereich ziehen statt abzuweisen. */
    public static function weeks(int $wochen): int
    {
        return max(self::MIN_WEEKS, min(self::MAX_WEEKS, $wochen));
    }

    /**
     * Ist der Schreiber ausgenommen - und wenn ja, warum?
     *
     * @param array<string, mixed> $message Nutzlast von core.chat.message
     * @return string|null 'mods', 'subs', 'vips', 'followers' oder null
     */
    public static function reason(App $app, array $message): ?string
    {
        $regeln = self::get($app);

        // Erst die Rollen - sie stehen in der Nachricht und kosten nichts.
        $badges = [];
        foreach ((array) ($message['badges'] ?? []) as $badge) {
            if (is_array($badge) && isset($badge['set_id'])) {
                $badges[] = (string) $badge['set_id'];
            }
        }

        foreach (self::BADGES as $regel => $passend) {
            if ($regeln[$regel] && array_intersect($badges, $passend) !== []) {
                return $regel;
            }
        }

        if ($regeln['followers'] && self::followsLongEnough($app, $message, $regeln['weeks'])) {
            return 'followers';
        }

        return null;
    }

    /**
     * Folgt der Schreiber seit mindestens so vielen Wochen?
     *
     * Kann Twitch das nicht beantworten, ist die Antwort nein: im
     * Zweifel gilt die Regel. Eine Ausnahme, die bei einem Fehler
     * stillschweigend fuer alle greift, waere ein Loch im Filter.
     */
    private static function followsLongEnough(App $app, array $message, int $wochen): bool
    {
        $benutzer = (string) ($message['chatter_id'] ?? '');
        $kanal = $app->twitch->broadcasterId();

        if ($benutzer === '' || $kanal === '') {
            return false;
        }

        try {
            // Wie bei den Chatbefehlen: der Endpunkt braucht
            // moderator:read:followers von einem Moderator des Kanals -
            // der Kanalinhaber ist das immer.
            $antwort = $app->twitch->api()->as(TokenStore::BROADCASTER)->get('channels/followers', [
                'broadcaster_id' => $kanal,
                'user_id'        => $benutzer,
            ]);

            if (!$antwort->ok()) {
                $app->log('Loeschbot: "folgt seit" nicht abfragbar - ' . $antwort->error());

                return false;
            }

            $seit = trim((string) ($antwort->json['data'][0]['followed_at'] ?? ''));
            if ($seit === '') {
                return false;
            }

            $grenze = new DateTimeImmutable('-' . $wochen . ' weeks');

            return new DateTimeImmutable($seit) <= $grenze;
        } catch (Throwable $fehler) {
            $app->log('Loeschbot: "folgt seit" nicht abfragbar - ' . $fehler->getMessage());

            return false;
        }
    }
}
