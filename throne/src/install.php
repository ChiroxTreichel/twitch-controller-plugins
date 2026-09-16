<?php

declare(strict_types=1);

/**
 * Keine eigene Tabelle.
 *
 * Die Ereignisse liegen in der Tabelle "events" des Kerns - dort, wo
 * auch die von Twitch liegen. Genau dafuer hat sie eine Spalte
 * "source": der Feed liest sie gemeinsam, filtert gemeinsam und
 * blaettert gemeinsam.
 *
 * Eine eigene Tabelle hiesse, all das ein zweites Mal zu bauen.
 *
 * Was dieses Plugin ablegt, liegt im Bereich "plugin:throne": der
 * oeffentliche Schluessel und die Alert-Texte. Den Bereich raeumt der
 * Kern beim Entfernen des Plugins mit ab.
 *
 * @var \TwitchController\Core\App $app
 */
