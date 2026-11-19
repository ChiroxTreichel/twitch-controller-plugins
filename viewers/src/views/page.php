<?php

declare(strict_types=1);

/**
 * Zuschauer: Reiter "Follower" und "Abonnenten", je mit Suche.
 *
 * Alles hier ist GET - Suche, Reiter, Seite, der Schalter fuer die
 * Ehemaligen. So laesst sich jede Ansicht als Adresse weitergeben und
 * mit "Zurueck" wiederfinden.
 *
 * @var callable $e
 * @var callable $url
 * @var string $tab               'followers' | 'subs'
 * @var string $search
 * @var int $page
 * @var list<array<string, mixed>> $rows
 * @var int $total
 * @var array{followers: int, unfollows: int, subs: int, former: int} $counts
 * @var bool $former              zeigt gerade die Ehemaligen
 * @var bool $formerShown         Schalter fuer die Ehemaligen anbieten
 * @var array{at: int, error: string} $sync
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Core\Support\Dates;
use TwitchController\Plugin\Viewers\Viewers;

$basis = $url('/stream/viewers');

/** Adresse dieser Seite mit geaenderten Angaben. */
$adresse = static function (array $aenderung) use ($basis, $tab, $search, $former): string {
    $angaben = array_merge(
        ['tab' => $tab, 'q' => $search, 'former' => $former ? '1' : ''],
        $aenderung
    );

    return $basis . '?' . http_build_query(array_filter($angaben, static fn ($w): bool => $w !== '' && $w !== null));
};

$seiten = max(1, (int) ceil($total / Viewers::PER_PAGE));
?>
<h1><?= $e(translate('viewers.name')) ?></h1>
<p class="lead"><?= $e(translate('viewers.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="tabs">
    <a class="tab<?= $tab === 'followers' ? ' is-active' : '' ?>" href="<?= $e($basis . '?tab=followers') ?>">
        <?= $e(translate('viewers.tab.followers')) ?>
        <span class="viewers-count"><?= $e(number_format($counts['followers'], 0, ',', '.')) ?></span>
    </a>
    <a class="tab<?= $tab === 'subs' ? ' is-active' : '' ?>" href="<?= $e($basis . '?tab=subs') ?>">
        <?= $e(translate('viewers.tab.subs')) ?>
        <span class="viewers-count"><?= $e(number_format($counts['subs'], 0, ',', '.')) ?></span>
    </a>
</div>

<div class="card">
    <div class="viewers-bar">
        <form class="viewers-search" method="get" action="<?= $e($basis) ?>" data-no-ajax>
            <input type="hidden" name="tab" value="<?= $e($tab) ?>">
            <?php if ($former): ?>
                <input type="hidden" name="former" value="1">
            <?php endif ?>
            <input class="input" type="search" name="q" value="<?= $e($search) ?>"
                   placeholder="<?= $e(translate('viewers.search')) ?>"
                   aria-label="<?= $e(translate('viewers.search')) ?>">
            <button class="btn btn-ghost" type="submit"><?= $e(translate('viewers.search_button')) ?></button>
        </form>

        <?php /*
            Der zweite der beiden Schalter. Ein Link und kein Formular:
            er aendert nur, was gezeigt wird - gespeichert wird nichts.
        */ ?>
        <?php if ($formerShown): ?>
            <a class="viewers-toggle" href="<?= $e($adresse(['former' => $former ? '' : '1', 'p' => ''])) ?>"
               role="switch" aria-checked="<?= $former ? 'true' : 'false' ?>">
                <span class="switch<?= $former ? ' is-on' : '' ?>">
                    <span class="switch-track"><span class="switch-knob"></span></span>
                </span>
                <span><?= $e(translate($tab === 'subs' ? 'viewers.former_toggle' : 'viewers.unfollow_toggle')) ?></span>
                <span class="viewers-count"><?= $e((string) $counts[$tab === 'subs' ? 'former' : 'unfollows']) ?></span>
            </a>
        <?php endif ?>
    </div>

    <?php if ($rows === []): ?>
        <div class="empty">
            <?php if ($search !== ''): ?>
                <?= $e(translate('viewers.empty_search', ['search' => $search])) ?>
            <?php elseif ($sync['at'] === 0): ?>
                <?= $e(translate('viewers.empty_never')) ?>
            <?php else: ?>
                <?= $e(translate($former
                    ? ($tab === 'subs' ? 'viewers.empty_former' : 'viewers.empty_unfollows')
                    : ($tab === 'subs' ? 'viewers.empty_subs' : 'viewers.empty_followers'))) ?>
            <?php endif ?>
        </div>
    <?php elseif ($tab === 'followers' && $former): ?>
        <table class="viewers-table">
            <thead>
            <tr>
                <th><?= $e(translate('viewers.col.name')) ?></th>
                <th><?= $e(translate('viewers.col.followed_from')) ?></th>
                <th><?= $e(translate('viewers.col.unfollowed_at')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $zeile): ?>
                <tr>
                    <td><?php require __DIR__ . '/_name.php' ?></td>
                    <td class="viewers-nowrap">
                        <?php if ($zeile['followed_at'] !== null): ?>
                            <?= $e(Dates::day((string) $zeile['followed_at'])) ?>
                        <?php else: ?>
                            <span class="viewers-muted"><?= $e(translate('viewers.unknown')) ?></span>
                        <?php endif ?>
                    </td>
                    <td class="viewers-nowrap">
                        <?= $e(Dates::day((string) $zeile['unfollowed_at'])) ?>
                        <div class="viewers-muted"><?= $e(Viewers::duration((string) $zeile['unfollowed_at'])) ?></div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php elseif ($tab === 'followers'): ?>
        <table class="viewers-table">
            <thead>
            <tr>
                <th><?= $e(translate('viewers.col.name')) ?></th>
                <th><?= $e(translate('viewers.col.followed_at')) ?></th>
                <th><?= $e(translate('viewers.col.duration')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $zeile): ?>
                <tr>
                    <td><?php require __DIR__ . '/_name.php' ?></td>
                    <td class="viewers-nowrap"><?= $e(Dates::day((string) $zeile['followed_at'])) ?></td>
                    <td class="viewers-muted viewers-nowrap"><?= $e(Viewers::duration((string) $zeile['followed_at'])) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php elseif ($former): ?>
        <table class="viewers-table">
            <thead>
            <tr>
                <th><?= $e(translate('viewers.col.name')) ?></th>
                <th><?= $e(translate('viewers.col.last_tier')) ?></th>
                <th><?= $e(translate('viewers.col.ended_at')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $zeile): ?>
                <tr>
                    <td><?php require __DIR__ . '/_name.php' ?></td>
                    <td class="viewers-nowrap"><?= $e(Viewers::tierLabel((string) $zeile['tier'])) ?></td>
                    <td class="viewers-nowrap">
                        <?php if ($zeile['ended_at'] !== null): ?>
                            <?= $e(Dates::day((string) $zeile['ended_at'])) ?>
                            <div class="viewers-muted"><?= $e(Viewers::duration((string) $zeile['ended_at'])) ?></div>
                        <?php else: ?>
                            <span class="viewers-muted"><?= $e(translate('viewers.unknown')) ?></span>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="viewers-table">
            <thead>
            <tr>
                <th><?= $e(translate('viewers.col.name')) ?></th>
                <th><?= $e(translate('viewers.col.tier')) ?></th>
                <th><?= $e(translate('viewers.col.ends_at')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $zeile): ?>
                <tr>
                    <td>
                        <?php require __DIR__ . '/_name.php' ?>
                        <?php if (!empty($zeile['is_gift'])): ?>
                            <div class="viewers-muted">
                                <?= $e((string) $zeile['gifter_name'] !== ''
                                    ? translate('viewers.gifted_by', ['name' => (string) $zeile['gifter_name']])
                                    : translate('viewers.gifted')) ?>
                            </div>
                        <?php endif ?>
                    </td>
                    <td class="viewers-nowrap"><?= $e(Viewers::tierLabel((string) $zeile['tier'])) ?></td>
                    <td class="viewers-nowrap">
                        <?php if ($zeile['ends_at'] !== null): ?>
                            <?= $e(Dates::day((string) $zeile['ends_at'])) ?>
                            <?php if (!empty($zeile['ends_estimated'])): ?>
                                <div class="viewers-muted" title="<?= $e(translate('viewers.estimated_hint')) ?>">
                                    <?= $e(translate('viewers.estimated')) ?>
                                </div>
                            <?php endif ?>
                        <?php else: ?>
                            <span class="viewers-muted" title="<?= $e(translate('viewers.unknown_hint')) ?>">
                                <?= $e(translate('viewers.unknown')) ?>
                            </span>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <?php if ($seiten > 1): ?>
        <div class="viewers-pager">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="<?= $e($adresse(['p' => (string) ($page - 1)])) ?>">‹ <?= $e(translate('viewers.prev')) ?></a>
            <?php endif ?>
            <span class="viewers-muted">
                <?= $e(translate('viewers.page_of', ['page' => (string) min($page, $seiten), 'pages' => (string) $seiten, 'total' => number_format($total, 0, ',', '.')])) ?>
            </span>
            <?php if ($page < $seiten): ?>
                <a class="btn btn-ghost" href="<?= $e($adresse(['p' => (string) ($page + 1)])) ?>"><?= $e(translate('viewers.next')) ?> ›</a>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<?php /*
    Wie alt die Liste ist - und warum, wenn der letzte Abgleich nicht
    klappte. Eine Liste, die seit einem Tag stillsteht, sieht sonst
    genauso aus wie eine aktuelle.
*/ ?>
<div class="viewers-sync">
    <span class="hint">
        <?php if ($sync['at'] > 0): ?>
            <?= $e(translate('viewers.live')) ?>
        <?php else: ?>
            <?= $e(translate('viewers.never_synced')) ?>
        <?php endif ?>
    </span>
    <form method="post" action="<?= $e($url('/stream/viewers/sync')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="tab" value="<?= $e($tab) ?>">
        <button class="btn btn-ghost" type="submit"><?= $e(translate('viewers.sync_now')) ?></button>
    </form>
</div>

<?php if ($sync['error'] !== ''): ?>
    <div class="note note-error">
        <?= $e(translate('viewers.sync_failed')) ?>
        <code><?= $e($sync['error']) ?></code>
    </div>
<?php endif ?>
