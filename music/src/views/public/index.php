<?php
/**
 * Die oeffentliche Musikseite.
 *
 * Aufbau Zeile fuer Zeile aus dem alten musik.talutah.de
 * (public/index.php):
 *
 *   zwei Spalten   links die Songwuensche, rechts die Warteschlange
 *   links          Reiter: Favoriten | Suche | Manuell eintragen
 *   rechts         Laeuft gerade / Zuletzt gespielt / Als Naechstes,
 *                  jede Zeile mit "Favorit", der laufende zusaetzlich
 *                  mit "Bannen"
 *
 * Auch die Texte sind die von dort. Geaendert sind nur die Farben.
 *
 * Die Suche ist der wichtigste Reiter: mit ihr kommt auch jemand
 * zurecht, der selbst kein Spotify hat und deshalb keinen Teilen-Link
 * kopieren kann.
 *
 * @var callable $e
 * @var callable $url
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var array{ok: bool, reason: string, until: int} $may
 * @var bool $enabled
 * @var bool $connected
 * @var bool $banned
 * @var bool $canBan
 * @var bool $accepted
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

/*
 * Der laufende Titel laesst sich sperren, ohne die Seite zu wechseln -
 * im alten System der Knopf "Bannen" neben "Laeuft gerade", dort an
 * zwei fest eingetragene Twitch-Kennungen gebunden, hier an ein Recht.
 */
$konfiguration = [
    'urls' => [
        'queue'     => $url('/music/queue'),
        'search'    => $url('/music/search'),
        'favorites' => $url('/music/favorites'),
        'ban'       => $url('/music/ban'),
    ],
    'csrf'         => $csrf,
    'allowAdding'  => $may['ok'],
    'isUserBanned' => $banned,

    /*
     * Merken kann nur, wer angemeldet ist - sonst gehoert die Liste
     * niemandem. Das alte System zeigte den Knopf auch dem Gast und
     * antwortete auf den Klick mit "Unauthorized".
     */
    'canFavorite'  => $identity !== null,
    'canBan'       => $canBan,
    'texts'        => [
        'now'            => translate('music.public.now'),
        'recent'         => translate('music.public.recent'),
        'next'           => translate('music.public.next'),
        'wishedBy'       => translate('music.public.wished_by'),
        'queueEmpty'     => translate('music.public.queue_empty'),
        'queueActive'    => translate('music.public.queue_active'),
        'queuePaused'    => translate('music.public.queue_paused'),
        'choose'         => translate('music.public.choose'),
        'favorite'       => translate('music.public.favorite'),
        'send'           => translate('music.public.send'),
        'delete'         => translate('music.public.delete'),
        'ban'            => translate('music.public.ban'),
        'banConfirm'     => translate('music.public.ban_confirm'),
        'banConfirmAny'  => translate('music.public.ban_confirm_any'),
        'banned'         => translate('music.public.ban_done'),
        'banFailed'      => translate('music.public.ban_failed'),
        'searchEmpty'    => translate('music.public.search_empty'),
        'searchFailed'   => translate('music.public.search_failed'),
        'favoritesEmpty' => translate('music.public.favorites_empty'),
        'favoritesError' => translate('music.public.favorites_failed'),
    ],
];
?>
<div class="layout">
    <div class="layout-left">
        <div class="card">
            <div class="card-header">
                <h2>🎵 <?= $e(translate('music.public.title')) ?></h2>
                <?php if (!$enabled): ?>
                    <span class="status-badge"><?= $e(translate('music.public.paused_badge')) ?></span>
                <?php endif ?>
            </div>

            <?php /*
                Das alte System kannte diesen Fall nicht - dort lag das
                Spotify-Token als Datei daneben und war einfach da.
                Hier kann die Verbindung fehlen, und dann kommt kein
                Wunsch an, egal was man tut. Das gehoert gesagt.
            */ ?>
            <?php if (!$connected): ?>
                <div class="alert alert-warning"><?= $e(translate('music.public.not_connected')) ?></div>
            <?php endif ?>

            <?php if ($identity === null): ?>
                <p><?= $e(translate('music.public.login_lead')) ?></p>
                <a id="twitch_login" href="<?= $e($url('/music/login')) ?>">
                    <?= $e(translate('music.public.login')) ?>
                </a>
            <?php else: ?>
                <?php if ($banned): ?>
                    <div class="alert alert-danger">🚫 <?= $e(translate('music.public.banned_alert')) ?></div>
                <?php endif ?>
                <?php if ($notice !== ''): ?>
                    <div class="alert alert-info">✅ <?= $e($notice) ?></div>
                <?php endif ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">❌ <?= $e($error) ?></div>
                <?php endif ?>

                <div class="tabs" data-tab-group="wish-tabs">
                    <div class="tab-buttons pills">
                        <button class="tab-button active" data-tab="favorites"><?= $e(translate('music.public.tab_favorites')) ?></button>
                        <button class="tab-button" data-tab="search"><?= $e(translate('music.public.tab_search')) ?></button>
                        <button class="tab-button" data-tab="manual"><?= $e(translate('music.public.tab_manual')) ?></button>
                    </div>

                    <div class="tab-panels">
                        <section class="tab-panel active" data-tab-panel="favorites">
                            <p class="muted"><?= $e(translate('music.public.favorites_lead')) ?></p>
                            <div id="favorites" class="media-list"></div>
                        </section>

                        <section class="tab-panel" data-tab-panel="search">
                            <div class="search-section">
                                <label for="spotify_search"><?= $e(translate('music.public.search_label')) ?></label>
                                <input type="text" id="spotify_search"
                                       placeholder="<?= $e(translate('music.public.search_placeholder')) ?>"
                                       autocomplete="off">
                            </div>
                            <div id="spotify_results" class="media-results"></div>
                        </section>

                        <section class="tab-panel" data-tab-panel="manual">
                            <?php if (!$accepted): ?>
                                <div class="alert alert-warning"><?= $e(translate('music.public.rules_first')) ?></div>
                            <?php elseif ($banned): ?>
                                <div class="alert alert-warning"><?= $e(translate('music.public.no_wishes')) ?></div>
                            <?php elseif ($may['ok']): ?>
                                <form method="post" action="<?= $e($url('/music')) ?>" id="wish_form" class="wish-form">
                                    <label for="spotify_link"><?= $e(translate('music.public.link')) ?></label>
                                    <div class="input-row">
                                        <input type="text" id="spotify_link" name="link" required
                                               placeholder="https://open.spotify.com/track/..."
                                               autocomplete="off">
                                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="action" value="wish">
                                        <button type="submit" class="primary"><?= $e(translate('music.public.submit')) ?></button>
                                    </div>
                                </form>
                            <?php elseif ($may['reason'] === 'cooldown'): ?>
                                <?php /*
                                    Die Zahl steht in einem eigenen
                                    Element, das music.js herunterzaehlt
                                    - genau wie im alten System, nur
                                    ohne Skript mitten in der Seite.
                                */ ?>
                                <p><?= str_replace(
                                    '%{time}',
                                    '<span id="wishtimer" data-time="' . (int) $may['until'] . '"></span>',
                                    $e(translate('music.public.wait'))
                                ) ?></p>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <?= $e(\TwitchController\Plugin\Music\Texts::denied($may['reason'])) ?>
                                </div>
                            <?php endif ?>
                        </section>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="layout-right">
        <div id="queuediv" class="card queue-card">
            <div class="card-header">
                <h2>🔊 <?= $e(translate('music.public.queue')) ?></h2>
                <span class="status-badge status-inline" id="queue_status"></span>
            </div>
            <div id="queue" class="queue-list"></div>
        </div>
    </div>
</div>

<?php /*
    Adressen und Texte als Daten, nicht als Skript: ein
    application/json-Block wird nicht ausgefuehrt, und music.js liest
    ihn beim Start. Das alte System hatte die Adressen fest im Skript
    stehen ("api.php?a=queue") - hier haengen sie am Unterverzeichnis,
    unter dem das System laeuft.
*/ ?>
<script type="application/json" id="music_config"><?= json_encode(
    $konfiguration,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
