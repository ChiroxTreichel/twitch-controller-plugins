<?php
/**
 * Die Alert-Seite: Kopfzeile mit Hauptschalter, darunter die Reiter
 * der Alert-Plugins.
 *
 * Diese Seite hat keinen eigenen Reiter. Größe und Lage der Fläche
 * sind die Einstellungen dieses Plugins und stehen in der
 * Plugin-Liste - sie gehören nicht zwischen Follows und Bits.
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
<div class="alerts-head">
    <h1><?= $e(translate('alerts.name')) ?></h1>

    <?php if (permission('Alerts.Global.Toggle')): ?>
        <form method="post" action="<?= $e($url('/display/alerts')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <button class="switch<?= $enabled ? ' is-on' : '' ?>" type="submit"
                    title="<?= $e(translate('alerts.toggle_hint')) ?>"
                    aria-label="<?= $e(translate('alerts.toggle_hint')) ?>">
                <span class="switch-track"><span class="switch-knob"></span></span>
            </button>
        </form>
    <?php else: ?>
        <span class="badge <?= $enabled ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($enabled ? translate('alerts.on') : translate('alerts.off')) ?>
        </span>
    <?php endif ?>
</div>

<p class="lead"><?= $e(translate('alerts.lead')) ?></p>

<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('alerts.all_off_hint')) ?></div>
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
            <?= $e(translate('alerts.no_tabs')) ?><br>
            <a class="btn btn-small" style="margin-top:14px;"
               href="<?= $e($url('/account/plugins/find')) ?>"><?= $e(translate('account.plugins.tab_find')) ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <a class="tab<?= $open === $key ? ' is-active' : '' ?>"
               href="<?= $e($url('/display/alerts/' . rawurlencode((string) $key))) ?>"><?= $e($tab['label']) ?></a>
        <?php endforeach ?>
    </div>

    <?php /* Von einem Alert-Plugin gerendert - siehe alerts.tabs. */ ?>
    <?= $content ?>
<?php endif ?>
