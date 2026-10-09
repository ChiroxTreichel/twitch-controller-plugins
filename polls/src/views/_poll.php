<?php
/**
 * Eine Umfrage in der Verwaltung: Kopf, Link, Zwischenstand, Knoepfe.
 *
 * Fuer laufende als Karte, fuer aeltere im aufklappbaren Kasten - der
 * Inhalt ist derselbe.
 *
 * @var callable $e
 * @var callable $url
 * @var \TwitchController\Core\Http\View $view
 * @var array<string, mixed> $poll
 * @var bool $inOverlay
 * @var bool $canEdit
 * @var bool $canDelete
 * @var string $csrf
 */

use TwitchController\Plugin\Polls\Polls;

$offen = Polls::isOpen($poll);
$link = $url('/polls/' . $poll['id']);
$stand = $poll['results'];
$ziel = $url('/tools/polls');

// Wohin angekuendigt wird - als kurze Liste unter dem Kopf.
$wege = [];
if ($poll['announce_discord_new'] || $poll['announce_discord_result']) {
    $wege[] = translate('polls.channel.discord', ['what' => implode(' + ', array_filter([
        $poll['announce_discord_new'] ? translate('polls.what.new') : '',
        $poll['announce_discord_result'] ? translate('polls.what.result') : '',
    ]))]);
}
if ($poll['announce_chat_new'] || $poll['announce_chat_result']) {
    $wege[] = translate('polls.channel.chat', ['what' => implode(' + ', array_filter([
        $poll['announce_chat_new'] ? translate('polls.what.new') : '',
        $poll['announce_chat_result'] ? translate('polls.what.result') : '',
    ]))]);
}
?>
<p class="hint polls-meta">
    <?= $e(translate('polls.meta', [
        'end'     => date('d.m.Y H:i', (int) $poll['ends_ts']),
        'creator' => $poll['creator_name'] !== '' ? $poll['creator_name'] : '?',
    ])) ?>
    · <?= $e(translate('polls.choose', ['count' => (string) $poll['max_choices']])) ?>
    · <?= $e($poll['participants'] === 1 ? translate('polls.participants_one') : translate('polls.participants', ['count' => (string) $poll['participants']])) ?>
    <?php if ($poll['suggestions']): ?>
        · <?= $e(translate('polls.with_suggestions')) ?>
    <?php endif ?>
    <?php if ($wege !== []): ?>
        · <?= $e(implode(', ', $wege)) ?>
        <?php if ($poll['result_announced']): ?>
            (<?= $e(translate('polls.announced')) ?>)
        <?php endif ?>
    <?php endif ?>
</p>

<?php if ($poll['description'] !== ''): ?>
    <p class="polls-description"><?= nl2br($e($poll['description'])) ?></p>
<?php endif ?>

<div class="row polls-link">
    <input class="input mono grow" value="<?= $e($link) ?>" readonly aria-label="<?= $e(translate('polls.link')) ?>">
    <button class="btn btn-ghost" type="button" data-copy="<?= $e($link) ?>"
            data-copied="<?= $e(translate('polls.copied')) ?>"><?= $e(translate('polls.copy')) ?></button>
    <a class="btn btn-ghost" href="<?= $e($link) ?>" target="_blank" rel="noopener"><?= $e(translate('polls.open')) ?></a>
</div>

<?php if ($stand['rows'] !== []): ?>
    <?php $meiste = max(1, max(array_column($stand['rows'], 'votes'))); ?>
    <h3><?= $e($offen ? translate('polls.interim') : translate('polls.result')) ?></h3>
    <div class="polls-rows">
        <?php foreach ($stand['rows'] as $zeile): ?>
            <div class="polls-row">
                <span class="polls-label"><?= $e($zeile['label']) ?></span>
                <span class="polls-votes"><?= $e((string) $zeile['votes']) ?></span>
                <span class="polls-bar"><span style="width: <?= $e((string) round($zeile['votes'] / $meiste * 100, 1)) ?>%"></span></span>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php if ($canEdit || $canDelete): ?>
    <div class="row polls-actions">
        <?php if ($canEdit): ?>
            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="overlay">
                <input type="hidden" name="id" value="<?= $e((string) $poll['id']) ?>">
                <button class="btn <?= $inOverlay ? '' : 'btn-ghost' ?>" type="submit">
                    <?= $e($inOverlay ? translate('polls.overlay_remove') : translate('polls.overlay_show')) ?>
                </button>
            </form>
            <?php if ($offen): ?>
                <?= $view->render('_confirm', [
                    'label'    => translate('polls.end'),
                    'question' => translate('polls.end_question', ['title' => $poll['title']]),
                    'note'     => translate('polls.end_note'),
                    'confirm'  => translate('polls.end_confirm'),
                    'action'   => $ziel,
                    'fields'   => ['csrf' => $csrf, 'action' => 'end', 'id' => (string) $poll['id']],
                    'danger'   => false,
                ], null) ?>
            <?php endif ?>
        <?php endif ?>
        <?php if ($canDelete): ?>
            <span class="right">
                <?= $view->render('_confirm', [
                    'label'    => translate('polls.delete'),
                    'question' => translate('polls.delete_question', ['title' => $poll['title']]),
                    'note'     => translate('polls.delete_note'),
                    'confirm'  => translate('polls.delete_confirm'),
                    'action'   => $ziel,
                    'fields'   => ['csrf' => $csrf, 'action' => 'delete', 'id' => (string) $poll['id']],
                    'right'    => true,
                ], null) ?>
            </span>
        <?php endif ?>
    </div>
<?php endif ?>
