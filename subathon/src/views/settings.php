<?php
/**
 * Die Einstellungen des Plugins - aus der Plugin-Liste erreichbar.
 *
 * Was den laufenden Subathon betrifft, steht im Reiter
 * "Einstellungen": Startzeit, Obergrenze, was ein Abo bringt. Hier
 * steht, was das Plugin betrifft.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $showManual
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */
?>
<h1><?= $e(translate('subathon.settings')) ?></h1>
<p class="lead"><?= $e(translate('subathon.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $e($url('/tools/subathon/settings')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('subathon.tab.manual')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('subathon.show_manual_hint')) ?></p>

        <label class="row" style="gap:8px;">
            <input type="checkbox" name="show_manual" value="1"
                   <?= $showManual ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
            <span><?= $e(translate('subathon.field.show_manual')) ?></span>
        </label>

        <?php if ($canEdit): ?>
            <div class="row" style="margin-top:14px;">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            </div>
        <?php endif ?>
    </div>
</form>

<p class="hint">
    <a href="<?= $e($url('/tools/subathon')) ?>"><?= $e(translate('subathon.to_page')) ?></a>
</p>
