<?php

declare(strict_types=1);

/**
 * Einstellungen des Rahmens: Größe und Lage der Fläche.
 *
 * Wie die Ziele aussehen, steht beim jeweiligen Ziel-Plugin — hier
 * geht es nur um den Kasten, in dem sie sitzen.
 *
 * @var callable $e
 * @var callable $url
 * @var int $width
 * @var int $offsetTop
 * @var array{min_width: int, max_width: int, max_offset: int} $limits
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$darfAendern = permission('Goals.Global.Edit');
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
</div>

<h1><?= $e(translate('goals.settings_title')) ?></h1>
<p class="lead"><?= $e(translate('goals.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
    <form method="post" action="<?= $e($url('/display/goals/settings')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <div class="row">
            <label class="field">
                <span class="hint"><?= $e(translate('goals.field.width')) ?></span>
                <input class="input" type="number" name="width"
                       min="<?= $e((string) $limits['min_width']) ?>"
                       max="<?= $e((string) $limits['max_width']) ?>" step="10"
                       value="<?= $e((string) $width) ?>"
                       <?= $darfAendern ? '' : 'disabled' ?>>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('goals.field.offset_top')) ?></span>
                <input class="input" type="number" name="offset_top"
                       min="0" max="<?= $e((string) $limits['max_offset']) ?>" step="5"
                       value="<?= $e((string) $offsetTop) ?>"
                       <?= $darfAendern ? '' : 'disabled' ?>>
            </label>
        </div>

        <p class="hint"><?= $e(translate('goals.field.hint')) ?></p>

        <?php if ($darfAendern): ?>
            <div class="row">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            </div>
        <?php endif ?>
    </form>
</div>
