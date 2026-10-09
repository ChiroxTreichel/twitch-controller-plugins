<?php
/**
 * Eine neue Umfrage.
 *
 * Die Eintraege sind einzelne Zeilen, je eine mit "–", darunter
 * "+ Eintrag" - wie im alten System. Enter in einer Zeile legt die
 * naechste an, statt das Formular abzuschicken (polls.js).
 *
 * Bei einem Fehler kommt dieselbe Seite mit allem zurueck, was
 * eingetippt war.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, mixed> $old
 * @var list<string> $errors
 * @var bool $hasWebhook
 * @var string $csrf
 */

use TwitchController\Plugin\Polls\Polls;

$wert = static fn (string $feld, string $vorgabe = ''): string => is_scalar($old[$feld] ?? null) ? (string) $old[$feld] : $vorgabe;
$an = static fn (string $feld): bool => ($old[$feld] ?? '') !== '';
$zeitpunkt = $wert('time_mode') === 'datetime';

// Zwei leere Zeilen zum Anfangen, wie im alten System - eine Umfrage
// mit nur einem Eintrag ist selten gemeint.
$zeilen = array_values(array_filter(
    is_array($old['options'] ?? null) ? $old['options'] : [],
    static fn (mixed $z): bool => is_string($z)
));
while (count($zeilen) < 2) {
    $zeilen[] = '';
}
?>
<h1><?= $e(translate('polls.new')) ?></h1>
<p class="lead"><?= $e(translate('polls.new_lead')) ?></p>

<?php if ($errors !== []): ?>
    <div class="note note-error">
        <?php foreach ($errors as $fehler): ?>
            <div><?= $e($fehler) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<form method="post" action="<?= $e($url('/tools/polls/new')) ?>" class="polls-new">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

    <div class="card">
        <label class="field">
            <span class="hint"><?= $e(translate('polls.field.title')) ?></span>
            <input class="input" name="title" value="<?= $e($wert('title')) ?>" maxlength="<?= $e((string) Polls::MAX_TITLE) ?>"
                   placeholder="<?= $e(translate('polls.field.title_example')) ?>" required>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('polls.field.description')) ?></span>
            <textarea class="input" name="description" rows="4" maxlength="<?= $e((string) Polls::MAX_DESCRIPTION) ?>"><?= $e($wert('description')) ?></textarea>
        </label>

        <div class="field">
            <span class="hint"><?= $e(translate('polls.field.options')) ?></span>
            <div class="polls-options" data-polls-options>
                <?php foreach ($zeilen as $text): ?>
                    <div class="row polls-option" data-polls-option-row>
                        <input class="input grow" name="options[]" value="<?= $e($text) ?>" maxlength="<?= $e((string) Polls::MAX_OPTION) ?>"
                               placeholder="<?= $e(translate('polls.field.option_placeholder')) ?>"
                               aria-label="<?= $e(translate('polls.field.option_placeholder')) ?>" data-polls-option>
                        <button class="btn btn-ghost" type="button" data-polls-remove
                                title="<?= $e(translate('polls.remove_option')) ?>" aria-label="<?= $e(translate('polls.remove_option')) ?>">&minus;</button>
                    </div>
                <?php endforeach ?>
            </div>
            <button class="btn btn-ghost polls-add" type="button" data-polls-add><?= $e(translate('polls.add_option')) ?></button>
        </div>

        <label class="field polls-narrow">
            <span class="hint"><?= $e(translate('polls.field.max_choices')) ?></span>
            <input class="input" type="number" name="max_choices" min="1" max="<?= $e((string) Polls::MAX_OPTIONS) ?>"
                   value="<?= $e($wert('max_choices', '1')) ?>" required>
        </label>

        <label class="switch-field">
            <input type="checkbox" name="suggestions" value="1" <?= $an('suggestions') ? 'checked' : '' ?>>
            <span class="switch-track"><span class="switch-knob"></span></span>
            <span><?= $e(translate('polls.field.suggestions')) ?></span>
        </label>
    </div>

    <div class="card">
        <h2><?= $e(translate('polls.field.end')) ?></h2>

        <div class="row polls-modes">
            <label class="row">
                <input type="radio" name="time_mode" value="duration" <?= $zeitpunkt ? '' : 'checked' ?> data-polls-mode>
                <?= $e(translate('polls.field.duration')) ?>
            </label>
            <label class="row">
                <input type="radio" name="time_mode" value="datetime" <?= $zeitpunkt ? 'checked' : '' ?> data-polls-mode>
                <?= $e(translate('polls.field.datetime')) ?>
            </label>
        </div>

        <div class="row" data-polls-show="duration" <?= $zeitpunkt ? 'hidden' : '' ?>>
            <?php foreach (['days' => translate('polls.field.days'), 'hours' => translate('polls.field.hours'), 'minutes' => translate('polls.field.minutes')] as $feld => $beschriftung): ?>
                <label class="field polls-narrow">
                    <span class="hint"><?= $e($beschriftung) ?></span>
                    <input class="input" type="number" name="<?= $e($feld) ?>" min="0" value="<?= $e($wert($feld, '0')) ?>">
                </label>
            <?php endforeach ?>
        </div>

        <div data-polls-show="datetime" <?= $zeitpunkt ? '' : 'hidden' ?>>
            <label class="field polls-narrow">
                <span class="hint"><?= $e(translate('polls.field.ends_at')) ?></span>
                <input class="input" type="datetime-local" name="ends_at" value="<?= $e($wert('ends_at')) ?>">
            </label>
        </div>
    </div>

    <div class="card">
        <h2><?= $e(translate('polls.field.announce')) ?></h2>

        <?php if (!$hasWebhook): ?>
            <p class="hint">
                <?php // Ohne $e: der Platzhalter ist eigenes Markup. ?>
                <?= translate('polls.no_webhook_hint', [
                    'link' => '<a href="' . $e($url('/tools/polls/settings')) . '">' . $e(translate('polls.settings')) . '</a>',
                ]) ?>
            </p>
        <?php endif ?>

        <div class="stack">
            <?php foreach ([
                'announce_discord_new'    => [translate('polls.field.discord_new'), !$hasWebhook],
                'announce_discord_result' => [translate('polls.field.discord_result'), !$hasWebhook],
                'announce_chat_new'       => [translate('polls.field.chat_new'), false],
                'announce_chat_result'    => [translate('polls.field.chat_result'), false],
            ] as $feld => [$beschriftung, $aus]): ?>
                <label class="switch-field">
                    <input type="checkbox" name="<?= $e($feld) ?>" value="1" <?= $an($feld) && !$aus ? 'checked' : '' ?> <?= $aus ? 'disabled' : '' ?>>
                    <span class="switch-track"><span class="switch-knob"></span></span>
                    <span><?= $e($beschriftung) ?></span>
                </label>
            <?php endforeach ?>
        </div>
    </div>

    <div class="row">
        <button class="btn" type="submit"><?= $e(translate('polls.create')) ?></button>
        <a class="btn btn-ghost" href="<?= $e($url('/tools/polls')) ?>"><?= $e(translate('common.cancel')) ?></a>
    </div>
</form>
