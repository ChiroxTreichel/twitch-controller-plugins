<?php
/**
 * Die Seite: wer einen Shoutout bekommt, und wie es in dieser Runde
 * um ihn steht.
 *
 * Kacheln wie bei der Live-Benachrichtigung - Bild, Name, Kreuz in der
 * Ecke. Statt der Ziel-Haken steht unten der Stand in dieser Runde.
 *
 * @var callable $e
 * @var callable $url
 * @var list<array<string, mixed>> $channels
 * @var array<string, string> $images
 * @var bool $enabled
 * @var bool $ready
 * @var int $roundAt
 * @var string $roundReason
 * @var bool $canAdd
 * @var bool $canDelete
 * @var bool $canReset
 * @var string $notice
 * @var string $error
 * @var string $csrf
 */

use TwitchController\Core\Support\Dates;

$ziel = $url('/chat/auto-shoutout');
?>
<h1><?= $e(translate('auto_shoutout.name')) ?></h1>
<p class="lead"><?= $e(translate('auto_shoutout.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('auto_shoutout.is_off')) ?></div>
<?php endif ?>

<?php if (!$ready): ?>
    <div class="note note-warn"><?= $e(translate('auto_shoutout.no_scope_hint')) ?></div>
<?php endif ?>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('auto_shoutout.round')) ?></h2>

        <?php if ($canReset): ?>
            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="reset">
                <button class="btn btn-ghost btn-small" type="submit">
                    <?= $e(translate('auto_shoutout.reset')) ?>
                </button>
            </form>
        <?php endif ?>
    </div>

    <p class="hint">
        <?= $e(translate('auto_shoutout.round_since.' . $roundReason, [
            'when' => Dates::short(date('c', $roundAt)),
        ])) ?>
        <?= $e(translate('auto_shoutout.round_hint')) ?>
    </p>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('auto_shoutout.channels')) ?></h2>

        <?php if ($channels !== []): ?>
            <span class="hint"><?= $e(translate('auto_shoutout.channel_count', [
                'count' => (string) count($channels),
            ])) ?></span>
        <?php endif ?>
    </div>

    <?php if ($canAdd): ?>
        <form class="row" method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="add">

            <label class="field grow">
                <span class="hint"><?= $e(translate('auto_shoutout.add_label')) ?></span>
                <input class="input" type="text" name="login" maxlength="120"
                       autocomplete="off" spellcheck="false"
                       placeholder="<?= $e(translate('auto_shoutout.add_example')) ?>">
            </label>

            <button class="btn" type="submit"><?= $e(translate('auto_shoutout.add')) ?></button>
        </form>
    <?php endif ?>

    <?php if ($channels === []): ?>
        <p class="hint"><?= $e(translate('auto_shoutout.no_channels')) ?></p>
    <?php else: ?>
        <div class="aso-grid">
            <?php foreach ($channels as $kanal): ?>
                <?php $bild = $images[$kanal['login']] ?? ''; ?>
                <?php $stand = (string) $kanal['state']; ?>

                <div class="aso-tile">
                    <?php if ($canDelete): ?>
                        <?php $weg = translate('auto_shoutout.remove_channel', ['name' => $kanal['display_name']]); ?>
                        <form class="aso-remove" method="post" action="<?= $e($ziel) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="login" value="<?= $e($kanal['login']) ?>">
                            <button type="submit" title="<?= $e($weg) ?>" aria-label="<?= $e($weg) ?>">&times;</button>
                        </form>
                    <?php endif ?>

                    <a class="aso-head" target="_blank" rel="noopener"
                       href="https://twitch.tv/<?= $e(rawurlencode($kanal['login'])) ?>">
                        <?php if ($bild !== ''): ?>
                            <img class="aso-avatar is-<?= $e($stand) ?>" src="<?= $e($bild) ?>" alt="">
                        <?php else: ?>
                            <div class="aso-avatar aso-avatar-empty is-<?= $e($stand) ?>">
                                <?= $e(strtoupper(substr((string) $kanal['display_name'], 0, 1))) ?>
                            </div>
                        <?php endif ?>

                        <div class="aso-name"><?= $e($kanal['display_name']) ?></div>
                    </a>

                    <?php /*
                        Der Stand in DIESER Runde, ausgeschrieben - Farbe
                        allein darf die Aussage nicht tragen.
                    */ ?>
                    <div class="aso-state is-<?= $e($stand) ?>"
                         <?= $kanal['error'] !== '' ? 'title="' . $e($kanal['error']) . '"' : '' ?>>
                        <?php if ($stand === 'done'): ?>
                            <?= $e(translate('auto_shoutout.state.done', [
                                'when' => Dates::short($kanal['sent_at']),
                            ])) ?>
                        <?php elseif ($stand === 'waiting'): ?>
                            <?= $e(translate('auto_shoutout.state.waiting')) ?>
                        <?php elseif ($stand === 'failed'): ?>
                            <?= $e(translate('auto_shoutout.state.failed')) ?>
                            <span class="aso-error"><?= $e($kanal['error']) ?></span>
                        <?php else: ?>
                            <?= $e(translate('auto_shoutout.state.idle')) ?>
                        <?php endif ?>
                    </div>

                    <?php if ($kanal['seen_at'] !== null): ?>
                        <div class="aso-foot hint">
                            <?= $e(translate('auto_shoutout.seen_at', [
                                'when' => Dates::short($kanal['seen_at']),
                            ])) ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
