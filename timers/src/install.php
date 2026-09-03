<?php

declare(strict_types=1);

/**
 * Timer legt keine Tabelle an.
 *
 * Die Timer liegen unter "timers" im Scope "plugin:timers", der
 * Laufzeitstand unter "state" und der Stream-Zustand unter "stream".
 * Den Scope loescht der Kern beim Entfernen des Plugins mit.
 *
 * Nach der Installation ist die Liste leer - einen ersten Timer muss
 * man anlegen, raten laesst sich das nicht.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
