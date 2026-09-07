<?php

declare(strict_types=1);

/**
 * Keine Tabelle.
 *
 * Dieses Plugin merkt sich genau eines: wann zuletzt ein Raid
 * gestartet wurde - daran haengt der Abbruch-Knopf. Das ist eine Zahl
 * und steht als Einstellung im Bereich plugin:raids-raid.
 *
 * Wen man raiden kann, weiss Raids; ob ein Raid laeuft, weiss Twitch.
 * Nichts davon gehoert in eine eigene Tabelle.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
