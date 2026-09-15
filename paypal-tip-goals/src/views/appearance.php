<?php
/**
 * Das Aussehen: HTML und CSS des Spendenbalkens.
 *
 * Erreichbar über Plugin → Einstellungen, nicht als Reiter in Goals —
 * es ist eine Einstellung dieses Plugins und gehört nicht zwischen die
 * Spendenziele.
 *
 * @var callable $e
 * @var callable $url
 * @var callable $view
 * @var string $html
 * @var string $css
 * @var bool $custom
 * @var list<string> $missing
 * @var array<string, string> $required  Name => Klarname
 * @var array<string, string> $fills
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/display/goals/tips/appearance');
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
    <a class="btn btn-ghost" href="<?= $e($url('/display/goals/tips')) ?>">
        <?= $e(translate('pp_tip.to_goals')) ?>
    </a>
</div>

<h1><?= $e(translate('pp_tip.appearance')) ?></h1>
<p class="lead"><?= $e(translate('pp_tip.appearance_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /*
    Fehlende Pflichtelemente stehen oben und nicht versteckt: ein
    Balken ohne sein Element zeigt im Overlay nichts an, und das faellt
    erst mitten im Stream auf.
*/ ?>
<?php if ($missing !== []): ?>
    <div class="note note-error">
        <strong><?= $e(translate('pp_tip.missing_hint')) ?></strong>
        <?php foreach ($missing as $eines): ?>
            <br><span class="mono"><?= $e($eines) ?></span>
        <?php endforeach ?>
    </div>
<?php endif ?>

<div class="card">
    <h2><?= $e(translate('pp_tip.required')) ?></h2>
    <p class="hint"><?= $e(translate('pp_tip.required_hint')) ?></p>

    <table>
        <tbody>
            <?php foreach ($required as $name => $klarname): ?>
                <tr>
                    <td><span class="mono">data-bind="<?= $e((string) $name) ?>"</span></td>
                    <td><?= $e($klarname) ?></td>
                </tr>
            <?php endforeach ?>
            <?php foreach ($fills as $name => $klarname): ?>
                <tr>
                    <td><span class="mono">data-fill="<?= $e((string) $name) ?>"</span></td>
                    <td><?= $e($klarname) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <p class="hint"><?= $e(translate('pp_tip.format_hint')) ?></p>
</div>

<div class="card">
    <h2><?= $e(translate('pp_tip.markup')) ?></h2>

    <?php if (!$custom): ?>
        <div class="note note-warn"><?= $e(translate('pp_tip.is_default')) ?></div>
    <?php endif ?>

    <?php /*
        Der Knopf steht ausserhalb des Formulars und findet es ueber
        form="…". Anders geht es nicht: die Rueckfrage bringt ihr
        eigenes Formular mit, und ein Formular in einem Formular ist
        ungueltiges HTML.
    */ ?>
    <form id="tip-appearance" method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">

        <label class="field">
            <span class="hint"><?= $e(translate('pp_tip.field.html')) ?></span>
            <textarea class="input tip-code" name="html" rows="16" spellcheck="false"
                      <?= $canEdit ? '' : 'disabled' ?>><?= $e($html) ?></textarea>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('pp_tip.field.css')) ?></span>
            <textarea class="input tip-code" name="css" rows="18" spellcheck="false"
                      <?= $canEdit ? '' : 'disabled' ?>><?= $e($css) ?></textarea>
        </label>

        <p class="hint"><?= $e(translate('pp_tip.no_script_hint')) ?></p>
        <p class="hint"><?= $e(translate('pp_tip.scope_hint')) ?></p>
    </form>

    <?php if ($canEdit): ?>
        <div class="row">
            <button class="btn" type="submit" form="tip-appearance">
                <?= $e(translate('common.save')) ?>
            </button>

            <?= $view->render('_confirm', [
                'label'    => translate('pp_tip.reset_label'),
                'question' => translate('pp_tip.reset_question'),
                'confirm'  => translate('pp_tip.reset_label'),
                'action'   => $ziel,
                'fields'   => ['csrf' => $csrf, 'action' => 'reset'],
                'danger'   => true,
                'small'    => false,
            ], null) ?>
        </div>
    <?php endif ?>
</div>
