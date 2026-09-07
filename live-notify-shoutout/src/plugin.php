<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Live-Benachrichtigung - Shoutout
 * ===================================================================
 *
 * Loest einen Twitch-Shoutout aus, wenn ein beobachteter Kanal live
 * geht. Ein Ziel wie Discord und Chat, nur ohne Text: Twitch baut den
 * Shoutout selbst und zeigt ihn im Chat als eigenes Element.
 *
 * Es gibt hier also nichts einzustellen - der Haken in der Kanalzeile
 * des Basis-Plugins ist die ganze Bedienung, und darum hat dieses
 * Plugin auch keinen Eintrag unter Plugins > Einstellungen.
 *
 * Ein Unterschied zum alten System, und zwar ein absichtlicher: dort
 * ging der Shoutout nur hinaus, NACHDEM die Chat-Nachricht gelungen
 * war. Das war eine stille Kopplung zweier Dinge, die nichts
 * miteinander zu tun haben - wer nur den Shoutout wollte, bekam keinen,
 * solange kein Chat-Ziel angehakt war. Hier entscheidet jedes Ziel fuer
 * sich.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Plugin\LiveNotify\LiveNotify;
use TwitchController\Plugin\LiveNotifyShoutout\ShoutoutTarget;

// -------------------------------------------------------------------
//  Die Twitch-Freigabe
// -------------------------------------------------------------------
$hooks->on('core.twitch.broadcaster_scopes', static function (array $scopes): array {
    $scopes[] = ShoutoutTarget::SCOPE;

    return $scopes;
});

// Der Kern kennt diese Freigabe mit Namen - "Shoutouts" steht in seinem
// Katalog. Nachzutragen ist hier also nichts.

// -------------------------------------------------------------------
//  Das Ziel
// -------------------------------------------------------------------
$hooks->on('live_notify.targets', static function (array $ziele) use ($app): array {
    $kann = ShoutoutTarget::ready($app);

    $ziele['shoutout'] = [
        'label' => translate('ln_shoutout.target'),
        'order' => 30,
        'ready' => $kann,
        'hint'  => $kann ? '' : translate('ln_shoutout.no_scope_hint'),
    ];

    return $ziele;
});

$hooks->on('live_notify.live', static function (array $info, array $ziele) use ($app): void {
    if (!in_array('shoutout', $ziele, true)) {
        return;
    }

    $login = (string) ($info['login'] ?? '');

    /*
     * Ein Shoutout ist ein Element im laufenden Stream. Ohne Stream
     * lehnt Twitch ihn ab - gefragt wird also vorher, damit im Log
     * "eigener Kanal ist nicht live" steht und nicht eine Ablehnung von
     * Twitch, die man erst nachschlagen muss.
     */
    if (!LiveNotify::broadcasterIsLive($app)) {
        $app->log('LiveNotifyShoutout: Shoutout fuer ' . $login . ' uebersprungen, eigener Kanal ist nicht live.');

        return;
    }

    // Die Twitch-Id des Ziels kommt aus der Live-Abfrage mit - der
    // Aufruf kennt nur Ids, keine Logins.
    $ergebnis = ShoutoutTarget::send($app, (string) ($info['user_id'] ?? ''));

    if ($ergebnis['ok']) {
        $app->log('LiveNotifyShoutout: Shoutout fuer ' . $login . ' gesendet.');

        return;
    }

    $app->log('LiveNotifyShoutout: Shoutout fuer ' . $login . ' gescheitert: ' . $ergebnis['error']);
});
