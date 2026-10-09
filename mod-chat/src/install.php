<?php

declare(strict_types=1);

/**
 * Eine Tabelle fuer die Nachrichten.
 *
 * Der Name steht in jeder Zeile mit und wird nicht ueber users
 * nachgeschlagen: wer das Team verlaesst, verschwindet aus users, und
 * seine Nachrichten stuenden danach ohne Absender da - mitten in
 * einem Verlauf, den andere noch lesen.
 *
 * Der Index auf created_at ist fuer das Abraeumen im Worker. Das
 * laeuft alle paar Sekunden und soll nicht jedes Mal die ganze
 * Tabelle lesen.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run('
    CREATE TABLE IF NOT EXISTS mod_chat_messages (
        id           BIGSERIAL   PRIMARY KEY,
        twitch_id    TEXT        NOT NULL,
        display_name TEXT        NOT NULL,
        text         TEXT        NOT NULL,
        created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
    )
');

$db->run('CREATE INDEX IF NOT EXISTS mod_chat_messages_created_at ON mod_chat_messages (created_at)');
