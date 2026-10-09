<?php
/**
 * Die Umfragen in der Verwaltung.
 *
 * Laufende und gerade beendete oben als Karten - wie im alten System,
 * das nur zeigte, was laeuft oder hoechstens einen Tag vorbei ist.
 * Aeltere verschwinden hier nicht, sondern stehen darunter zugeklappt:
 * man will auch spaeter noch nachsehen koennen, wie etwas ausging.
 *
 * @var callable $e
 * @var callable $url
 * @var \TwitchController\Core\Http\View $view
 * @var list<array<string, mixed>> $polls
 * @var int $overlayPoll
 * @var bool $hasWebhook
 * @var bool $canCreate
 * @var bool $canEdit
 * @var bool $canDelete
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\Polls\Polls;

$frisch = [];
$aelter = [];
foreach ($polls as $poll) {
    if (Polls::isOpen($poll) || time() - (int) $poll['ends_ts'] < 86400) {
        $frisch[] = $poll;
    } else {
        $aelter[] = $poll;
    }
}

$teile = static fn (array $poll): array => [
    'poll'      => $poll,
    'inOverlay' => $poll['id'] === $overlayPoll,
    'canEdit'   => $canEdit,
    'canDelete' => $canDelete,
    'csrf'      => $csrf,
];
?>
<div class="head-row">
    <h1><?= $e(translate('polls.name')) ?></h1>
    <?php if ($canCreate): ?>
        <a class="btn" href="<?= $e($url('/tools/polls/new')) ?>"><?= $e(translate('polls.new')) ?></a>
    <?php endif ?>
</div>
<p class="lead"><?= $e(translate('polls.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if ($polls === []): ?>
    <div class="card">
        <div class="empty"><?= $e(translate('polls.empty')) ?></div>
    </div>
<?php endif ?>

<?php foreach ($frisch as $poll): ?>
    <div class="card polls-card<?= $poll['id'] === $overlayPoll ? ' is-overlay' : '' ?>">
        <div class="head-row">
            <h2>#<?= $e((string) $poll['id']) ?> · <?= $e($poll['title']) ?></h2>
            <span>
                <?php if ($poll['id'] === $overlayPoll): ?>
                    <span class="badge"><?= $e(translate('polls.in_overlay')) ?></span>
                <?php endif ?>
                <?php if (Polls::isOpen($poll)): ?>
                    <span class="badge badge-ok"><?= $e(translate('polls.running')) ?></span>
                <?php else: ?>
                    <span class="badge badge-off"><?= $e(translate('polls.finished')) ?></span>
                <?php endif ?>
            </span>
        </div>
        <?= $view->render('_poll', $teile($poll), null) ?>
    </div>
<?php endforeach ?>

<?php if ($aelter !== []): ?>
    <h2 class="polls-older"><?= $e(translate('polls.older')) ?></h2>
    <?php foreach ($aelter as $poll): ?>
        <details class="case">
            <summary>
                #<?= $e((string) $poll['id']) ?> · <?= $e($poll['title']) ?>
                <span class="hint"><?= $e(date('d.m.Y', (int) $poll['ends_ts'])) ?></span>
                <?php if ($poll['id'] === $overlayPoll): ?>
                    <span class="badge"><?= $e(translate('polls.in_overlay')) ?></span>
                <?php endif ?>
            </summary>
            <div class="case-body">
                <?= $view->render('_poll', $teile($poll), null) ?>
            </div>
        </details>
    <?php endforeach ?>
<?php endif ?>
