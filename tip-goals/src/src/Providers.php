<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TipGoals;

use TwitchController\Core\App;

/**
 * ===================================================================
 *  Die Zahlungsanbieter
 * ===================================================================
 *
 * Dieses Plugin kennt keinen einzigen. Es haelt die Seite, die Ziele
 * und die Spenden - wie das Geld fliesst, bringt ein eigenes Plugin
 * mit, das sich hier anmeldet:
 *
 *   $hooks->on('tips.providers', static function (array $anbieter) use ($app): array {
 *       $anbieter['paypal'] = [
 *           'label'       => 'PayPal',
 *           'ready'       => PayPal::hasCredentials($app),
 *           'fee_percent' => 2.99,
 *           'fee_fixed'   => 0.39,
 *           'settings'    => '/display/goals/tips/paypal',
 *           'start'       => static fn (array $spende): Response => ...,
 *       ];
 *
 *       return $anbieter;
 *   });
 *
 * label        der Name, den der Spender sieht
 * ready        kann gerade angenommen werden? (Zugang hinterlegt usw.)
 * fee_percent  Gebuehr fuer den Vorschlag "Gebuehren uebernehmen"
 * fee_fixed    dito, fester Teil in Euro
 * settings     wo der Anbieter eingerichtet wird (optional)
 * start        schickt den Spender los: bekommt die vorgemerkte Spende
 *              und gibt eine Antwort zurueck - meist eine Weiterleitung.
 *              Scheitert es, wirft er; die Spende wird dann verworfen.
 *
 * Zurueck kommt der Spender ueber eine Route des Anbieters. Die liegt
 * unter /tips/<schluessel>/..., also mit ZWEI Abschnitten: /tips/{page}
 * faengt genau einen und wuerde eine Adresse wie /tips/return sonst
 * selbst beantworten. Dort bucht der Anbieter mit Donations::complete().
 *
 * Kein Anbieter geladen: die Seite bleibt zu, wie ohne Impressum.
 * Einer: er wird genommen. Mehrere: der Spender waehlt.
 */
final class Providers
{
    /**
     * Alle angemeldeten Anbieter.
     *
     * Was sich unvollstaendig anmeldet, faellt heraus - ein Anbieter
     * ohne start() waere ein Knopf, hinter dem nichts passiert.
     *
     * @return array<string, array{key: string, label: string, ready: bool, fee_percent: float, fee_fixed: float, settings: string, start: callable}>
     */
    public static function all(App $app): array
    {
        $roh = $app->hooks->filter('tips.providers', []);
        $anbieter = [];

        foreach (is_array($roh) ? $roh : [] as $schluessel => $eintrag) {
            $schluessel = strtolower(trim((string) $schluessel));

            if (preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $schluessel) !== 1
                || !is_array($eintrag)
                || !is_callable($eintrag['start'] ?? null)
            ) {
                continue;
            }

            $label = trim((string) ($eintrag['label'] ?? ''));

            $anbieter[$schluessel] = [
                'key'         => $schluessel,
                'label'       => $label !== '' ? $label : $schluessel,
                'ready'       => (bool) ($eintrag['ready'] ?? false),
                'fee_percent' => max(0.0, (float) ($eintrag['fee_percent'] ?? 0)),
                'fee_fixed'   => max(0.0, (float) ($eintrag['fee_fixed'] ?? 0)),
                'settings'    => (string) ($eintrag['settings'] ?? ''),
                'start'       => $eintrag['start'],
            ];
        }

        return $anbieter;
    }

    /**
     * Die, bei denen gerade gezahlt werden kann.
     *
     * @return array<string, array{key: string, label: string, ready: bool, fee_percent: float, fee_fixed: float, settings: string, start: callable}>
     */
    public static function ready(App $app): array
    {
        return array_filter(self::all($app), static fn (array $eintrag): bool => $eintrag['ready']);
    }

    /** Der Name eines Anbieters - auch eines, der inzwischen fehlt. */
    public static function label(App $app, string $schluessel): string
    {
        return self::all($app)[$schluessel]['label'] ?? $schluessel;
    }
}
