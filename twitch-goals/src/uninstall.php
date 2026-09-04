<?php

declare(strict_types=1);

/**
 * Nichts abzuraeumen: keine Tabelle, und die Einstellungen loescht der
 * Kern mit dem Scope "plugin:twitch-goals".
 *
 * Die Abos channel.goal.* bleiben bei Twitch stehen, bis der naechste
 * Abgleich unter Konto > Einstellungen > Kanal laeuft - der Kern
 * raeumt dort auf, was kein Plugin mehr anfordert.
 *
 * @var \TwitchController\Core\Database\Db $db
 */
