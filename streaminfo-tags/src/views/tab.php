<?php
/**
 * Der Reiter "Tags" auf der Streaminfo-Seite.
 *
 * Eine Zeile, ein Feld; anhaengen und entfernen ohne JavaScript.
 *
 * Die Reihenfolge hier ist die Reihenfolge im Titel: der oberste Tag
 * steht vorn. Darum wird nicht sortiert.
 *
 * Meldungen zeigt der Rahmen der Streaminfo-Seite, nicht dieser Reiter.
 *
 * @var callable $e
 * @var callable $url
 * @var list<string> $tags     zum Bearbeiten, mit leeren Zeilen
 * @var list<string> $usable   zum Benutzen, ohne leere Zeilen
 * @var int $maxTag
 * @var int $maxTags
 * @var bool $canEdit
 * @var string $csrf
 */

$ziel = $url('/stream/info/tags');
?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('si_tags.name')) ?></h2>
    </div>

    <p class="hint"><?= $e(translate('si_tags.lead')) ?></p>

    <?php /*
        Ein Beispiel statt einer Erklaerung: was vor dem Titel steht,
        sieht man in einer Zeile schneller als in drei Saetzen. Die
        genauen Regeln - fremde Klammern, Schreibweise, Reihenfolge -
        stehen in der README des Plugins und nicht hier: auf einer Seite,
        die man im Stream benutzt, ist eine Liste von fuenf Regeln im
        Weg.
    */ ?>
    <p class="hint mono"><?= $e(translate('si_tags.example_line')) ?></p>

    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php foreach ($tags as $i => $tag): ?>
            <div class="row">
                <input class="input grow" type="text" name="tags[]"
                       maxlength="<?= $e((string) $maxTag) ?>"
                       value="<?= $e($tag) ?>"
                       placeholder="<?= $e(translate('si_tags.example')) ?>"
                       <?= $canEdit ? '' : 'disabled' ?>>

                <?php if ($canEdit): ?>
                    <button class="btn btn-ghost btn-small" type="submit"
                            name="remove" value="<?= $e((string) $i) ?>">
                        <?= $e(translate('si_tags.remove_row')) ?>
                    </button>
                <?php endif ?>
            </div>
        <?php endforeach ?>

        <?php /*
            Die Vorlage fuer eine neue Zeile - siehe assets/rows.js.
            <template> wird nicht angezeigt und nicht abgeschickt, ohne
            JavaScript ist sie also unsichtbar und ohne Wirkung.
        */ ?>
        <?php if ($canEdit): ?>
            <template data-row-template>
                <div class="row">
                    <input class="input grow" type="text" name="tags[]"
                           maxlength="<?= $e((string) $maxTag) ?>" value=""
                           placeholder="<?= $e(translate('si_tags.example')) ?>">
                </div>
            </template>
        <?php endif ?>

        <?php if ($tags === []): ?>
            <p class="hint" data-empty-hint><?= $e(translate('si_tags.empty')) ?></p>
        <?php endif ?>

        <div class="row">
            <?php if ($canEdit): ?>
                <?php if (count($tags) < $maxTags): ?>
                    <button class="btn btn-ghost btn-small" type="submit" name="add" value="1"
                            data-add-row="tags[]" data-max="<?= $e((string) $maxTags) ?>">
                        <?= $e(translate('si_tags.add_row')) ?>
                    </button>
                <?php else: ?>
                    <span class="hint"><?= $e(translate('si_tags.full', ['count' => (string) $maxTags])) ?></span>
                <?php endif ?>

                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>

            <?php /*
                Gezaehlt wird, was als Haken erscheint - nicht die
                Zeilen. Eine leere Zeile ist kein Tag.
            */ ?>
            <span class="hint"><?= $e(translate('si_tags.count', ['count' => (string) count($usable)])) ?></span>
        </div>
    </form>
</div>
