<?php

declare(strict_types=1);

/**
 * Streaminfo legt keine Tabelle an.
 *
 * Es haelt ueberhaupt keinen Zustand: Titel und Kategorie stehen bei
 * Twitch, und diese Seite zeigt und aendert sie dort. Gespeichert wird
 * hier nur die Liste der vordefinierten Titel, unter "presets" im
 * Scope "plugin:streaminfo" - den loescht der Kern beim Entfernen des
 * Plugins mit.
 *
 * Bewusst KEIN Zwischenspeicher fuer den aktuellen Titel: er kann sich
 * jederzeit anderswo aendern - im Twitch-Dashboard, per Handy, durch
 * einen Mod. Ein gespeicherter Wert waere dann falsch, und falsch ist
 * hier schlimmer als langsam.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */
