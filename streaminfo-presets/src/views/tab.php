<?php
/**
 * Der Reiter "Vorlagen" auf der Streaminfo-Seite.
 *
 * Eine Zeile, ein Feld - wie beim Loeschbot und bei den Timern. Kein
 * Textfeld mit einem Titel pro Zeile: was man anklicken und einzeln
 * bearbeiten kann, verwechselt man nicht.
 *
 * Anhaengen und Entfernen laufen ueber das Formular, nicht ueber
 * JavaScript. Das Formular kommt vollstaendig an, die Liste wird auf dem
 * Server veraendert und zurueckgeschickt - damit funktioniert die Seite
 * auch dann, wenn ein Skript fehlt.
 *
 * Meldungen zeigt der Rahmen der Streaminfo-Seite, nicht dieser Reiter.
 *
 * @var callable $e
 * @var callable $url
 * @var list<string> $presets   zum Bearbeiten, mit leeren Zeilen
 * @var list<string> $usable    zum Benutzen, ohne leere Zeilen
 * @var int $maxTitle
 * @var int $maxPresets
 * @var bool $canEdit
 * @var string $csrf
 */

$ziel = $url('/stream/info/presets');
?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('si_presets.name')) ?></h2>
    </div>

    <p class="hint"><?= $e(translate('si_presets.lead')) ?></p>

    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <div class="streaminfo-rows">
        <?php foreach ($presets as $i => $vorlage): ?>
            <div class="row">
                <input class="input grow" type="text" name="presets[]"
                       maxlength="<?= $e((string) $maxTitle) ?>"
                       value="<?= $e($vorlage) ?>"
                       placeholder="<?= $e(translate('si_presets.example')) ?>"
                       <?= $canEdit ? '' : 'disabled' ?>>

                <?php if ($canEdit): ?>
                    <button class="btn btn-ghost btn-small" type="submit"
                            name="remove" value="<?= $e((string) $i) ?>">
                        <?= $e(translate('si_presets.remove_row')) ?>
                    </button>
                <?php endif ?>
            </div>
        <?php endforeach ?>

        <?php /*
            Die Vorlage fuer eine neue Zeile. Sie steht HIER und nicht im
            Skript: so bringt eine neue Zeile Klassen, Grenze und
            Platzhalter von selbst mit, und beides laeuft nicht
            auseinander.

            <template> wird nicht angezeigt und nicht abgeschickt - ohne
            JavaScript ist sie also unsichtbar und ohne Wirkung.
        */ ?>
        <?php if ($canEdit): ?>
            <template data-row-template>
                <div class="row">
                    <input class="input grow" type="text" name="presets[]"
                           maxlength="<?= $e((string) $maxTitle) ?>" value=""
                           placeholder="<?= $e(translate('si_presets.example')) ?>">
                </div>
            </template>
        <?php endif ?>

        <?php if ($presets === []): ?>
            <p class="hint" data-empty-hint><?= $e(translate('si_presets.empty')) ?></p>
        <?php endif ?>

        </div>

        <div class="row">
            <?php if ($canEdit): ?>
                <?php /*
                    Anhaengen nur bis zur Grenze. Ein Knopf, der still
                    nichts tut, ist schlimmer als einer, der fehlt.
                */ ?>
                <?php if (count($presets) < $maxPresets): ?>
                    <button class="btn btn-ghost btn-small" type="submit" name="add" value="1"
                            data-add-row="presets[]" data-max="<?= $e((string) $maxPresets) ?>">
                        <?= $e(translate('si_presets.add_row')) ?>
                    </button>
                <?php else: ?>
                    <span class="hint"><?= $e(translate('si_presets.full', ['count' => (string) $maxPresets])) ?></span>
                <?php endif ?>

                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>

            <?php /*
                Gezaehlt wird, was in der Auswahl landet - nicht die
                Zeilen. Eine leere Zeile ist keine Vorlage, und wer sie
                mitzaehlte, wuerde sich fragen, wo sie geblieben ist.
            */ ?>
            <span class="hint"><?= $e(translate('si_presets.count', ['count' => (string) count($usable)])) ?></span>
        </div>
    </form>
</div>
