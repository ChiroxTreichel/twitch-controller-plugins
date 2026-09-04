<?php

declare(strict_types=1);

/**
 * Twitch-Goals legt keine Tabelle an.
 *
 * Titel, Geruest und der letzte bekannte Stand liegen im Scope
 * "plugin:twitch-goals", den loescht der Kern beim Entfernen mit.
 *
 * Die Vorgabe fuers Aussehen steht in src/Config.php und greift beim
 * ersten Lesen: nach der Installation sehen die Ziele aus wie im alten
 * System, ohne dass eine Zeile in der Datenbank steht.
 *
 * Die Zahlen kommen beim ersten Takt des Hintergrundprozesses - oder
 * sofort ueber "Jetzt abrufen".
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
