<?php
/**
 * Die gespeicherten Stream-Titel pflegen.
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
 * @var callable $e
 * @var callable $url
 * @var list<string> $presets
 * @var int $maxTitle
 * @var int $maxPresets
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/stream/info/presets');
?>
<h1><?= $e(translate('si_presets.name')) ?></h1>
<p class="lead"><?= $e(translate('si_presets.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

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

        <?php if ($presets === []): ?>
            <p class="hint"><?= $e(translate('si_presets.empty')) ?></p>
        <?php endif ?>

        <div class="row">
            <?php if ($canEdit): ?>
                <?php /*
                    Anhaengen nur bis zur Grenze. Ein Knopf, der still
                    nichts tut, ist schlimmer als einer, der fehlt.
                */ ?>
                <?php if (count($presets) < $maxPresets): ?>
                    <button class="btn btn-ghost btn-small" type="submit" name="add" value="1">
                        <?= $e(translate('si_presets.add_row')) ?>
                    </button>
                <?php else: ?>
                    <span class="hint"><?= $e(translate('si_presets.full', ['count' => (string) $maxPresets])) ?></span>
                <?php endif ?>

                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>

            <span class="hint"><?= $e(translate('si_presets.count', ['count' => (string) count($presets)])) ?></span>
        </div>
    </form>
</div>

<div class="card">
    <h2><?= $e(translate('si_presets.rules')) ?></h2>
    <ul class="hint">
        <li><?= $e(translate('si_presets.rule_empty')) ?></li>
        <li><?= $e(translate('si_presets.rule_double')) ?></li>
        <li><?= $e(translate('si_presets.rule_order')) ?></li>
        <li><?= $e(translate('si_presets.rule_length', ['count' => (string) $maxTitle])) ?></li>
    </ul>

    <div class="row">
        <a class="btn btn-ghost btn-small" href="<?= $e($url('/stream/info')) ?>">
            <?= $e(translate('si_presets.to_page')) ?>
        </a>
    </div>
</div>
