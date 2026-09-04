<?php

declare(strict_types=1);

/**
 * Der Reiter in Goals: die Titel, und was Twitch gerade meldet.
 *
 * Aktuell und Ziel sind nicht bearbeitbar — sie kommen von Twitch.
 * Wer sie ändern will, legt auf Twitch ein Ziel an; hier stünde sonst
 * eine Zahl, die beim nächsten Abruf wieder überschrieben wird.
 *
 * Das Aussehen steht nicht hier, sondern unter Plugin → Einstellungen.
 *
 * @var callable $e
 * @var callable $url
 * @var array{follower_title: string, sub_title: string} $titles
 * @var array{follower_current: int, follower_goal: int, sub_current: int, sub_goal: int, checked_at: int} $state
 * @var bool $custom
 * @var int $maxTitle
 * @var string $csrf
 */

use TwitchController\Core\Support\Dates;

$darfAendern = permission('TwitchGoals.Global.Edit');
$ziel = $url('/display/goals/twitch');

/** Ein Ziel ohne Zielwert ist auf Twitch nicht angelegt. */
$hinweis = static function (int $zielwert) use ($e): string {
    return $zielwert > 0 ? '' : $e(translate('twitch_goals.no_goal'));
};
?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('twitch_goals.name')) ?></h2>

        <?php if ($darfAendern): ?>
            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="fetch">
                <button class="btn btn-ghost" type="submit"><?= $e(translate('twitch_goals.fetch')) ?></button>
            </form>
        <?php endif ?>
    </div>

    <p class="hint">
        <?php if ($state['checked_at'] > 0): ?>
            <?= $e(translate('twitch_goals.checked_at', [
                'time' => Dates::long((string) date('Y-m-d H:i:sP', $state['checked_at'])),
            ])) ?>
        <?php else: ?>
            <?= $e(translate('twitch_goals.never_checked')) ?>
        <?php endif ?>
    </p>

    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="titles">

        <h2><?= $e(translate('twitch_goals.follower')) ?></h2>
        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('twitch_goals.field.title')) ?></span>
                <input class="input" type="text" name="follower_title"
                       maxlength="<?= $e((string) $maxTitle) ?>"
                       value="<?= $e($titles['follower_title']) ?>"
                       <?= $darfAendern ? '' : 'disabled' ?>>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('twitch_goals.field.current')) ?></span>
                <input class="input" type="number" value="<?= $e((string) $state['follower_current']) ?>" readonly>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('twitch_goals.field.goal')) ?></span>
                <input class="input" type="number" value="<?= $e((string) $state['follower_goal']) ?>" readonly>
            </label>
        </div>
        <?php if ($hinweis($state['follower_goal']) !== ''): ?>
            <p class="hint"><?= $hinweis($state['follower_goal']) ?></p>
        <?php endif ?>

        <h2><?= $e(translate('twitch_goals.sub')) ?></h2>
        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('twitch_goals.field.title')) ?></span>
                <input class="input" type="text" name="sub_title"
                       maxlength="<?= $e((string) $maxTitle) ?>"
                       value="<?= $e($titles['sub_title']) ?>"
                       <?= $darfAendern ? '' : 'disabled' ?>>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('twitch_goals.field.current')) ?></span>
                <input class="input" type="number" value="<?= $e((string) $state['sub_current']) ?>" readonly>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('twitch_goals.field.goal')) ?></span>
                <input class="input" type="number" value="<?= $e((string) $state['sub_goal']) ?>" readonly>
            </label>
        </div>
        <?php if ($hinweis($state['sub_goal']) !== ''): ?>
            <p class="hint"><?= $hinweis($state['sub_goal']) ?></p>
        <?php endif ?>

        <?php if ($darfAendern): ?>
            <div class="row">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                <a class="btn btn-ghost" href="<?= $e($url('/display/goals/twitch/appearance')) ?>">
                    <?= $e(translate('twitch_goals.to_appearance')) ?>
                </a>
            </div>
        <?php endif ?>
    </form>
</div>
