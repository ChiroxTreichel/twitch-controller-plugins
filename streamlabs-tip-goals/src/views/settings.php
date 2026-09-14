<?php
/**
 * Die Zugangsdaten zu Streamlabs.
 *
 * Zwei Felder: das Token und die Kanal-ID. Das Token wird
 * verschluesselt abgelegt und nie wieder angezeigt - darum steht dort
 * kein Wert, sondern nur, ob eines hinterlegt ist.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $hasToken
 * @var bool $ready
 * @var string $lastError
 * @var int $pollSeconds
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/display/goals/tips/settings');
?>
<h1><?= $e(translate('sl_tip.name')) ?></h1>
<p class="lead"><?= $e(translate('sl_tip.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('sl_tip.credentials')) ?></h2>

        <span class="badge <?= $ready ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($ready ? translate('sl_tip.ready') : translate('sl_tip.not_ready')) ?>
        </span>
    </div>

    <?php if ($lastError !== ''): ?>
        <div class="note note-error">
            <strong><?= $e(translate('sl_tip.last_failed')) ?></strong>
            <span class="mono"><?= $e($lastError) ?></span>
        </div>
    <?php endif ?>

    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php /*
            Keine Kanal-ID: das Token gehoert bei Streamlabs schon zu
            einem Konto. Ein zweites Feld waere eines, das man
            ausfuellen muss, ohne dass es etwas entscheidet.
        */ ?>
        <?php /*
            Das Token steht nie im Feld. Es ist verschluesselt abgelegt,
            und ein Formular, das es zurueckschreibt, waere ein Weg, es
            wieder herauszuholen.

            Leer lassen heisst darum "nicht aendern" und nicht
            "loeschen" - sonst wuerfe ein Speichern der Kanal-ID
            nebenbei den Zugang weg.
        */ ?>
        <label class="field">
            <span class="hint"><?= $e(translate('sl_tip.token')) ?></span>
            <input class="input" type="password" name="token"
                   autocomplete="off" spellcheck="false"
                   placeholder="<?= $e($hasToken
                       ? translate('sl_tip.token_set')
                       : translate('sl_tip.token_empty')) ?>"
                   <?= $canEdit ? '' : 'readonly' ?>>
        </label>

        <p class="hint"><?= $e(translate('sl_tip.token_where')) ?></p>

        <?php if ($canEdit): ?>
            <div class="row">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>

                <?php if ($hasToken): ?>
                    <button class="btn btn-ghost" type="submit" name="action" value="forget">
                        <?= $e(translate('sl_tip.forget_token')) ?>
                    </button>
                <?php endif ?>
            </div>
        <?php endif ?>
    </form>
</div>

<div class="card">
    <h2><?= $e(translate('sl_tip.how_heading')) ?></h2>

    <p class="hint"><?= $e(translate('sl_tip.how_poll', ['seconds' => (string) $pollSeconds])) ?></p>
    <p class="hint"><?= $e(translate('sl_tip.how_first')) ?></p>
    <p class="hint"><?= $e(translate('sl_tip.how_target')) ?></p>
</div>
