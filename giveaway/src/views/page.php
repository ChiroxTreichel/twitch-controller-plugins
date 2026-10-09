<?php
/**
 * Das Giveaway: Ziehung, Teilnehmer, Preise, Befehl, Werbung.
 *
 * Alles auf einer Seite und nicht in Reitern: im Stream will man die
 * Ziehung und die Teilnehmer zugleich sehen, und die Einstellungen
 * darunter stoeren dabei nicht.
 *
 * @var callable $e
 * @var callable $url
 * @var \TwitchController\Core\Http\View $view
 * @var bool $enabled
 * @var array<string, mixed> $config
 * @var list<string> $prizes
 * @var array<string, mixed> $draw
 * @var bool $hasTimers
 * @var bool $canEdit
 * @var bool $canManage
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\Giveaway\Draw;
use TwitchController\Plugin\Giveaway\Giveaway;

$ziel = $url('/tools/giveaway');
$ziehung = $draw['phase'] === Draw::DRAW;

/** Die Platzhalter, die in einem Text etwas bedeuten - wie bei den Alerts. */
$platzhalter = static function (string $feld) use ($e): void {
    ?>
    <p class="hint placeholders">
        <?= $e(translate('giveaway.placeholders')) ?>
        <?php foreach (Giveaway::PLACEHOLDERS[$feld] as $name): ?>
            <code>{{ <?= $e($name) ?> }}</code>
        <?php endforeach ?>
    </p>
    <?php
};

// Die Teilnehmer nach Tickets, die meisten oben - die Liste auf der
// Seite beantwortet "wer hat am meisten", das Rad sortiert anders.
$teilnehmer = $draw['entries'];
usort($teilnehmer, static fn (array $a, array $b): int => [$b['tickets'], $a['display_name']] <=> [$a['tickets'], $b['display_name']]);
?>
<h1><?= $e(translate('giveaway.name')) ?></h1>
<p class="lead"><?= $e(translate('giveaway.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>
<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('giveaway.off_hint')) ?></div>
<?php endif ?>

<?php /* ================= Ziehung ================= */ ?>
<div class="card">
    <div class="head-row">
        <h2><?= $e(translate('giveaway.draw.title')) ?></h2>
        <?php if ($ziehung): ?>
            <span class="badge badge-warn"><?= $e(translate('giveaway.phase.draw')) ?></span>
        <?php elseif ($enabled): ?>
            <span class="badge badge-ok"><?= $e(translate('giveaway.phase.collect')) ?></span>
        <?php else: ?>
            <span class="badge badge-off"><?= $e(translate('giveaway.phase.off')) ?></span>
        <?php endif ?>
    </div>

    <p class="giveaway-stats">
        <?php // Ohne $e: die Platzhalter sind eigenes Markup. ?>
        <?= translate('giveaway.stats', [
            'people'  => '<strong>' . $e((string) count($draw['entries'])) . '</strong>',
            'tickets' => '<strong>' . $e((string) $draw['tickets']) . '</strong>',
        ]) ?>
        <?php if ($ziehung): ?>
            · <?= $e(translate('giveaway.prizes_left', ['count' => (string) $draw['left']])) ?>
        <?php endif ?>
    </p>

    <?php
    // Fuer das Skript: wann das Rad steht (ansagen), wann neu laden.
    // Ansagen darf nur, wer ziehen darf - zuschauen duerfen alle.
    $zeitplan = $draw['reloadIn'] !== null ? [
        'data-giveaway-timer' => '',
        'data-reload-in'      => (string) $draw['reloadIn'],
    ] + ($canManage && $draw['announceIn'] !== null ? [
        'data-announce-in'  => (string) $draw['announceIn'],
        'data-announce-url' => $url('/tools/giveaway/announce'),
        'data-csrf'         => $csrf,
    ] : []) : [];
    ?>
    <div class="row giveaway-actions"<?php foreach ($zeitplan as $name => $wert): ?> <?= $e($name) ?>="<?= $e($wert) ?>"<?php endforeach ?>>
        <?php if ($draw['step'] === 'collect'): ?>
            <?php
            $grund = match (true) {
                !$enabled                => translate('giveaway.why.off'),
                $prizes === []           => translate('giveaway.why.no_prizes'),
                $draw['entries'] === []  => translate('giveaway.why.no_entries'),
                default                  => '',
            };
            ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= $e($ziel) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="start">
                    <button class="btn giveaway-go" type="submit" <?= $grund !== '' ? 'disabled' : '' ?>>
                        <?= $e(translate('giveaway.draw.start')) ?>
                    </button>
                </form>
            <?php endif ?>
            <?php if ($grund !== ''): ?>
                <span class="hint"><?= $e($grund) ?></span>
            <?php endif ?>

        <?php elseif ($draw['step'] === 'ready'): ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= $e($ziel) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="next">
                    <button class="btn giveaway-go" type="submit" <?= $enabled ? '' : 'disabled' ?>>
                        <?= $e(translate('giveaway.draw.next', ['prize' => $draw['nextPrize']])) ?>
                    </button>
                </form>
            <?php else: ?>
                <span class="hint"><?= $e(translate('giveaway.draw.next_waiting', ['prize' => $draw['nextPrize']])) ?></span>
            <?php endif ?>

        <?php elseif ($draw['step'] === 'spinning'): ?>
            <button class="btn giveaway-go" type="button" disabled><?= $e(translate('giveaway.draw.spinning')) ?></button>

        <?php elseif ($draw['step'] === 'pause'): ?>
            <button class="btn giveaway-go" type="button" disabled><?= $e(translate('giveaway.draw.pause')) ?></button>

        <?php elseif ($draw['step'] === 'done'): ?>
            <span><?= $e(translate('giveaway.draw.done')) ?></span>

        <?php else: ?>
            <span><?= $e(translate('giveaway.draw.empty')) ?></span>
        <?php endif ?>

        <?php // Beenden geht immer - auch mitten in der Ziehung, als Abbruch. ?>
        <?php if ($canManage && ($ziehung || $draw['entries'] !== [])): ?>
            <span class="right">
                <?= $view->render('_confirm', [
                    'label'    => translate('giveaway.end'),
                    'question' => translate('giveaway.end_question'),
                    'note'     => translate('giveaway.end_note'),
                    'confirm'  => translate('giveaway.end_confirm'),
                    'action'   => $ziel,
                    'fields'   => ['csrf' => $csrf, 'action' => 'end'],
                    'right'    => true,
                ], null) ?>
            </span>
        <?php endif ?>
    </div>

    <?php if ($draw['winners'] !== []): ?>
        <h3><?= $e(translate('giveaway.winners')) ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?= $e(translate('giveaway.col.prize')) ?></th>
                    <th><?= $e(translate('giveaway.col.winner')) ?></th>
                    <th><?= $e(translate('giveaway.col.tickets')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($draw['winners'] as $gewinner): ?>
                <tr>
                    <td><?= $e((string) $gewinner['prize']) ?></td>
                    <?php
                    // Erst nach der Ansage. Vorher stuende der Name hier,
                    // waehrend das Rad noch dreht - und wer seinen
                    // Bildschirm im Stream zeigt, haette es verraten.
                    ?>
                    <?php if ($gewinner['announced_at'] !== null): ?>
                        <td><strong><?= $e((string) $gewinner['display_name']) ?></strong> <span class="hint mono">@<?= $e((string) $gewinner['login']) ?></span></td>
                        <td><?= $e((string) $gewinner['tickets']) ?></td>
                    <?php else: ?>
                        <td class="hint"><?= $e(translate('giveaway.draw.spinning')) ?></td>
                        <td></td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?php /* ================= Teilnehmer ================= */ ?>
<div class="card">
    <h2><?= $e(translate('giveaway.entries')) ?></h2>

    <?php if ($teilnehmer === []): ?>
        <div class="empty"><?= $e(translate('giveaway.entries_empty', ['command' => '!' . $config['command']])) ?></div>
    <?php else: ?>
        <div class="giveaway-entries">
            <table>
                <thead>
                    <tr>
                        <th><?= $e(translate('giveaway.col.viewer')) ?></th>
                        <th><?= $e(translate('giveaway.col.tickets')) ?></th>
                        <th><?= $e(translate('giveaway.col.chance')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($teilnehmer as $t): ?>
                    <tr>
                        <td><?= $e($t['display_name']) ?></td>
                        <td><?= $e((string) $t['tickets']) ?></td>
                        <td class="hint"><?= $e(number_format(100 * $t['tickets'] / max(1, $draw['tickets']), 1, ',', '.')) ?> %</td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<?php /* ================= Preise ================= */ ?>
<div class="card">
    <h2><?= $e(translate('giveaway.prizes')) ?></h2>
    <p class="hint"><?= $e(translate('giveaway.prizes_hint')) ?></p>

    <?php if ($ziehung): ?>
        <div class="note note-warn"><?= $e(translate('giveaway.error.prizes_locked')) ?></div>
    <?php endif ?>

    <?php $aenderbar = $canEdit && !$ziehung; ?>

    <?php if ($prizes === []): ?>
        <div class="empty"><?= $e(translate('giveaway.prizes_empty')) ?></div>
    <?php else: ?>
        <table>
            <tbody>
            <?php foreach ($prizes as $i => $preis): ?>
                <tr>
                    <td class="hint mono giveaway-rank"><?= $e((string) ($i + 1)) ?>.</td>
                    <td><?= $e($preis) ?></td>
                    <td class="actions">
                        <?php if ($aenderbar): ?>
                            <div class="row">
                                <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $richtung => $pfeil): ?>
                                    <form method="post" action="<?= $e($ziel) ?>">
                                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="action" value="prize_move">
                                        <input type="hidden" name="index" value="<?= $e((string) $i) ?>">
                                        <input type="hidden" name="direction" value="<?= $e($richtung) ?>">
                                        <button class="btn btn-ghost btn-small" type="submit"
                                                title="<?= $e($richtung === 'up' ? translate('giveaway.move_up') : translate('giveaway.move_down')) ?>"
                                            <?= ($richtung === 'up' && $i === 0) || ($richtung === 'down' && $i === count($prizes) - 1) ? 'disabled' : '' ?>><?= $pfeil ?></button>
                                    </form>
                                <?php endforeach ?>
                                <form method="post" action="<?= $e($ziel) ?>">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="action" value="prize_remove">
                                    <input type="hidden" name="index" value="<?= $e((string) $i) ?>">
                                    <button class="btn btn-ghost btn-small" type="submit"
                                            title="<?= $e(translate('common.remove')) ?>">&times;</button>
                                </form>
                            </div>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <?php if ($aenderbar && count($prizes) < Giveaway::MAX_PRIZES): ?>
        <form class="row giveaway-add" method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="prize_add">
            <input class="input grow" name="prize" maxlength="<?= $e((string) Giveaway::MAX_PRIZE) ?>"
                   placeholder="<?= $e(translate('giveaway.prize_example')) ?>"
                   aria-label="<?= $e(translate('giveaway.prize_add')) ?>" required>
            <button class="btn" type="submit"><?= $e(translate('giveaway.prize_add')) ?></button>
        </form>
    <?php endif ?>
</div>

<?php /* ================= Befehl und Texte ================= */ ?>
<form method="post" action="<?= $e($ziel) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="action" value="command">

    <div class="card">
        <h2><?= $e(translate('giveaway.command')) ?></h2>

        <div class="row giveaway-fields">
            <label class="field grow">
                <span class="hint"><?= $e(translate('giveaway.field.command')) ?></span>
                <input class="input" name="command" value="<?= $e('!' . $config['command']) ?>" required <?= $canEdit ? '' : 'readonly' ?>>
            </label>
            <label class="field">
                <span class="hint"><?= $e(translate('giveaway.field.interval')) ?></span>
                <input class="input" type="number" name="interval" value="<?= $e((string) $config['interval']) ?>"
                       min="<?= $e((string) Giveaway::INTERVAL_MIN) ?>" max="<?= $e((string) Giveaway::INTERVAL_MAX) ?>" required <?= $canEdit ? '' : 'readonly' ?>>
            </label>
        </div>

        <div class="stack">
            <label class="switch-field">
                <input type="checkbox" name="sub_bonus" value="1" <?= $config['sub_bonus'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <span class="switch-track"><span class="switch-knob"></span></span>
                <span><?= $e(translate('giveaway.field.sub_bonus')) ?></span>
            </label>
            <label class="switch-field">
                <input type="checkbox" name="multiple_wins" value="1" <?= $config['multiple_wins'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <span class="switch-track"><span class="switch-knob"></span></span>
                <span>
                    <?= $e(translate('giveaway.field.multiple_wins')) ?>
                    <span class="hint"><?= $e(translate('giveaway.field.multiple_wins_hint')) ?></span>
                </span>
            </label>
        </div>

        <?php foreach (['message_ok', 'message_wait', 'message_closed', 'announce'] as $feld): ?>
            <label class="field giveaway-text">
                <span class="hint"><?= $e(match ($feld) {
                    'message_ok'     => translate('giveaway.field.message_ok'),
                    'message_wait'   => translate('giveaway.field.message_wait'),
                    'message_closed' => translate('giveaway.field.message_closed'),
                    default          => translate('giveaway.field.announce'),
                }) ?></span>
                <textarea class="input" name="<?= $e($feld) ?>" rows="2" maxlength="<?= $e((string) Giveaway::MAX_MESSAGE) ?>"
                    <?= $canEdit ? '' : 'readonly' ?>><?= $e($config[$feld]) ?></textarea>
            </label>
            <?php $platzhalter($feld) ?>
        <?php endforeach ?>
        <p class="hint"><?= $e(translate('giveaway.empty_text_hint')) ?></p>

        <?php if ($canEdit): ?>
            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
        <?php endif ?>
    </div>
</form>

<?php /* ================= Werbung im Chat ================= */ ?>
<?php if (!$hasTimers): ?>
    <div class="card">
        <h2><?= $e(translate('giveaway.timer')) ?></h2>
        <p class="hint"><?= $e(translate('giveaway.timer_needs_plugin')) ?></p>
    </div>
<?php else: ?>
    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="timer">

        <div class="card">
            <h2><?= $e(translate('giveaway.timer')) ?></h2>
            <p class="hint"><?= $e(translate('giveaway.timer_hint')) ?></p>

            <label class="switch-field">
                <input type="checkbox" name="timer_enabled" value="1" <?= $config['timer_enabled'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <span class="switch-track"><span class="switch-knob"></span></span>
                <span><?= $e(translate('giveaway.field.timer_enabled')) ?></span>
            </label>

            <div class="row giveaway-fields">
                <label class="field">
                    <span class="hint"><?= $e(translate('giveaway.field.timer_interval')) ?></span>
                    <input class="input" type="number" name="timer_interval" value="<?= $e((string) $config['timer_interval']) ?>"
                           min="<?= $e((string) Giveaway::TIMER_MIN) ?>" max="<?= $e((string) Giveaway::TIMER_MAX) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>
                <label class="field">
                    <span class="hint"><?= $e(translate('giveaway.field.timer_lines')) ?></span>
                    <input class="input" type="number" name="timer_lines" value="<?= $e((string) $config['timer_lines']) ?>"
                           min="0" max="1000" <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            </div>

            <label class="field giveaway-text">
                <span class="hint"><?= $e(translate('giveaway.field.timer_message')) ?></span>
                <textarea class="input" name="timer_message" rows="2" maxlength="<?= $e((string) Giveaway::MAX_MESSAGE) ?>"
                    <?= $canEdit ? '' : 'readonly' ?>><?= $e($config['timer_message']) ?></textarea>
            </label>
            <?php $platzhalter('timer_message') ?>

            <?php if ($canEdit): ?>
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>
        </div>
    </form>
<?php endif ?>
