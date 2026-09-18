<?php

declare(strict_types=1);

/**
 * Beim Entfernen bleibt bei Twitch alles stehen.
 *
 * Das ist Absicht: die Belohnungen gehoeren dem Kanal, nicht diesem
 * Plugin. Wer es entfernt, will die Automatik los sein - nicht seine
 * Kanalpunkte. Was verschwindet, sind die Bedingungen, und die haben
 * ohne das Plugin ohnehin keine Wirkung.
 *
 * Eine Belohnung, die zuletzt ausgeschaltet war, bleibt
 * ausgeschaltet. Sie laesst sich im Creator-Dashboard wieder
 * einschalten.
 *
 * @var \TwitchController\Core\Database\Db $db
 */
