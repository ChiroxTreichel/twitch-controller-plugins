<?php
/**
 * Einstellungen der Umfragen: Discord und Overlay.
 *
 * Im alten System stand der Webhook an jeder Umfrage, vorbelegt mit
 * dem zuletzt benutzten. Hier ist es einer fuer den Kanal - wer
 * ankuendigen will, schaltet es an der Umfrage nur noch ein.
 *
 * @var callable $e
 * @var callable $url
 * @var string $webhook
 * @var string $mention
 * @var string $roleId
 * @var string $position
 * @var int $width
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\Polls\Polls;

$ziel = $url('/tools/polls/settings');
?>
<h1><?= $e(translate('polls.settings')) ?></h1>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $e($ziel) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="action" value="save">

    <div class="card">
        <h2><?= $e(translate('polls.discord')) ?></h2>
        <p class="hint"><?= $e(translate('polls.discord_hint')) ?></p>

        <label class="field">
            <span class="hint"><?= $e(translate('polls.field.webhook')) ?></span>
            <input class="input mono" type="url" name="discord_webhook" value="<?= $e($webhook) ?>"
                   placeholder="https://discord.com/api/webhooks/…" <?= $canEdit ? '' : 'readonly' ?>>
        </label>

        <?php /*
            Die Rollen-ID steht nur da, wenn "Eigene Rolle" gewaehlt ist
            (polls.js). Ohne Skript steht sie immer da und gilt nur bei
            dieser Wahl.
        */ ?>
        <div class="row polls-mention">
            <label class="field">
                <span class="hint"><?= $e(translate('polls.field.mention')) ?></span>
                <select name="discord_mention" data-polls-mention <?= $canEdit ? '' : 'disabled' ?>>
                    <?php foreach (Polls::MENTIONS as $art): ?>
                        <option value="<?= $e($art) ?>" <?= $art === $mention ? 'selected' : '' ?>><?= $e(match ($art) {
                            'everyone' => translate('polls.mention.everyone'),
                            'here'     => translate('polls.mention.here'),
                            'role'     => translate('polls.mention.role'),
                            default    => translate('polls.mention.none'),
                        }) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label class="field grow" data-polls-role <?= $mention === 'role' ? '' : 'hidden' ?>>
                <span class="hint"><?= $e(translate('polls.field.role_id')) ?></span>
                <input class="input mono" type="text" name="discord_role_id" value="<?= $e($roleId) ?>"
                       inputmode="numeric" maxlength="40" placeholder="123456789012345678"
                       <?= $canEdit ? '' : 'readonly' ?>>
            </label>
        </div>
        <p class="hint"><?= $e(translate('polls.mention_hint')) ?></p>
    </div>

    <div class="card">
        <h2><?= $e(translate('polls.overlay')) ?></h2>
        <p class="hint">
            <?php // Ohne $e: der Platzhalter ist eigenes Markup. ?>
            <?= translate('polls.overlay_hint', ['view' => '<span class="mono">?view=polls</span>']) ?>
        </p>

        <div class="row">
            <label class="field">
                <span class="hint"><?= $e(translate('polls.field.position')) ?></span>
                <select name="overlay_position" <?= $canEdit ? '' : 'disabled' ?>>
                    <?php foreach (Polls::POSITIONS as $stelle): ?>
                        <option value="<?= $e($stelle) ?>" <?= $stelle === $position ? 'selected' : '' ?>><?= $e($stelle) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <label class="field polls-narrow">
                <span class="hint"><?= $e(translate('polls.field.width')) ?></span>
                <input class="input" type="number" name="overlay_width" min="300" max="1920" value="<?= $e((string) $width) ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </label>
        </div>
    </div>

    <?php if ($canEdit): ?>
        <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
    <?php endif ?>
</form>

<?php if ($canEdit && $webhook !== ''): ?>
    <form method="post" action="<?= $e($ziel) ?>" class="polls-test">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="test_discord">
        <button class="btn btn-ghost" type="submit"><?= $e(translate('polls.test_discord')) ?></button>
    </form>
<?php endif ?>
