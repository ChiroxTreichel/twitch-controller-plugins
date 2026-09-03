<?php

declare(strict_types=1);

/**
 * Loeschbot legt keine Tabelle an.
 *
 * Die Musterliste liegt unter "words" im Scope "plugin:delete-bot",
 * der Hauptschalter unter "enabled". Den Scope loescht der Kern beim
 * Entfernen des Plugins mit.
 *
 * Nach der Installation ist die Liste leer und der Schalter AUS - ein
 * Werkzeug, das ungefragt anfaengt, fremde Nachrichten zu loeschen,
 * waere eine unangenehme Ueberraschung.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
