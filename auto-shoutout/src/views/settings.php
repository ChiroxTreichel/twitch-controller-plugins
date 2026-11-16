<?php
/**
 * Die Einstellungen: der optionale Custom-Command.
 *
 * @var callable $e
 * @var callable $url
 * @var string $command
 * @var int $maxCommand
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/chat/auto-shoutout/settings');
?>
<h1><?= $e(translate('auto_shoutout.settings_title')) ?></h1>
<p class="lead"><?= $e(translate('auto_shoutout.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('auto_shoutout.command_label')) ?></span>
                <input class="input" type="text" name="command"
                       maxlength="<?= $e((string) $maxCommand) ?>"
                       autocomplete="off" spellcheck="false"
                       value="<?= $e($command) ?>"
                       placeholder="!so {{username}}"
                       <?= $canEdit ? '' : 'disabled' ?>>
            </label>
        </div>

        <p class="hint"><?= $e(translate('auto_shoutout.command_hint')) ?></p>
        <p class="hint">
            <?= $e(translate('auto_shoutout.placeholders')) ?>
            <span class="mono">{{username}}</span>
            <span class="mono">{{displayname}}</span>
        </p>

        <div class="row">
            <?php if ($canEdit): ?>
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>
            <a class="btn btn-ghost" href="<?= $e($url('/chat/auto-shoutout')) ?>">
                <?= $e(translate('auto_shoutout.to_channels')) ?>
            </a>
        </div>
    </form>
</div>
