<?php
/**
 * Die Einstellungen: die Nachrichtenvorlage fuer den Chat.
 *
 * Welche Kanaele dieses Ziel benutzen, steht nicht hier - das ist der
 * Haken in der Kanalzeile auf der Live-Benachrichtigungs-Seite.
 *
 * @var callable $e
 * @var callable $url
 * @var string $message
 * @var string $defaultText
 * @var int $maxMessage
 * @var list<string> $placeholders
 * @var bool $canSend
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/networking/live/chat');
?>
<h1><?= $e(translate('ln_chat.name')) ?></h1>
<p class="lead"><?= $e(translate('ln_chat.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if (!$canSend): ?>
    <div class="note note-warn">
        <strong><?= $e(translate('ln_chat.no_sender')) ?></strong>
        <?= $e(translate('ln_chat.no_sender_hint')) ?>
    </div>
<?php endif ?>

<div class="card">
    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('ln_chat.message')) ?></span>
                <textarea class="input" name="message" rows="2"
                          maxlength="<?= $e((string) $maxMessage) ?>"
                          placeholder="<?= $e($defaultText) ?>"
                          <?= $canEdit ? '' : 'disabled' ?>><?= $e($message) ?></textarea>
            </label>
        </div>

        <p class="hint">
            <?= $e(translate('ln_chat.placeholders')) ?>
            <?php foreach ($placeholders as $platzhalter): ?>
                <span class="mono">{{<?= $e($platzhalter) ?>}}</span>
            <?php endforeach ?>
        </p>

        <?php /*
            Die Regel, die dieses Ziel von den anderen unterscheidet -
            und sie steht hier, weil sie sonst wie ein Fehler aussieht:
            man haekelt das Ziel an, es passiert nichts, und der Grund
            steht nur im Log.
        */ ?>
        <p class="hint"><?= $e(translate('ln_chat.only_when_live')) ?></p>

        <?php if ($canEdit): ?>
            <div class="row">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                <a class="btn btn-ghost" href="<?= $e($url('/networking/live')) ?>">
                    <?= $e(translate('ln_chat.to_channels')) ?>
                </a>
            </div>
        <?php endif ?>
    </form>
</div>
