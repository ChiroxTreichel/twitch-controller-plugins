<?php

declare(strict_types=1);

/**
 * Wer folgt, steht im Kern (twitch_followers) - der fuehrt die Liste,
 * weil er an ihr die Unfollows erkennt. Hier stehen nur:
 *
 *   viewers_subs       wer ein Abo hat oder hatte, mit errechnetem Ende
 *   viewers_unfollows  wer nicht mehr folgt - nur wenn freigeschaltet
 *
 * Idempotent: laeuft bei jedem Versionswechsel.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var \TwitchController\Core\Config\Settings $settings
 * @var string|null $fromVersion
 */

/*
 * Ehemalige Follower. Eine Zeile entsteht nur, solange "Ehemalige
 * Follower erfassen" freigeschaltet ist, und verschwindet, wenn
 * dieselbe Person wieder folgt.
 */
$db->run('
    CREATE TABLE IF NOT EXISTS viewers_unfollows (
        user_id       VARCHAR(32)  PRIMARY KEY,
        login         VARCHAR(64)  NOT NULL,
        name          VARCHAR(255) NOT NULL,
        followed_at   TIMESTAMPTZ,
        unfollowed_at TIMESTAMPTZ  NOT NULL
    )
');
$db->run('CREATE INDEX IF NOT EXISTS viewers_unfollows_when_idx ON viewers_unfollows (unfollowed_at DESC)');
$db->run('CREATE INDEX IF NOT EXISTS viewers_unfollows_login_idx ON viewers_unfollows (lower(login))');

/*
 * active = false heisst: hatte ein Abo, hat keines mehr. Solche Zeilen
 * entstehen nur, solange "Ehemalige erfassen" freigeschaltet ist -
 * sonst wird eine auslaufende Zeile geloescht.
 *
 * ends_estimated: das Ende ist fortgeschrieben, nicht aus einem
 * Ereignis gelesen. Ein Abo, das sich still verlaengert, meldet
 * Twitch nicht - dann steht hier "voraussichtlich".
 */
$db->run('
    CREATE TABLE IF NOT EXISTS viewers_subs (
        user_id        VARCHAR(32)  PRIMARY KEY,
        login          VARCHAR(64)  NOT NULL,
        name           VARCHAR(255) NOT NULL,
        tier           VARCHAR(8)   NOT NULL DEFAULT \'1000\',
        is_gift        BOOLEAN      NOT NULL DEFAULT false,
        gifter_name    VARCHAR(255) NOT NULL DEFAULT \'\',
        ends_at        TIMESTAMPTZ,
        ends_estimated BOOLEAN      NOT NULL DEFAULT false,
        active         BOOLEAN      NOT NULL DEFAULT true,
        ended_at       TIMESTAMPTZ,
        seen_at        TIMESTAMPTZ  NOT NULL DEFAULT now(),
        updated_at     TIMESTAMPTZ  NOT NULL DEFAULT now()
    )
');
$db->run('CREATE INDEX IF NOT EXISTS viewers_subs_active_idx ON viewers_subs (active, ended_at DESC)');

/*
 * 1.1.1: Bis dahin wurde duration_months als "im Voraus bezahlte
 * Monate" gelesen - es zaehlt aber hoch, und Enden lagen im Jahr 2029.
 * Alle Enden verwerfen; der naechste Abgleich errechnet sie neu aus
 * den Ereignissen. Der Merker sorgt dafuer, dass er gleich laeuft und
 * nicht erst in zehn Minuten.
 */
if ($fromVersion !== null && version_compare($fromVersion, '1.1.1', '<')) {
    $db->run('UPDATE viewers_subs SET ends_at = NULL, ends_estimated = false WHERE active');
    $settings->forget('subs_synced_at', \TwitchController\Core\Config\Settings::pluginScope('viewers'));
}
$db->run('CREATE INDEX IF NOT EXISTS viewers_subs_login_idx ON viewers_subs (lower(login))');
