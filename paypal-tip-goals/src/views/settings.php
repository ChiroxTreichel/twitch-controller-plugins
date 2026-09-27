<?php
/**
 * Der Zugang zu PayPal.
 *
 * Nur, was zu PayPal gehoert: Zugangsdaten, Modus, Gebuehrensaetze.
 * Name, Betraege und Rechtstexte der Spendenseite stehen bei Tip-Goals.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $hasCredentials
 * @var bool $live
 * @var string $feePercent
 * @var string $feeFixed
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/display/goals/tips/paypal');
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
    <a class="btn btn-ghost" href="<?= $e($url('/display/goals/tips/settings')) ?>">
        <?= $e(translate('pp_tip.to_page')) ?>
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

        <?php /*
            Die Zugangsdaten stehen nie im Feld. Sie sind
            verschluesselt abgelegt, und ein Formular, das sie
            zurueckschreibt, waere ein Weg, sie wieder herauszuholen.

            Leer lassen heisst darum "nicht aendern" und nicht
            "loeschen" - sonst wuerfe ein Speichern der
            Gebuehrensaetze nebenbei den Zugang zum Geldkonto weg.
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

        <h3><?= $e(translate('pp_tip.fees')) ?></h3>

        <div class="row">
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
