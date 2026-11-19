<?php

declare(strict_types=1);

/**
 * Zuschauer: Einstellungen.
 *
 * Hier sitzen die ERSTEN der beiden Schalter fuer ehemalige
 * Abonnenten und ehemalige Follower: freischalten heisst erfassen.
 * Gezeigt werden sie erst, wenn man sie auf dem jeweiligen Reiter auch
 * einschaltet.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $allowed
 * @var int $stored    so viele Ehemalige liegen gerade in der Tabelle
 * @var bool $unfollowsAllowed
 * @var int $unfollowsStored
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
    <a class="btn btn-ghost" href="<?= $e($url('/stream/viewers')) ?>">
        <?= $e(translate('viewers.to_list')) ?>
    </a>
</div>

<h1><?= $e(translate('viewers.settings')) ?></h1>
<p class="lead"><?= $e(translate('viewers.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $e($url('/stream/viewers/settings')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('viewers.history')) ?></h2>
            <span class="badge <?= $allowed ? 'badge-ok' : 'badge-off' ?>">
                <?= $e($allowed ? translate('viewers.history_on') : translate('viewers.history_off')) ?>
            </span>
        </div>

        <p class="hint"><?= $e(translate('viewers.history_hint')) ?></p>

        <label class="switch-field">
            <input type="checkbox" name="history_allowed" value="1"
                   <?= $allowed ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
            <span class="switch-track"><span class="switch-knob"></span></span>
            <span><?= $e(translate('viewers.history_field')) ?></span>
        </label>

        <p class="hint"><?= $e(translate('viewers.history_off_hint')) ?></p>

        <?php if (!$allowed && $stored > 0): ?>
            <div class="note note-warn">
                <?= $e(translate('viewers.history_kept', ['count' => (string) $stored])) ?>
            </div>
        <?php endif ?>
    </div>

    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('viewers.unfollows')) ?></h2>
            <span class="badge <?= $unfollowsAllowed ? 'badge-ok' : 'badge-off' ?>">
                <?= $e($unfollowsAllowed ? translate('viewers.history_on') : translate('viewers.history_off')) ?>
            </span>
        </div>

        <p class="hint"><?= $e(translate('viewers.unfollows_hint')) ?></p>

        <label class="switch-field">
            <input type="checkbox" name="unfollows_allowed" value="1"
                   <?= $unfollowsAllowed ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
            <span class="switch-track"><span class="switch-knob"></span></span>
            <span><?= $e(translate('viewers.unfollows_field')) ?></span>
        </label>

        <p class="hint"><?= $e(translate('viewers.unfollows_off_hint')) ?></p>

        <?php if (!$unfollowsAllowed && $unfollowsStored > 0): ?>
            <div class="note note-warn">
                <?= $e(translate('viewers.unfollows_kept', ['count' => (string) $unfollowsStored])) ?>
            </div>
        <?php endif ?>
    </div>

    <?php if ($canEdit): ?>
        <div class="row">
            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
        </div>
    <?php endif ?>
</form>
