<?php

declare(strict_types=1);

/**
 * Zwei Tabellen: die Bereiche je Kategorie, und je Kategorie ein
 * Screenshot als Hilfe im Editor.
 *
 * Bereiche in einer Tabelle und nicht in den Einstellungen: es sind
 * beliebig viele Kategorien mit beliebig vielen Bereichen, und
 * gebraucht werden immer nur die einer einzigen - der laufenden. Eine
 * Liste in einer Einstellung muesste jedes Mal ganz gelesen werden.
 *
 * Der Screenshot liegt in der Datenbank und nicht unter
 * public/uploads: er ist nur fuer den Editor da, gehoert niemandem
 * draussen und geht mit dem Plugin. Unter public/ waere er ohne
 * Anmeldung abrufbar, und der Upload des Kerns nimmt ohnehin nur
 * Video und Ton an.
 *
 * Der Name der Kategorie steht beim Screenshot noch einmal: eine
 * Kategorie, deren Bereiche alle geloescht sind, soll mit ihrem Bild
 * in der Liste bleiben - und dort braucht sie einen Namen.
 *
 * Ob das Plugin an ist, liegt im Scope "plugin:blur-chats".
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var string|null $fromVersion
 */

$db->run('
    CREATE TABLE IF NOT EXISTS blur_chats_regions (
        id            BIGSERIAL   PRIMARY KEY,
        category_id   TEXT        NOT NULL,
        category_name TEXT        NOT NULL,
        label         TEXT        NOT NULL DEFAULT \'\',
        x             INTEGER     NOT NULL DEFAULT 0,
        y             INTEGER     NOT NULL DEFAULT 0,
        w             INTEGER     NOT NULL DEFAULT 400,
        h             INTEGER     NOT NULL DEFAULT 200,
        color         VARCHAR(7)  NOT NULL DEFAULT \'#1e1e24\',
        opacity       SMALLINT    NOT NULL DEFAULT 90,
        feather       SMALLINT    NOT NULL DEFAULT 24,
        radius        SMALLINT    NOT NULL DEFAULT 12,
        sort          INTEGER     NOT NULL DEFAULT 0
    )
');

$db->run('CREATE INDEX IF NOT EXISTS blur_chats_regions_category ON blur_chats_regions (category_id)');

$db->run('
    CREATE TABLE IF NOT EXISTS blur_chats_screens (
        category_id   TEXT        PRIMARY KEY,
        category_name TEXT        NOT NULL DEFAULT \'\',
        image         BYTEA       NOT NULL,
        updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
    )
');
