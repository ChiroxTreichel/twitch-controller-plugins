<?php

declare(strict_types=1);

/**
 * Löschbot: bei wem die Muster nicht gelten.
 *
 * Aus der Plugin-Liste erreichbar ("Einstellungen"). Die Muster selbst
 * stehen auf der Seite des Löschbots - hier steht nur, wer davon
 * ausgenommen ist.
 *
 * @var callable $e
 * @var callable $url
 * @var array{mods: bool, subs: bool, vips: bool, followers: bool, weeks: int} $exemptions
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\DeleteBot\Exemptions;

$gesperrt = $canEdit ? '' : 'disabled';
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
    <a class="btn btn-ghost" href="<?= $e($url('/chat/delete-bot')) ?>">
        <?= $e(translate('delete_bot.to_patterns')) ?>
    </a>
</div>

<h1><?= $e(translate('delete_bot.settings')) ?></h1>
<p class="lead"><?= $e(translate('delete_bot.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $e($url('/chat/delete-bot/settings')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

    <div class="card">
        <h2><?= $e(translate('delete_bot.exempt')) ?></h2>
        <p class="hint"><?= $e(translate('delete_bot.exempt_hint')) ?></p>

        <div class="stack">
            <?php foreach ([
                'mods' => translate('delete_bot.exempt.mods'),
                'subs' => translate('delete_bot.exempt.subs'),
                'vips' => translate('delete_bot.exempt.vips'),
            ] as $schluessel => $beschriftung): ?>
                <label class="switch-field">
                    <input type="checkbox" name="exempt_<?= $e($schluessel) ?>" value="1"
                           <?= $exemptions[$schluessel] ? 'checked' : '' ?> <?= $gesperrt ?>>
                    <span class="switch-track"><span class="switch-knob"></span></span>
                    <span><?= $e($beschriftung) ?></span>
                </label>
            <?php endforeach ?>

            <?php /*
                Schalter und Zahl gehoeren zusammen: die Zahl allein
                schaltet nichts ein, und wer den Schalter ausmacht, soll
                seine Wochen beim naechsten Einschalten wiederfinden.
            */ ?>
            <div class="db-follow">
                <label class="switch-field">
                    <input type="checkbox" name="exempt_followers" value="1"
                           <?= $exemptions['followers'] ? 'checked' : '' ?> <?= $gesperrt ?>>
                    <span class="switch-track"><span class="switch-knob"></span></span>
                    <span><?= $e(translate('delete_bot.exempt.followers')) ?></span>
                </label>

                <label class="db-follow-weeks">
                    <input class="input" type="number" name="follow_weeks" inputmode="numeric"
                           min="<?= Exemptions::MIN_WEEKS ?>" max="<?= Exemptions::MAX_WEEKS ?>" step="1"
                           value="<?= $e((string) $exemptions['weeks']) ?>"
                           aria-label="<?= $e(translate('delete_bot.exempt.weeks')) ?>"
                           <?= $canEdit ? '' : 'readonly' ?>>
                    <span><?= $e(translate('delete_bot.exempt.weeks')) ?></span>
                </label>
            </div>
        </div>

        <p class="hint"><?= $e(translate('delete_bot.exempt_follow_hint')) ?></p>
    </div>

    <?php if ($canEdit): ?>
        <div class="row">
            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
        </div>
    <?php endif ?>
</form>
