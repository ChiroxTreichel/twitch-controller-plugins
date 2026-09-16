<?php

declare(strict_types=1);

/**
 * Nichts abzuraeumen.
 *
 * Die Einstellungen im Bereich "plugin:throne" loescht der Kern.
 *
 * Die Ereignisse in "events" bleiben ABSICHTLICH stehen: sie gehoeren
 * dem Kanal und nicht diesem Plugin. Wer das Plugin wieder
 * installiert, findet seine Historie vor - und wer sie loeschen will,
 * tut das in der Aktivitaetenliste.
 *
 * @var \TwitchController\Core\App $app
 */
