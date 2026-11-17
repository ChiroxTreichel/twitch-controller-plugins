<?php
/**
 * Einstellungen von Raids - Raiden: was nach einem bestaetigten Raid
 * mit dem Stream des Ziels passiert.
 *
 * Drei Knoepfe zur Wahl statt einer Liste: drei Moeglichkeiten sieht
 * man auf einen Blick, eine Auswahlliste muss man erst aufklappen.
 *
 * @var callable $e
 * @var callable $url
 * @var string $mode   ask | yes | no
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\RaidsRaid\Raid;
?>
<h1><?= $e(translate('raids_raid.name')) ?></h1>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('raids_raid.watch.setting')) ?></h2>
    </div>

    <p class="hint"><?= $e(translate('raids_raid.watch.setting_hint')) ?></p>

    <form method="post" action="<?= $e($url('/networking/raids/raid/settings')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php foreach (Raid::WATCH_MODES as $wahl): ?>
            <label class="row" style="gap:8px; margin:6px 0;">
                <input type="radio" name="watch_mode" value="<?= $e($wahl) ?>" <?= $wahl === $mode ? 'checked' : '' ?>>
                <span>
                    <strong><?= $e(translate('raids_raid.watch.mode_' . $wahl)) ?></strong><br>
                    <span class="hint"><?= $e(translate('raids_raid.watch.mode_' . $wahl . '_hint')) ?></span>
                </span>
            </label>
        <?php endforeach ?>

        <div class="row" style="margin-top:14px;">
            <button class="btn btn-small" type="submit"><?= $e(translate('common.save')) ?></button>
        </div>
    </form>
</div>
