<?php
/**
 * Die Haken ueber dem Titelfeld.
 *
 * Jeder Haken schickt seinen NAMEN mit, nicht eine Nummer: eine Nummer
 * waere nach dem Umsortieren der Liste ein anderer Tag, und das Formular
 * im Browser wuesste davon nichts.
 *
 * Welche Haken gesetzt sind, kommt aus dem Titel bei Twitch - siehe
 * Tags::active(). Nicht aus einer Einstellung: der Titel ist die einzige
 * Wahrheit darueber, was gerade drinsteht.
 *
 * @var callable $e
 * @var list<string> $tags
 * @var list<string> $active
 * @var bool $canEdit
 */
?>
<div class="si-tags" id="si-tags">
    <span class="hint"><?= $e(translate('si_tags.field')) ?></span>

    <div class="si-tags-list">
        <?php foreach ($tags as $tag): ?>
            <label class="si-tag">
                <input type="checkbox" name="si_tags[]" value="<?= $e($tag) ?>"
                       <?= in_array($tag, $active, true) ? 'checked' : '' ?>
                       <?= $canEdit ? '' : 'disabled' ?>>
                <span><?= $e($tag) ?></span>
            </label>
        <?php endforeach ?>
    </div>

    <?php /*
        Die Vorschau. Sie zeigt, was zu Twitch geht - und dass die Tags
        Platz vom Titel nehmen. Ohne sie merkt man erst nach dem
        Speichern, dass der Titel gekuerzt wurde.

        Gefuellt wird sie per JavaScript. Ohne das bleibt hier der
        Anfangswert stehen, den der Server gesetzt hat; falsch waere er
        nur, wenn man etwas aendert, ohne zu speichern.
    */ ?>
    <p class="si-tags-preview hint" id="si-tags-preview"
       data-title="streaminfo-title"
       data-max="140"
       data-over="<?= $e(translate('si_tags.too_long')) ?>"></p>
</div>
