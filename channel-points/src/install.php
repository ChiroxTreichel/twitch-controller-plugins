<?php

declare(strict_types=1);

/**
 * Kanalpunkte legt keine Tabelle an.
 *
 * Die Belohnungen liegen unter "rewards" im Scope
 * "plugin:channel-points", der Stream-Zustand unter "stream". Den
 * Scope loescht der Kern beim Entfernen des Plugins mit.
 *
 * Nach der Installation ist die Liste leer. Der erste Schritt ist der
 * Knopf "Kanalpunktbelohnungen laden": er holt, was im Kanal schon
 * existiert. Raten laesst sich das nicht.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
