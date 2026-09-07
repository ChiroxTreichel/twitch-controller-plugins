<?php

declare(strict_types=1);

/**
 * Nichts abzuraeumen: keine Tabelle, und die Vorlage loescht der Kern
 * mit dem Scope.
 *
 * Der Haken in der Kanalzeile bleibt stehen und wird nur nicht mehr
 * ausgelesen - er gehoert dem Basis-Plugin. Wird dieses Plugin wieder
 * installiert, wirkt er sofort wieder.
 *
 * @var \TwitchController\Core\Database\Db $db
 */
