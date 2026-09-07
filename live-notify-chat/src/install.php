<?php

declare(strict_types=1);

/**
 * Keine Tabelle. Die Nachrichtenvorlage liegt unter "message" im Scope
 * "plugin:live-notify-chat" - den loescht der Kern beim Entfernen des
 * Plugins mit.
 *
 * Welche Kanaele dieses Ziel benutzen, steht NICHT hier: das ist der
 * Haken in der Kanalzeile des Basis-Plugins. Ein zweiter Ort dafuer
 * waere sofort uneinig mit dem ersten.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
