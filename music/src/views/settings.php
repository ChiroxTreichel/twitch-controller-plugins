<?php
/**
 * Die Einstellungen des Musik-Plugins.
 *
 * Im alten System war das die rechte Haelfte von public/admin/index.php:
 * ein Auswahlfeld "Songwuensche erlauben", ein Zahlenfeld fuer die
 * Abkuehlzeit, und darueber ein Knopf "Bei Spotify anmelden". Die
 * Regeln standen gar nicht hier - sie standen als fuenf <li> im HTML
 * der oeffentlichen Seite.
 *
 * Vier Kaesten, in der Reihenfolge, in der man sie braucht: erst
 * Spotify verbinden, dann die Wuensche einstellen, dann die Regeln,
 * dann die Groesse im Overlay.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $enabled
 * @var int $cooldown
 * @var string $rules
 * @var int $width
 * @var int $height
 * @var int $offsetX
 * @var int $offsetY
 * @var string $theme
 * @var bool $hasTimers
 * @var array{enabled: bool, interval: int, lines: int, on: string, off: string} $timer
 * @var string $clientId
 * @var bool $hasSecret
 * @var bool $hasCreds
 * @var bool $connected
 * @var string $account
 * @var string $redirectUri
 * @var string $publicUrl
 * @var string $panelUrl
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */
?>
<h1><?= $e(translate('music.settings')) ?></h1>
<p class="lead"><?= $e(translate('music.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /* ---------------------------------------------------------- */ ?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('music.spotify')) ?></h2>
        <?php if ($connected): ?>
            <span class="badge badge-ok"><?= $e(translate('music.connected_badge')) ?></span>
        <?php else: ?>
            <span class="badge badge-off"><?= $e(translate('music.not_connected_badge')) ?></span>
        <?php endif ?>
    </div>

    <p class="hint"><?= $e(translate('music.spotify_hint')) ?></p>

    <?php /*
        Die Rueckkehradresse wird gebaut und nicht eingetippt: Spotify
        vergleicht sie Zeichen fuer Zeichen mit der im
        Entwicklerkonto, und eine abgetippte Adresse ist ein Fehler,
        den man erst beim Anmelden bemerkt.
    */ ?>
    <p class="hint">
        <?= $e(translate('music.redirect_uri')) ?>
        <span class="mono" style="word-break:break-all;"><?= $e($redirectUri) ?></span>
    </p>

    <form method="post" action="<?= $e($url('/display/music/settings')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="credentials">

        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('music.client_id')) ?></span>
                <input class="input" type="text" name="client_id" value="<?= $e($clientId) ?>"
                       autocomplete="off" <?= $canEdit ? '' : 'readonly' ?>>
            </label>

            <label class="field grow">
                <span class="hint"><?= $e(translate('music.client_secret')) ?></span>
                <?php /*
                    Leer heisst "nicht aendern". Sonst wuerfe ein
                    Speichern der Kennung nebenbei das Geheimnis weg -
                    angezeigt wird es nie wieder.
                */ ?>
                <input class="input" type="password" name="client_secret" value=""
                       autocomplete="new-password"
                       placeholder="<?= $e($hasSecret ? translate('music.secret_set') : translate('music.secret_empty')) ?>"
                    <?= $canEdit ? '' : 'readonly' ?>>
            </label>
        </div>

        <?php if ($canEdit): ?>
            <div class="row">
                <button class="btn btn-small" type="submit"><?= $e(translate('common.save')) ?></button>
            </div>
        <?php endif ?>
    </form>

    <?php if ($canEdit): ?>
        <div class="row" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line);">
            <?php if ($connected): ?>
                <span class="hint grow">
                    <?= $e($account !== ''
                        ? translate('music.connected_as', ['name' => $account])
                        : translate('music.connected_badge')) ?>
                </span>

                <form method="post" action="<?= $e($url('/display/music/settings')) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="connect">
                    <button class="btn btn-ghost btn-small" type="submit">
                        <?= $e(translate('music.reconnect')) ?>
                    </button>
                </form>

                <form method="post" action="<?= $e($url('/display/music/settings')) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="disconnect">
                    <button class="btn btn-ghost btn-small" type="submit">
                        <?= $e(translate('music.disconnect')) ?>
                    </button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= $e($url('/display/music/settings')) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="connect">
                    <button class="btn" type="submit" <?= $hasCreds ? '' : 'disabled' ?>>
                        <?= $e(translate('music.connect')) ?>
                    </button>
                </form>

                <?php if (!$hasCreds): ?>
                    <span class="hint"><?= $e(translate('music.need_credentials')) ?></span>
                <?php endif ?>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<?php /* ---------------------------------------------------------- */ ?>
<form method="post" action="<?= $e($url('/display/music/settings')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="action" value="save">

    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('music.wishing')) ?></h2>
        </div>

        <p class="hint">
            <?= $e(translate('music.public_url')) ?>
            <span class="mono" style="word-break:break-all;"><?= $e($publicUrl) ?></span>
        </p>

        <div class="row">
            <a class="btn btn-ghost btn-small" href="<?= $e($publicUrl) ?>" target="_blank" rel="noopener">
                <?= $e(translate('music.open_public')) ?>
            </a>
        </div>

        <?php /*
            Das Panel aus dem alten System: reiner Text fuer eine
            Textquelle in OBS, die eine Adresse ausliest. Es steht hier
            und nicht im Menue - man traegt es einmal in OBS ein und
            sieht es danach nie wieder in der Verwaltung.
        */ ?>
        <p class="hint" style="margin-top:14px;">
            <?= $e(translate('music.panel_url')) ?>
            <span class="mono" style="word-break:break-all;"><?= $e($panelUrl) ?></span>
        </p>

        <label class="field" style="margin-top:14px;">
            <span class="hint"><?= $e(translate('music.cooldown')) ?></span>
            <input class="input" type="number" name="cooldown"
                   min="1" max="60" step="1" value="<?= (int) $cooldown ?>"
                <?= $canEdit ? '' : 'readonly' ?>>
        </label>

        <p class="hint"><?= $e(translate('music.cooldown_hint')) ?></p>
    </div>

    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('music.rules')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('music.rules_hint')) ?></p>

        <label class="field">
            <textarea class="input" name="rules" rows="7"
                      style="resize:vertical;font-family:inherit;line-height:1.6;"
                <?= $canEdit ? '' : 'readonly' ?>><?= $e($rules) ?></textarea>
        </label>
    </div>

    <?php /*
        Der Timer laeuft im Timer-Plugin: dort wird gezaehlt, gewartet
        und gepostet. Hier steht nur, was er sagt - und deshalb gibt es
        diese Karte nur, wenn es das Plugin auch gibt.
    */ ?>
    <?php if ($hasTimers): ?>
        <div class="card">
            <div class="card-head">
                <h2><?= $e(translate('music.timer')) ?></h2>
            </div>

            <p class="hint"><?= $e(translate('music.timer_hint')) ?></p>

            <label class="row" style="gap:8px;margin-bottom:12px;">
                <input type="checkbox" name="timer_enabled" value="1"
                       <?= $timer['enabled'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <span><?= $e(translate('music.timer_enabled')) ?></span>
            </label>

            <div class="row">
                <label class="field">
                    <span class="hint"><?= $e(translate('music.timer_interval')) ?></span>
                    <input class="input" type="number" name="timer_interval"
                           min="<?= (int) \TwitchController\Plugin\Music\Music::TIMER_INTERVAL_MIN ?>"
                           max="<?= (int) \TwitchController\Plugin\Music\Music::TIMER_INTERVAL_MAX ?>"
                           step="1" value="<?= (int) $timer['interval'] ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('music.timer_lines')) ?></span>
                    <input class="input" type="number" name="timer_lines" min="0" max="1000" step="1"
                           value="<?= (int) $timer['lines'] ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            </div>

            <?php /*
                Zwei Texte, weil die Seite zwei Zustaende hat: wer
                wuenschen darf, soll wissen, dass er darf - und wer
                nicht, soll trotzdem nachsehen koennen, was laeuft.
            */ ?>
            <label class="field">
                <span class="hint"><?= $e(translate('music.timer_on')) ?></span>
                <textarea class="input" name="timer_message_on" rows="3"
                          style="resize:vertical;font-family:inherit;line-height:1.6;"
                          maxlength="<?= (int) \TwitchController\Plugin\Music\Music::TIMER_MAX_MESSAGE ?>"
                          placeholder="<?= $e(translate('music.timer_on_example', ['url' => $publicUrl])) ?>"
                          <?= $canEdit ? '' : 'readonly' ?>><?= $e($timer['on']) ?></textarea>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('music.timer_off')) ?></span>
                <textarea class="input" name="timer_message_off" rows="3"
                          style="resize:vertical;font-family:inherit;line-height:1.6;"
                          maxlength="<?= (int) \TwitchController\Plugin\Music\Music::TIMER_MAX_MESSAGE ?>"
                          placeholder="<?= $e(translate('music.timer_off_example', ['url' => $publicUrl])) ?>"
                          <?= $canEdit ? '' : 'readonly' ?>><?= $e($timer['off']) ?></textarea>
            </label>

            <p class="hint"><?= $e(translate('music.timer_empty_hint')) ?></p>
        </div>
    <?php endif ?>

    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('music.size')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('music.size_hint')) ?></p>

        <div class="row">
            <label class="field">
                <span class="hint"><?= $e(translate('music.width')) ?></span>
                <input class="input" type="number" name="width" min="80" max="3840" step="1"
                       value="<?= (int) $width ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('music.height')) ?></span>
                <input class="input" type="number" name="height" min="80" max="3840" step="1"
                       value="<?= (int) $height ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </label>
        </div>

        <p class="hint"><?= $e(translate('music.offset_hint')) ?></p>

        <div class="row">
            <label class="field">
                <span class="hint"><?= $e(translate('music.offset_x')) ?></span>
                <input class="input" type="number" name="offset_x" min="0" max="3840" step="1"
                       value="<?= (int) $offsetX ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('music.offset_y')) ?></span>
                <input class="input" type="number" name="offset_y" min="0" max="3840" step="1"
                       value="<?= (int) $offsetY ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </label>
        </div>

        <?php /*
            Hell oder dunkel: das alte obs.php war ein weisser Kasten
            mit schwarzer Schrift. Auf einem dunklen Spiel ist das gut
            zu lesen - auf einem hellen nicht, und dann will man das
            Dunkle.
        */ ?>
        <label class="field">
            <span class="hint"><?= $e(translate('music.theme')) ?></span>
            <select class="input" name="theme" <?= $canEdit ? '' : 'disabled' ?>>
                <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>
                    <?= $e(translate('music.theme_dark')) ?>
                </option>
                <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>
                    <?= $e(translate('music.theme_light')) ?>
                </option>
            </select>
        </label>
    </div>

    <?php if ($canEdit): ?>
        <div class="row">
            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
        </div>
    <?php endif ?>
</form>
