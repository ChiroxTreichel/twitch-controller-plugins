<?php

declare(strict_types=1);

/**
 * Chatbefehle legt keine Tabelle an.
 *
 * Die Grundbefehle liegen als "builtin.<name>" im Scope
 * "plugin:chat-commands", die eigenen Befehle gesammelt unter
 * "custom". Den Scope loescht der Kern beim Entfernen des Plugins mit.
 *
 * Vorgaben stehen in src/Commands.php und greifen beim ersten Lesen -
 * nach der Installation ist alles benutzbar, ohne dass eine Zeile in
 * der Datenbank steht. Nur der Anmeldelink fuer !discord fehlt, und
 * den kann niemand erraten.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
