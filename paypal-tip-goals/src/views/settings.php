<?php
/**
 * Die Einrichtung: PayPal, Impressum, Datenschutz, AGB.
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
 * @var bool $hasCredentials
 * @var bool $live
 * @var string $brand
 * @var string $presets
 * @var string $minAmount
 * @var string $defaultAmount
 * @var string $feePercent
 * @var string $feeFixed
 * @var bool $pageReady
 * @var string $publicUrl
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\PaypalTipGoals\Legal;

$ziel = $url('/display/goals/tips/settings');
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
    <a class="btn btn-ghost" href="<?= $e($url('/display/goals/tips')) ?>">
        <?= $e(translate('pp_tip.to_goals')) ?>
    </a>
</div>

<h1><?= $e(translate('pp_tip.settings')) ?></h1>
<p class="lead"><?= $e(translate('pp_tip.settings_lead')) ?></p>

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
    <div class="note note-warn"><?= $e(translate('pp_tip.needs_imprint')) ?></div>
<?php endif ?>

<div class="tabs">
    <a class="tab<?= $tab === 'paypal' ? ' is-active' : '' ?>"
       href="<?= $e($ziel . '/paypal') ?>"><?= $e(translate('pp_tip.tab.paypal')) ?></a>

    <?php foreach ($pages as $schluessel => $titel): ?>
        <a class="tab<?= $tab === $schluessel ? ' is-active' : '' ?>"
           href="<?= $e($ziel . '/' . rawurlencode($schluessel)) ?>"><?= $e(Legal::title($schluessel)) ?></a>
    <?php endforeach ?>
</div>

<?php if ($tab === 'paypal'): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('pp_tip.credentials')) ?></h2>

            <span class="badge <?= $hasCredentials ? ($live ? 'badge-ok' : 'badge-warn') : 'badge-off' ?>">
                <?= $e($hasCredentials
                    ? ($live ? translate('pp_tip.mode.live') : translate('pp_tip.mode.sandbox'))
                    : translate('pp_tip.mode.none')) ?>
            </span>
        </div>

        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="tab" value="paypal">

            <?php /*
                Die Zugangsdaten stehen nie im Feld. Sie sind
                verschluesselt abgelegt, und ein Formular, das sie
                zurueckschreibt, waere ein Weg, sie wieder herauszuholen.

                Leer lassen heisst darum "nicht aendern" und nicht
                "loeschen" - sonst wuerfe ein Speichern der
                Vorgabebetraege nebenbei den Zugang zum Geldkonto weg.
            */ ?>
            <label class="field">
                <span class="hint"><?= $e(translate('pp_tip.client_id')) ?></span>
                <input class="input" type="password" name="client_id"
                       autocomplete="off" spellcheck="false"
                       placeholder="<?= $e($hasCredentials
                           ? translate('pp_tip.secret_set')
                           : translate('pp_tip.secret_empty')) ?>"
                       <?= $canEdit ? '' : 'readonly' ?>>
            </label>

            <label class="field">
                <span class="hint"><?= $e(translate('pp_tip.client_secret')) ?></span>
                <input class="input" type="password" name="secret"
                       autocomplete="off" spellcheck="false"
                       placeholder="<?= $e($hasCredentials
                           ? translate('pp_tip.secret_set')
                           : translate('pp_tip.secret_empty')) ?>"
                       <?= $canEdit ? '' : 'readonly' ?>>
            </label>

            <p class="hint"><?= $e(translate('pp_tip.credentials_where')) ?></p>

            <?php /*
                Echtbetrieb ist NICHT die Vorgabe. Wer eine Spendenseite
                einrichtet, soll erst mit dem Testkonto sehen, dass der
                Weg funktioniert - und dann bewusst umschalten.
            */ ?>
            <label class="field">
                <span class="hint"><?= $e(translate('pp_tip.mode')) ?></span>
                <select class="input" name="live" <?= $canEdit ? '' : 'disabled' ?>>
                    <option value="0" <?= $live ? '' : 'selected' ?>><?= $e(translate('pp_tip.mode.sandbox')) ?></option>
                    <option value="1" <?= $live ? 'selected' : '' ?>><?= $e(translate('pp_tip.mode.live')) ?></option>
                </select>
            </label>

            <h3><?= $e(translate('pp_tip.amounts')) ?></h3>

            <div class="row">
                <label class="field grow">
                    <span class="hint"><?= $e(translate('pp_tip.brand')) ?></span>
                    <input class="input" type="text" name="brand" maxlength="80"
                           value="<?= $e($brand) ?>"
                           placeholder="<?= $e(translate('pp_tip.brand_default')) ?>"
                           <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('pp_tip.presets')) ?></span>
                    <input class="input" type="text" name="presets" maxlength="80"
                           value="<?= $e($presets) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            </div>

            <div class="row">
                <label class="field">
                    <span class="hint"><?= $e(translate('pp_tip.min_amount')) ?></span>
                    <input class="input" type="number" step="0.01" min="0.5" name="min_amount"
                           value="<?= $e($minAmount) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('pp_tip.default_amount')) ?></span>
                    <input class="input" type="number" step="0.01" min="0" name="default_amount"
                           value="<?= $e($defaultAmount) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('pp_tip.fee_percent')) ?></span>
                    <input class="input" type="number" step="0.01" min="0" name="fee_percent"
                           value="<?= $e($feePercent) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('pp_tip.fee_fixed')) ?></span>
                    <input class="input" type="number" step="0.01" min="0" name="fee_fixed"
                           value="<?= $e($feeFixed) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            </div>

            <p class="hint"><?= $e(translate('pp_tip.fee_hint')) ?></p>

            <?php if ($canEdit): ?>
                <div class="row">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>

                    <?php if ($hasCredentials): ?>
                        <button class="btn btn-ghost" type="submit" name="action" value="forget">
                            <?= $e(translate('pp_tip.forget_credentials')) ?>
                        </button>
                    <?php endif ?>
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
        <p class="hint"><?= $e(translate('pp_tip.legal_hint')) ?></p>

        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="tab" value="<?= $e($tab) ?>">

            <label class="field">
                <span class="hint"><?= $e(translate('pp_tip.legal_field', [
                    'max' => (string) $maxLength,
                ])) ?></span>
                <textarea class="input tip-code" name="text" rows="20"
                          spellcheck="true" <?= $canEdit ? '' : 'disabled' ?>><?= $e($text) ?></textarea>
            </label>

            <p class="hint"><?= $e(translate('pp_tip.legal_markdown')) ?></p>

            <?php if ($canEdit): ?>
                <div class="row">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>

                    <?php if ($text !== ''): ?>
                        <a class="btn btn-ghost" target="_blank" rel="noopener"
                           href="<?= $e($publicUrl . '/' . rawurlencode($tab)) ?>">
                            <?= $e(translate('pp_tip.legal_preview')) ?>
                        </a>
                    <?php endif ?>
                </div>
            <?php endif ?>
        </form>
    </div>
<?php endif ?>
