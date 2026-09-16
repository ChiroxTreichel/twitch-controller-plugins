<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Throne;

use TwitchController\Core\App;
use TwitchController\Plugin\Alerts\Alerts;

/**
 * Was der Alert je Fall zeigt.
 *
 * Drei Faelle mit je eigenem Text, Video, Ton und Dauer - genau wie im
 * alten System. Ein Sammelziel ist etwas anderes als ein Geschenk, und
 * es soll auch anders aussehen duerfen.
 *
 * Alles liegt unter EINEM Einstellungsschluessel: es ist eine Sache,
 * die man zusammen einstellt.
 */
final class Config
{
    public const KEY = 'alert';

    /** Laenger als das passt in keinen Alert. */
    public const MAX_TEXT = 200;

    /**
     * Was im Text vorkommen darf.
     *
     * Dieselben vier wie im alten System.
     *
     * @var list<string>
     */
    public const PLACEHOLDERS = ['username', 'amount', 'item', 'message'];

    /**
     * Die Vorgaben, Zeichen fuer Zeichen aus dem alten System.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'gift'         => translate('throne.default.gift'),
            'contribution' => translate('throne.default.contribution'),
            'crowdfund'    => translate('throne.default.crowdfund'),
        ];
    }

    /**
     * Die Einstellung, auf feste Form gebracht.
     *
     * @return array{enabled: bool, cases: array<string, array{text: string, video: string, audio: string, duration: int}>}
     */
    public static function of(App $app): array
    {
        $gespeichert = $app->settings->get(self::KEY, null, Throne::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $vorgaben = self::defaults();
        $faelle = [];

        foreach (Throne::CASES as $fall) {
            $vorhanden = $gespeichert['cases'][$fall] ?? [];
            $vorhanden = is_array($vorhanden) ? $vorhanden : [];

            $text = trim((string) ($vorhanden['text'] ?? ''));

            $faelle[$fall] = [
                'text'     => $text !== '' ? $text : ($vorgaben[$fall] ?? ''),
                'video'    => trim((string) ($vorhanden['video'] ?? '')),
                'audio'    => trim((string) ($vorhanden['audio'] ?? '')),
                'duration' => self::duration($vorhanden['duration'] ?? null),
            ];
        }

        return [
            // Eingeschaltet, solange niemand es abgeschaltet hat.
            'enabled' => (bool) ($gespeichert['enabled'] ?? true),
            'cases'   => $faelle,
        ];
    }

    /**
     * @param array<string, mixed> $eingaben
     */
    public static function save(App $app, array $eingaben): void
    {
        $faelle = [];

        foreach (Throne::CASES as $fall) {
            $roh = $eingaben[$fall] ?? [];
            $roh = is_array($roh) ? $roh : [];

            $text = trim((string) ($roh['text'] ?? ''));

            if (preg_match('/^.{0,' . self::MAX_TEXT . '}/us', $text, $treffer) === 1) {
                $text = $treffer[0];
            }

            $faelle[$fall] = [
                'text'     => $text,
                'video'    => trim((string) ($roh['video'] ?? '')),
                'audio'    => trim((string) ($roh['audio'] ?? '')),
                'duration' => self::duration($roh['duration'] ?? null),
            ];
        }

        $app->settings->set(self::KEY, [
            // Der Schalter kommt NICHT aus diesem Formular - er hat
            // seinen eigenen Knopf. Ein nicht angehaktes Kaestchen wird
            // gar nicht mitgeschickt, und ein Speichern der Texte
            // schaltete den Alert sonst nebenbei aus.
            'enabled' => self::of($app)['enabled'],
            'cases'   => $faelle,
        ], Throne::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set(self::KEY, ['enabled' => $an] + self::of($app), Throne::scope());
    }

    /**
     * Die Werte fuer die Platzhalter aus einer gespeicherten Zeile.
     *
     * @param array<string, mixed> $event
     * @return array<string, string>
     */
    public static function values(array $event): array
    {
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        $betrag = (string) ($event['amount'] ?? '');
        $waehrung = trim((string) ($event['currency'] ?? ''));

        return [
            'username' => (string) ($event['actor_name'] ?? ''),
            'item'     => trim((string) ($payload['item_name'] ?? '')),
            'message'  => (string) ($event['message'] ?? ''),
            'amount'   => $betrag === ''
                ? ''
                : number_format((float) $betrag, 2, ',', '.') . ($waehrung !== '' ? ' ' . $waehrung : ''),
        ];
    }

    private static function duration(mixed $wert): int
    {
        $zahl = (int) $wert;

        if ($zahl <= 0) {
            return 0;
        }

        return max(1, min(Alerts::MAX_DURATION, $zahl));
    }
}
