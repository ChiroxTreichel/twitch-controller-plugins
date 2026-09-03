<?php
/**
 * Einstellungen des Alerts-Plugins: Größe und Lage der Fläche.
 *
 * Erreichbar über *Konto → Plugins → Einstellungen*, nicht als Reiter
 * unter „Alerts" - das sind die Einstellungen dieses Plugins und keine
 * Alert-Art.
 *
 * Die Dauer steht hier nicht: die legt jeder Alert selbst fest, je Fall
 * oder je Stufe.
 *
 * @var callable $e
 * @var callable $url
 * @var int $width
 * @var int $offsetTop
 * @var int $mediaWidth
 * @var int $mediaHeight
 * @var bool $enabled
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$darfAendern = permission('Alerts.Global.Edit');
$darfTesten = permission('Alerts.Global.Test');
?>
<h1><?= $e(translate('alerts.settings_title')) ?></h1>
<p class="lead"><?= $e(translate('alerts.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="row">
    <?php /*
        Zurueck zur Plugin-Liste, nicht zu den Alerts: von dort kommt
        man hierher, und hierher gehoert diese Seite auch - es sind
        die Einstellungen des Plugins.
    */ ?>
    <a class="btn btn-ghost btn-small" href="<?= $e($url('/account/plugins')) ?>">
        &larr; <?= $e(translate('alerts.back_to_plugins')) ?>
    </a>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('alerts.area_heading')) ?></h2>
    </div>
    <p class="hint"><?= $e(translate('alerts.area_hint')) ?></p>

    <form method="post" action="<?= $e($url('/display/alerts/settings')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="settings">

        <div class="row">
            <label class="field">
                <span class="hint"><?= $e(translate('alerts.width')) ?></span>
                <input class="input" type="number" name="width" min="160" max="3840"
                       value="<?= (int) $width ?>" <?= $darfAendern ? '' : 'disabled' ?>>
            </label>
            <label class="field">
                <span class="hint"><?= $e(translate('alerts.offset_top')) ?></span>
                <input class="input" type="number" name="offset_top" min="0" max="2160"
                       value="<?= (int) $offsetTop ?>" <?= $darfAendern ? '' : 'disabled' ?>>
            </label>
        </div>

        <p class="hint" style="margin-top:14px;"><?= $e(translate('alerts.media_hint')) ?></p>

        <div class="row">
            <label class="field">
                <span class="hint"><?= $e(translate('alerts.media_width')) ?></span>
                <input class="input" type="number" name="media_width" min="0" max="3840"
                       placeholder="<?= $e(translate('alerts.automatic')) ?>"
                       value="<?= $mediaWidth > 0 ? (int) $mediaWidth : '' ?>"
                    <?= $darfAendern ? '' : 'disabled' ?>>
            </label>
            <label class="field">
                <span class="hint"><?= $e(translate('alerts.media_height')) ?></span>
                <input class="input" type="number" name="media_height" min="0" max="2160"
                       placeholder="<?= $e(translate('alerts.automatic')) ?>"
                       value="<?= $mediaHeight > 0 ? (int) $mediaHeight : '' ?>"
                    <?= $darfAendern ? '' : 'disabled' ?>>
            </label>
        </div>

        <?php if ($darfAendern): ?>
            <div class="row" style="margin-top:16px;">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            </div>
        <?php endif ?>
    </form>
</div>

<?php if ($darfTesten): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('alerts.test_heading')) ?></h2>
        </div>
        <p class="hint"><?= $e(translate('alerts.test_hint')) ?></p>

        <?php if (!$enabled): ?>
            <div class="note note-warn"><?= $e(translate('alerts.all_off_hint')) ?></div>
        <?php endif ?>

        <form method="post" action="<?= $e($url('/display/alerts/settings')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="test">
            <button class="btn btn-ghost" type="submit"><?= $e(translate('alerts.send_test')) ?></button>
        </form>
    </div>
<?php endif ?>
