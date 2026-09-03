<?php

declare(strict_types=1);

/**
 * Alerts legt keine Tabelle an.
 *
 * Die Einstellungen liegen im Scope "plugin:alerts" - der wird beim
 * Entfernen des Plugins vom Kern mitgeloescht. Und die Alerts selbst
 * sind fluechtig: sie gehen ueber overlay_messages, und die raeumt der
 * Bus des Kerns von allein auf.
 *
 * Diese Datei bleibt trotzdem: sie muss vorhanden sein, und beim
 * naechsten Update steht hier vielleicht etwas.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion  Bisherige Fassung, null bei der Erstinstallation
 */
