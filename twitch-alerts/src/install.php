<?php

declare(strict_types=1);

/**
 * Twitch-Alerts legt keine Tabelle an.
 *
 * Die Einstellungen liegen im Scope "plugin:twitch-alerts", einen
 * Eintrag je Alert-Typ. Den Scope loescht der Kern beim Entfernen des
 * Plugins mit.
 *
 * Die Vorgaben - Texte, eine Stufe ab 1 - stehen in src/Types.php und
 * greifen beim ersten Lesen. Deshalb muss hier nichts geschrieben
 * werden: nach der Installation ist alles eingestellt, ohne dass eine
 * Zeile in der Datenbank steht.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
