<?php
/**
 * Die Auswahlliste ueber dem Titelfeld.
 *
 * Sie hat KEINEN name= - sie wird nicht abgeschickt. Ihr Wert wandert
 * per JavaScript in das Titelfeld daneben, und von dort geht er wie
 * jeder getippte Titel hinaus. Zwei Felder, die beide "title" heissen,
 * waeren im Formular nicht auseinanderzuhalten, und welches gewinnt,
 * haengt dann an der Reihenfolge im HTML.
 *
 * @var callable $e
 * @var list<string> $presets
 * @var string $current   Der Titel, der gerade laeuft
 * @var bool $canEdit
 */
?>
<div class="row">
    <label class="field grow">
        <span class="hint"><?= $e(translate('si_presets.field')) ?></span>
        <select class="input" id="streaminfo-preset" data-target="streaminfo-title"
                <?= $canEdit ? '' : 'disabled' ?>>
            <?php /*
                Der erste Eintrag ist kein Titel, sondern der Hinweis,
                dass gerade keine Vorlage laeuft. Er ist waehlbar und
                nicht gesperrt: wer ihn nimmt, will selbst tippen.
            */ ?>
            <option value="" <?= in_array($current, $presets, true) ? '' : 'selected' ?>>
                <?= $e(translate('si_presets.own_text')) ?>
            </option>
            <?php foreach ($presets as $vorlage): ?>
                <option value="<?= $e($vorlage) ?>" <?= $vorlage === $current ? 'selected' : '' ?>>
                    <?= $e($vorlage) ?>
                </option>
            <?php endforeach ?>
        </select>
    </label>
</div>
