<?php

declare(strict_types=1);

/**
 * Der Rahmen: Titel, Reiter, Meldungen. Den Inhalt bringt basic.php
 * oder custom.php - siehe plugin.php.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, array{label: string, permission: string}> $tabs
 * @var string $open
 * @var string $content
 * @var bool $enabled
 * @var string $notice
 * @var string $error
 */
?>
<div class="head-row">
    <h1><?= $e(translate('chat_commands.name')) ?></h1>

    <?php if (permission('ChatCommands.Global.Toggle')): ?>
        <form method="post" action="<?= $e($url('/chat/commands/toggle')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <button class="switch<?= $enabled ? ' is-on' : '' ?>" type="submit"
                    title="<?= $e(translate('chat_commands.toggle_hint')) ?>"
                    aria-label="<?= $e(translate('chat_commands.toggle_hint')) ?>">
                <span class="switch-track"><span class="switch-knob"></span></span>
            </button>
        </form>
    <?php else: ?>
        <span class="badge <?= $enabled ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($enabled ? translate('chat_commands.on') : translate('chat_commands.off')) ?>
        </span>
    <?php endif ?>
</div>

<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('chat_commands.all_off_hint')) ?></div>
<?php endif ?>

<div class="tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="tab<?= $open === $key ? ' is-active' : '' ?>"
           href="<?= $e($url('/chat/commands/' . rawurlencode((string) $key))) ?>"><?= $e($tab['label']) ?></a>
    <?php endforeach ?>
</div>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?= $content ?>
