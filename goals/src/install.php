<?php

declare(strict_types=1);

/**
 * Goals legt keine Tabelle an.
 *
 * Groesse und Lage der Flaeche liegen im Scope "plugin:goals", den
 * loescht der Kern beim Entfernen des Plugins mit. Die Vorgaben stehen
 * in src/Goals.php und greifen beim ersten Lesen - nach der
 * Installation ist die Flaeche also schon richtig eingestellt.
 *
 * Fuer die Ziele selbst braucht es ein Ziel-Plugin. Ohne eines steht
 * auf der Seite ein Hinweis, wo man eines herbekommt.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
