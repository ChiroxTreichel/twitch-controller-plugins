<?php

declare(strict_types=1);

namespace TwitchController\Plugin\StreamelementsTipGoals;

use TwitchController\Core\App;
use TwitchController\Plugin\Alerts\Alerts;

/**
 * ===================================================================
 *  Der Alert zur Spende
 * ===================================================================
 *
 * Bis hierher konnte eine Spende zwar einen Alert ausloesen, aber
 * niemand konnte ihn einstellen: es gab keinen Reiter. Der Text stand
 * als Vorgabe im Code, Video, Ton und Dauer gab es gar nicht.
 *
 * Im alten System hiess dieser Reiter "Spende" und kannte die
 * Platzhalter {{ amount }} und {{ message }}. Hier kommt {{ name }}
 * dazu - anonym gespendet wird ueber ein Haekchen, und dann steht dort
 * ohnehin kein Name.
 *
 * Alles liegt unter EINEM Einstellungsschluessel und nicht unter
 * fuenfen: es ist eine Sache, die man zusammen einstellt, und ein
 * halber Alert - Text ohne Dauer - ergibt keinen Sinn.
 */
final class TipAlert
{
    /** Der Schluessel in den Einstellungen. */
    public const KEY = 'alert';

    /**
     * Was im Text vorkommen darf.
     *
     * Steht hier und nicht in der Vorlage: die Pruefsammlung liest es
     * mit, und der Reiter zeigt genau das an, was auch wirklich
     * eingesetzt wird.
     *
     * @var list<string>
     */
    public const PLACEHOLDERS = ['name', 'amount', 'message'];

    /** Laenger als das passt in keinen Alert. */
    public const MAX_TEXT = 200;

    /**
     * Die Einstellung, auf feste Form gebracht.
     *
     * @return array{enabled: bool, text: string, video: string, audio: string, duration: int}
     */
    public static function config(App $app): array
    {
        $gespeichert = $app->settings->get(self::KEY, null, TipGoals::scope());
        $gespeichert = is_array($gespeichert) ? $gespeichert : [];

        $text = trim((string) ($gespeichert['text'] ?? ''));

        return [
            // Eingeschaltet, solange niemand es abgeschaltet hat: wer
            // das Plugin installiert, will den Alert.
            'enabled'  => (bool) ($gespeichert['enabled'] ?? true),
            'text'     => $text !== '' ? $text : translate('se_tip.alert_default'),
            'video'    => trim((string) ($gespeichert['video'] ?? '')),
            'audio'    => trim((string) ($gespeichert['audio'] ?? '')),
            'duration' => self::duration($gespeichert['duration'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $eingaben
     */
    public static function save(App $app, array $eingaben): void
    {
        $text = trim((string) ($eingaben['text'] ?? ''));

        if (preg_match('/^.{0,' . self::MAX_TEXT . '}/us', $text, $treffer) === 1) {
            $text = $treffer[0];
        }

        $app->settings->set(self::KEY, [
            // Der Schalter kommt NICHT aus diesem Formular - er hat
            // seinen eigenen Knopf. Wuerde er hier mitgeschrieben,
            // schaltete ein Speichern ihn nebenbei aus, weil ein nicht
            // angehaktes Kaestchen gar nicht erst mitgeschickt wird.
            'enabled'  => self::config($app)['enabled'],
            'text'     => $text,
            'video'    => trim((string) ($eingaben['video'] ?? '')),
            'audio'    => trim((string) ($eingaben['audio'] ?? '')),
            'duration' => self::duration($eingaben['duration'] ?? null),
        ], TipGoals::scope());
    }

    public static function setEnabled(App $app, bool $an): void
    {
        $app->settings->set(self::KEY, ['enabled' => $an] + self::config($app), TipGoals::scope());
    }

    /**
     * Den Alert ausloesen.
     *
     * Text und Werte bleiben getrennt: Alerts setzt sie selbst ein und
     * maskiert dabei, was der Spender geschrieben hat. Fertig
     * zusammengebautes HTML hineinzureichen waere ein Weg, ueber eine
     * Spendennachricht Markup ins Overlay zu bekommen.
     *
     * @param array<string, mixed> $spende
     */
    public static function fire(App $app, array $spende): bool
    {
        if (!$app->plugins->isEnabled('alerts')) {
            return false;
        }

        $config = self::config($app);

        if (!$config['enabled']) {
            return false;
        }

        return Alerts::send($app, [
            'kind'     => 'tip',
            'text'     => $config['text'],
            'video'    => $config['video'],
            'audio'    => $config['audio'],
            'duration' => $config['duration'],
            'values'   => self::values($spende),
        ]);
    }

    /**
     * Die Werte fuer die Platzhalter.
     *
     * @param array<string, mixed> $spende
     * @return array<string, string>
     */
    public static function values(array $spende): array
    {
        return [
            'name'    => (string) ($spende['name'] ?? ''),
            'amount'  => number_format((float) ($spende['amount'] ?? 0), 2, ',', '.') . ' €',
            'message' => (string) ($spende['message'] ?? ''),
        ];
    }

    /**
     * Die Dauer in Sekunden - 0 heisst "die Vorgabe von Alerts".
     *
     * Begrenzt wird hier UND in Alerts::send(). Doppelt ist hier
     * richtig: der Wert steht danach in den Einstellungen und wird im
     * Formular wieder angezeigt.
     */
    private static function duration(mixed $wert): int
    {
        $zahl = (int) $wert;

        if ($zahl <= 0) {
            return 0;
        }

        return max(1, min(Alerts::MAX_DURATION, $zahl));
    }
}
