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
        Der Beitrag dieser Erweiterung zum fertigen Titel.

        Ein verborgenes Feld, das assets/tags.js aktuell haelt. Die
        Vorschau gehoert Streaminfo - der fertige Titel ist seine Sache -
        und liest alle Felder mit data-title-prefix in der Reihenfolge
        des Dokuments.

        Ohne name= wird es nicht abgeschickt: was gilt, sind die Haken,
        und gerechnet wird auf dem Server. Dieses Feld ist nur fuer die
        Anzeige.
    */ ?>
    <input type="hidden" data-title-prefix value="">
</div>
