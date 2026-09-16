<?php
/**
 * Die Einrichtung: der oeffentliche Schluessel und die Adresse, die
 * bei Throne eingetragen wird.
 *
 * @var \TwitchController\Core\Http\View $view
 * @var callable $e
 * @var callable $url
 * @var bool $hasKey
 * @var string $webhookUrl
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */
?>
<h1><?= $e(translate('throne.name')) ?></h1>
<p class="lead"><?= $e(translate('throne.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if (!$hasKey): ?>
    <div class="note note-warn"><?= $e(translate('throne.needs_key')) ?></div>
<?php endif ?>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('throne.webhook')) ?></h2>
        <span class="badge <?= $hasKey ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($hasKey ? translate('throne.ready') : translate('throne.not_ready')) ?>
        </span>
    </div>

    <p class="hint"><?= $e(translate('throne.webhook_hint')) ?></p>

    <?php /*
        Die Adresse steht zum Ablesen da und nicht als Link: sie
        gehoert in ein Feld bei Throne, nicht in einen Browser. Wer
        sie hier anklickt, bekaeme eine leere Antwort und wuerde sich
        wundern.
    */ ?>
    <p class="mono" style="background:var(--bg);padding:10px 12px;border-radius:9px;border:1px solid var(--line);">
        <?= $e($webhookUrl) ?>
    </p>
</div>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('throne.key')) ?></h2>
    </div>

    <p class="hint"><?= $e(translate('throne.key_hint')) ?></p>

    <?php /*
        Das Formular traegt eine id, und der Speichern-Knopf steht
        DRAUSSEN und gehoert ueber form="..." dazu.

        Grund: der Knopf "Schluessel loeschen" ist eine Rueckfrage, und
        die bringt ihr eigenes Formular mit. Beide in einer Zeile hiesse
        ein Formular im anderen - ungueltiges HTML. Der Browser wirft
        das innere Start-Tag weg und haengt dessen Felder an das
        aeussere; dann gaebe es zwei "action"-Felder, und ein Klick auf
        "Speichern" loeschte den Schluessel.
    */ ?>
    <form id="throne-key" method="post" action="<?= $e($url('/networking/throne')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php /*
            Ein oeffentlicher Schluessel ist kein Geheimnis - trotzdem
            steht er hier nie im Feld. Wer ihn austauschen kann, kann
            sich eigene Ereignisse unterschreiben; er gehoert also
            geschuetzt, nur nicht vor dem Lesen.

            Leer heisst deshalb "nicht aendern" und nicht "loeschen".
        */ ?>
        <label class="field">
            <span class="hint"><?= $e(translate('throne.key_field')) ?></span>
            <input class="input" type="text" name="public_key"
                   autocomplete="off" spellcheck="false"
                   placeholder="<?= $e($hasKey
                       ? translate('throne.key_set')
                       : translate('throne.key_empty')) ?>"
                <?= $canEdit ? '' : 'readonly' ?>>
        </label>
    </form>

    <?php if ($canEdit): ?>
        <div class="row">
            <button class="btn" type="submit" form="throne-key">
                <?= $e(translate('common.save')) ?>
            </button>

            <?php if ($hasKey): ?>
                <?= $view->render('_confirm', [
                    'label'    => translate('throne.forget_key'),
                    'question' => translate('throne.forget_key_question'),
                    'confirm'  => translate('throne.forget_key_yes'),
                    'action'   => $url('/networking/throne'),
                    'fields'   => ['csrf' => $csrf, 'action' => 'forget'],
                    'danger'   => true,
                    'right'    => true,
                ], null) ?>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>
