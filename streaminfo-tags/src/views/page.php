<?php
/**
 * Die eigenen Tags festlegen.
 *
 * Eine Zeile, ein Feld - wie beim Loeschbot und bei den Timern.
 * Anhaengen und Entfernen laufen ueber das Formular, nicht ueber
 * JavaScript.
 *
 * Die Reihenfolge hier ist die Reihenfolge im Titel: der oberste Tag
 * steht vorn. Darum wird nicht sortiert.
 *
 * @var callable $e
 * @var callable $url
 * @var list<string> $tags
 * @var int $maxTag
 * @var int $maxTags
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/stream/info/tags');
?>
<h1><?= $e(translate('si_tags.name')) ?></h1>
<p class="lead"><?= $e(translate('si_tags.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
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

        <?php if ($tags === []): ?>
            <p class="hint"><?= $e(translate('si_tags.empty')) ?></p>
        <?php endif ?>

        <div class="row">
            <?php if ($canEdit): ?>
                <?php if (count($tags) < $maxTags): ?>
                    <button class="btn btn-ghost btn-small" type="submit" name="add" value="1">
                        <?= $e(translate('si_tags.add_row')) ?>
                    </button>
                <?php else: ?>
                    <span class="hint"><?= $e(translate('si_tags.full', ['count' => (string) $maxTags])) ?></span>
                <?php endif ?>

                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>

            <span class="hint"><?= $e(translate('si_tags.count', ['count' => (string) count($tags)])) ?></span>
        </div>
    </form>
</div>

<div class="card">
    <h2><?= $e(translate('si_tags.how')) ?></h2>

    <?php /*
        Ein Beispiel statt einer Erklaerung: was vor dem Titel steht,
        sieht man in einer Zeile schneller als in drei Saetzen.
    */ ?>
    <pre class="mono"><?= $e(translate('si_tags.example_result')) ?></pre>

    <ul class="hint">
        <li><?= $e(translate('si_tags.rule_order')) ?></li>
        <li><?= $e(translate('si_tags.rule_brackets')) ?></li>
        <li><?= $e(translate('si_tags.rule_foreign')) ?></li>
        <li><?= $e(translate('si_tags.rule_case')) ?></li>
        <li><?= $e(translate('si_tags.rule_length', ['count' => (string) $maxTag])) ?></li>
    </ul>

    <div class="row">
        <a class="btn btn-ghost btn-small" href="<?= $e($url('/stream/info')) ?>">
            <?= $e(translate('si_tags.to_page')) ?>
        </a>
    </div>
</div>
