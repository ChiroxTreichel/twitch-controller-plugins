<?php
/**
 * Vorlage eines Plugins. Liegt im Plugin, benutzt aber das Layout des
 * Kerns - erreicht wird sie mit:
 *
 *   $app->view->from($plugin->directory . '/views')->render('page', [...])
 *
 * @var callable $e
 * @var callable $url
 * @var callable $asset
 * @var string $gruss
 * @var int $zaehler
 * @var bool $canManage
 * @var string $csrf
 * @var string $notice
 */
?>
<link rel="stylesheet" href="<?= $e($asset('/plugin/example/assets/example.css')) ?>">

<h1><?= $e(translate('example.name')) ?></h1>
<p class="lead"><?= $e(translate('example.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif; ?>

<div class="card">
    <h2><?= $e(translate('example.own_setting')) ?></h2>
    <p class="hint">
        <?= translate('example.scope_hint', ['scope' => '<span class="mono">plugin:example</span>']) ?>
    </p>

    <?php if ($canManage): ?>
        <form method="post" action="<?= $e($url('/example')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <div class="field">
                <label for="gruss"><?= $e(translate('example.greeting')) ?></label>
                <input class="input" id="gruss" name="gruss" value="<?= $e($gruss) ?>">
            </div>
            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
        </form>
    <?php else: ?>
        <p><?= $e($gruss) ?></p>
        <p class="hint"><?= translate('common.missing_permission', ['permission' => '<span class="mono">Example.Page.Manage</span>']) ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= $e(translate('example.events_title')) ?></h2>
    <p class="example-notice">
        <?= translate('example.styled', [
            'file' => '<span class="mono">assets/example.css</span>',
        ]) ?>
    </p>
    <p>
        <?= translate('example.counted', [
            'count' => '<strong>' . $e((string) $zaehler) . '</strong>',
        ]) ?>
    </p>
    <p class="hint">
        <?= translate('example.counted_hint', [
            'hook' => '<span class="mono">core.event.stored</span>',
        ]) ?>
    </p>
</div>
