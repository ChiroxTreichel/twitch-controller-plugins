<?php
/**
 * Die Einrichtung: die Seite, Impressum, Datenschutz, AGB.
 *
 * Die Zahlungsanbieter werden in ihren eigenen Plugins eingerichtet;
 * hier steht nur, welche es gibt.
 *
 * Vier Reiter auf EINER Seite. Wer das Impressum schreibt, will danach
 * die AGB schreiben - und soll dafuer nicht zwei Ebenen hoch
 * navigieren.
 *
 * @var callable $e
 * @var callable $url
 * @var string $tab
 * @var array<string, string> $pages    Schluessel => Sprachschluessel
 * @var string $text                    der Rechtstext des offenen Reiters
 * @var int $maxLength
 * @var array<string, array{key: string, label: string, ready: bool, settings: string}> $providers
 * @var string $brand
 * @var string $presets
 * @var string $minAmount
 * @var string $defaultAmount
 * @var bool $pageReady
 * @var string $publicUrl
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\TipGoals\Legal;

$ziel = $url('/display/goals/tips/settings');
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
    <a class="btn btn-ghost" href="<?= $e($url('/display/goals/tips')) ?>">
        <?= $e(translate('tips.to_goals')) ?>
    </a>
</div>

<h1><?= $e(translate('tips.settings')) ?></h1>
<p class="lead"><?= $e(translate('tips.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /*
    Ohne Impressum bleibt die oeffentliche Seite zu. Das steht hier
    oben und nicht versteckt im Reiter: es ist die Bedingung, an der
    die ganze Seite haengt.
*/ ?>
<?php if (!$pageReady): ?>
    <div class="note note-warn"><?= $e(translate('tips.needs_imprint')) ?></div>
<?php endif ?>

<div class="tabs">
    <a class="tab<?= $tab === 'page' ? ' is-active' : '' ?>"
       href="<?= $e($ziel . '/page') ?>"><?= $e(translate('tips.tab.page')) ?></a>

    <?php foreach ($pages as $schluessel => $titel): ?>
        <a class="tab<?= $tab === $schluessel ? ' is-active' : '' ?>"
           href="<?= $e($ziel . '/' . rawurlencode($schluessel)) ?>"><?= $e(Legal::title($schluessel)) ?></a>
    <?php endforeach ?>
</div>

<?php if ($tab === 'page'): ?>
    <?php /*
        Die Zahlungsanbieter. Eingerichtet werden sie in IHREM Plugin -
        hier steht nur, welche es gibt und ob sie gerade annehmen. Ohne
        einen einzigen bleibt die Spendenseite zu.
    */ ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('tips.providers')) ?></h2>
        </div>

        <?php if ($providers === []): ?>
            <div class="note note-warn"><?= $e(translate('tips.needs_provider')) ?></div>
        <?php else: ?>
            <?php foreach ($providers as $anbieter): ?>
                <div class="row">
                    <span class="grow"><?= $e($anbieter['label']) ?></span>

                    <span class="badge <?= $anbieter['ready'] ? 'badge-ok' : 'badge-off' ?>">
                        <?= $e($anbieter['ready']
                            ? translate('tips.provider.ready')
                            : translate('tips.provider.not_ready')) ?>
                    </span>

                    <?php if ($anbieter['settings'] !== ''): ?>
                        <a class="btn btn-small btn-ghost" href="<?= $e($url($anbieter['settings'])) ?>">
                            <?= $e(translate('tips.provider.setup')) ?>
                        </a>
                    <?php endif ?>
                </div>
            <?php endforeach ?>

            <p class="hint"><?= $e(translate('tips.providers_hint')) ?></p>
        <?php endif ?>
    </div>

    <div class="card">
        <h2><?= $e(translate('tips.amounts')) ?></h2>

        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="tab" value="page">

            <div class="row">
                <label class="field grow">
                    <span class="hint"><?= $e(translate('tips.brand')) ?></span>
                    <input class="input" type="text" name="brand" maxlength="80"
                           value="<?= $e($brand) ?>"
                           placeholder="<?= $e(translate('tips.brand_default')) ?>"
                           <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('tips.presets')) ?></span>
                    <input class="input" type="text" name="presets" maxlength="80"
                           value="<?= $e($presets) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            </div>

            <div class="row">
                <label class="field">
                    <span class="hint"><?= $e(translate('tips.min_amount')) ?></span>
                    <input class="input" type="number" step="0.01" min="0.5" name="min_amount"
                           value="<?= $e($minAmount) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('tips.default_amount')) ?></span>
                    <input class="input" type="number" step="0.01" min="0" name="default_amount"
                           value="<?= $e($defaultAmount) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            </div>

            <?php if ($canEdit): ?>
                <div class="row">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                </div>
            <?php endif ?>
        </form>
    </div>

<?php else: ?>
    <div class="card">
        <h2><?= $e(Legal::title($tab)) ?></h2>

        <?php /*
            Diese Texte liefert das Plugin NICHT mit. In einem Impressum
            steht der Name dessen, der die Seite betreibt - ein Plugin
            aus dem Katalog, das eines mitbraechte, verbreitete fremde
            Angaben bei jedem, der es installiert.
        */ ?>
        <p class="hint"><?= $e(translate('tips.legal_hint')) ?></p>

        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="tab" value="<?= $e($tab) ?>">

            <label class="field">
                <span class="hint"><?= $e(translate('tips.legal_field', [
                    'max' => (string) $maxLength,
                ])) ?></span>
                <textarea class="input tip-code" name="text" rows="20"
                          spellcheck="true" <?= $canEdit ? '' : 'disabled' ?>><?= $e($text) ?></textarea>
            </label>

            <p class="hint"><?= $e(translate('tips.legal_markdown')) ?></p>

            <?php if ($canEdit): ?>
                <div class="row">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>

                    <?php if ($text !== ''): ?>
                        <a class="btn btn-ghost" target="_blank" rel="noopener"
                           href="<?= $e($publicUrl . '/' . rawurlencode($tab)) ?>">
                            <?= $e(translate('tips.legal_preview')) ?>
                        </a>
                    <?php endif ?>
                </div>
            <?php endif ?>
        </form>
    </div>
<?php endif ?>
