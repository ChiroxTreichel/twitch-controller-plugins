<?php

declare(strict_types=1);

/**
 * Keine Tabelle. Die Tagliste liegt unter "tags" im Scope
 * "plugin:streaminfo-tags" - den loescht der Kern beim Entfernen des
 * Plugins mit.
 *
 * Welche Tags EINGESCHALTET sind, wird nirgends gespeichert: das steht
 * im Titel bei Twitch, und der ist die einzige Wahrheit. Ein zweiter
 * Ort dafuer waere sofort falsch, sobald jemand den Titel anderswo
 * aendert - im Dashboard, per Handy, durch einen Mod.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
