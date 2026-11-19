<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Viewers;

use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * Einstellungen und Konstanten des Plugins.
 */
final class Viewers
{
    public const SLUG = 'viewers';

    /** Zeilen je Seite in den Listen. */
    public const PER_PAGE = 50;

    /**
     * So oft werden die Abos abgeglichen. Die Follower gleicht der
     * Kern ab, alle zehn Sekunden - siehe Twitch\Followers.
     */
    public const SUBS_EVERY = 600;

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    /**
     * Darf erfasst werden, wer kein Abo mehr hat?
     *
     * Der erste der beiden Schalter. Aus ist die Voreinstellung: dafuer
     * werden Daten ueber Menschen aufbewahrt, die mit dem Kanal nichts
     * mehr zu tun haben wollen - das soll niemand nebenbei einschalten.
     *
     * Ausschalten loescht nichts. Was erfasst ist, bleibt liegen und
     * ist nur nicht mehr zu sehen; es wird auch nichts Neues erfasst.
     */
    public static function historyAllowed(App $app): bool
    {
        return $app->settings->bool('history_allowed', false, self::scope());
    }

    public static function setHistoryAllowed(App $app, bool $an): void
    {
        $app->settings->set('history_allowed', $an, self::scope());
    }

    /**
     * Darf erfasst werden, wer nicht mehr folgt? Dasselbe wie bei den
     * Abos, eigener Schalter: wer das eine will, will nicht unbedingt
     * das andere.
     *
     * Die Unfollows selbst stehen ohnehin in den Aktivitaeten - das ist
     * ein Kernfeature. Hier geht es um die Liste: wer, seit wann, mit
     * Suche.
     */
    public static function unfollowsAllowed(App $app): bool
    {
        return $app->settings->bool('unfollows_allowed', false, self::scope());
    }

    public static function setUnfollowsAllowed(App $app, bool $an): void
    {
        $app->settings->set('unfollows_allowed', $an, self::scope());
    }

    /** Klarname einer Abo-Stufe. */
    public static function tierLabel(string $tier): string
    {
        return match ($tier) {
            '2000'  => translate('viewers.tier.2'),
            '3000'  => translate('viewers.tier.3'),
            default => translate('viewers.tier.1'),
        };
    }

    /**
     * Wie lange etwas her ist, grob: "2 Jahre, 3 Monate", "5 Tage".
     *
     * Grob mit Absicht. Ob jemand seit 812 oder 813 Tagen folgt, sagt
     * nichts; ob seit zwei Jahren oder seit zwei Wochen, sagt alles.
     */
    public static function duration(string $seit): string
    {
        try {
            $abstand = (new \DateTimeImmutable($seit))->diff(new \DateTimeImmutable('now'));
        } catch (\Throwable) {
            return '';
        }

        if ($abstand->y > 0) {
            return $abstand->m > 0
                ? translate('viewers.duration.years_months', ['years' => (string) $abstand->y, 'months' => (string) $abstand->m])
                : translate('viewers.duration.years', ['years' => (string) $abstand->y]);
        }

        if ($abstand->m > 0) {
            return translate('viewers.duration.months', ['months' => (string) $abstand->m]);
        }

        return translate('viewers.duration.days', ['days' => (string) $abstand->d]);
    }

    /**
     * Stand des letzten Abgleichs, fuer die Anzeige.
     *
     * Die Follower gleicht der Kern ab - deren Stand steht in seinen
     * Einstellungen.
     *
     * @return array{at: int, error: string}
     */
    public static function syncState(App $app, string $was): array
    {
        if ($was === 'followers') {
            return [
                'at'    => $app->settings->int('followers_full_at', 0),
                'error' => $app->settings->string('followers_error', ''),
            ];
        }

        return [
            'at'    => $app->settings->int($was . '_synced_at', 0, self::scope()),
            'error' => $app->settings->string($was . '_sync_error', '', self::scope()),
        ];
    }
}
