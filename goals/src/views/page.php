<?php

declare(strict_types=1);

/**
 * Die Goals-Seite: nur die Reiter der Ziel-Plugins.
 *
 * Einen eigenen Reiter hat dieses Plugin nicht - Größe und Lage der
 * Fläche sind seine Einstellungen und stehen in der Plugin-Liste. Sie
 * gehören nicht zwischen Follower- und Sub-Ziel.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, array{label: string, order: int, render: callable|null}> $tabs
 * @var string $open
 * @var string $content
 * @var bool $enabled
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */
?>
<div class="head-row">
    <h1><?= $e(translate('goals.name')) ?></h1>

    <?php if (permission('Goals.Global.Toggle')): ?>
        <form method="post" action="<?= $e($url('/display/goals/toggle')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <button class="switch<?= $enabled ? ' is-on' : '' ?>" type="submit"
                    title="<?= $e(translate('goals.toggle_hint')) ?>"
                    aria-label="<?= $e(translate('goals.toggle_hint')) ?>">
                <span class="switch-track"><span class="switch-knob"></span></span>
            </button>
        </form>
    <?php else: ?>
        <span class="badge <?= $enabled ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($enabled ? translate('goals.on') : translate('goals.off')) ?>
        </span>
    <?php endif ?>
</div>

<p class="lead"><?= $e(translate('goals.lead')) ?></p>

<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('goals.all_off_hint')) ?></div>
<?php endif ?>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if ($tabs === []): ?>
    <div class="card">
        <div class="empty">
            <?= $e(translate('goals.no_tabs')) ?><br>
            <a class="btn" style="margin-top:14px;"
               href="<?= $e($url('/account/plugins/find')) ?>"><?= $e(translate('account.plugins.tab_find')) ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <a class="tab<?= $open === $key ? ' is-active' : '' ?>"
               href="<?= $e($url('/display/goals/' . rawurlencode((string) $key))) ?>"><?= $e($tab['label']) ?></a>
        <?php endforeach ?>
    </div>

    <?php /* Von einem Ziel-Plugin gerendert - siehe goals.tabs. */ ?>
    <?= $content ?>
<?php endif ?>
