<?php
/**
 * Der Reiter "Throne" auf der Alerts-Seite.
 *
 * Drei Faelle mit je eigenem Text, Video, Ton und Dauer - genau wie im
 * alten System. Ein Sammelziel ist etwas anderes als ein Geschenk und
 * darf auch anders aussehen.
 *
 * Aufbau wie die Reiter daneben: derselbe Kasten, derselbe Schalter
 * oben rechts, dieselben Felder.
 *
 * @var callable $e
 * @var array{enabled: bool, cases: array<string, array{text: string, video: string, audio: string, duration: int}>} $config
 * @var array<string, string> $cases
 * @var list<string> $placeholders
 * @var array<string, string> $preview
 * @var string $target
 * @var bool $ready
 * @var bool $canEdit
 * @var int $defaultDuration
 * @var int $maxText
 * @var string $csrf
 */

/**
 * Ein Feld für eine Datei-Adresse. Der Knopf löst die verborgene
 * Dateiauswahl aus; verdrahtet ist das im Kern (layout.php).
 * Hochgeladen wird nichts - die Datei muss schon auf dem Server liegen.
 */
$dateifeld = static function (string $name, string $wert, string $accept) use ($e, $canEdit): void {
    $id = 'f-' . substr(hash('crc32b', $name), 0, 8);
    ?>
    <div class="file-field">
        <input class="input" type="text" name="<?= $e($name) ?>" value="<?= $e($wert) ?>"
               placeholder="/uploads/alerts/…"
            <?= $canEdit ? '' : 'readonly' ?>>
        <?php if ($canEdit): ?>
            <button class="file-field-button" type="button"
                    data-file-trigger="<?= $e($id) ?>"
                    title="<?= $e(translate('throne.alert.choose_file')) ?>">↑</button>
            <input class="file-field-native" id="<?= $e($id) ?>" type="file" accept="<?= $e($accept) ?>">
        <?php endif ?>
    </div>
    <?php
};
?>
<div class="card">
    <div class="head-row">
        <h2 style="margin:0;"><?= $e(translate('throne.name')) ?></h2>

        <?php if ($canEdit): ?>
            <form method="post" action="<?= $e($target) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="btn btn-small<?= $config['enabled'] ? '' : ' btn-ghost' ?>" type="submit">
                    <?= $e($config['enabled']
                        ? translate('throne.alert.on')
                        : translate('throne.alert.off')) ?>
                </button>
            </form>
        <?php else: ?>
            <span class="badge <?= $config['enabled'] ? 'badge-ok' : 'badge-off' ?>">
                <?= $e($config['enabled'] ? translate('throne.alert.on') : translate('throne.alert.off')) ?>
            </span>
        <?php endif ?>
    </div>

    <?php /*
        Ohne Schluessel kommt gar nichts an. Der Hinweis steht hier und
        nicht nur auf der Einrichtungsseite: wer den Alert einstellt und
        dann vergeblich wartet, sucht den Fehler beim Text.
    */ ?>
    <?php if (!$ready): ?>
        <div class="note note-warn"><?= $e(translate('throne.alert.needs_key')) ?></div>
    <?php endif ?>

    <p class="hint">
        <?= $e(translate('throne.alert.placeholders')) ?>
        <?php foreach ($placeholders as $platzhalter): ?>
            <code>{{ <?= $e($platzhalter) ?> }}</code>
        <?php endforeach ?>
    </p>

    <form method="post" action="<?= $e($target) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">

        <?php foreach ($cases as $schluessel => $beschriftung): ?>
            <?php $fall = $config['cases'][$schluessel] ?? ['text' => '', 'video' => '', 'audio' => '', 'duration' => 0]; ?>
            <details class="case"<?= array_key_first($cases) === $schluessel ? ' open' : '' ?>>
                <summary><?= $e($beschriftung) ?></summary>

                <div class="case-body">
                    <label class="field">
                        <span class="hint"><?= $e(translate('throne.alert.text')) ?></span>
                        <input class="input" type="text" maxlength="<?= (int) $maxText ?>"
                               name="cases[<?= $e($schluessel) ?>][text]" value="<?= $e($fall['text']) ?>"
                            <?= $canEdit ? '' : 'readonly' ?>>
                    </label>

                    <div class="field">
                        <span class="hint"><?= $e(translate('throne.alert.video')) ?></span>
                        <?php $dateifeld('cases[' . $schluessel . '][video]', $fall['video'], 'video/*'); ?>
                    </div>

                    <div class="field">
                        <span class="hint"><?= $e(translate('throne.alert.audio')) ?></span>
                        <?php $dateifeld('cases[' . $schluessel . '][audio]', $fall['audio'], 'audio/*'); ?>
                    </div>

                    <?php /*
                        Leer heisst "die Vorgabe von Alerts". Darum steht
                        sie als Platzhalter im Feld und nicht als Wert -
                        ein eingetragener Wert liesse sich nie wieder auf
                        "Vorgabe" zuruecksetzen.
                    */ ?>
                    <label class="field">
                        <span class="hint"><?= $e(translate('throne.alert.duration')) ?></span>
                        <input class="input" type="number" min="0" max="120" step="1"
                               name="cases[<?= $e($schluessel) ?>][duration]"
                               value="<?= $fall['duration'] > 0 ? (int) $fall['duration'] : '' ?>"
                               placeholder="<?= (int) $defaultDuration ?>"
                            <?= $canEdit ? '' : 'readonly' ?>>
                    </label>
                </div>
            </details>
        <?php endforeach ?>

        <?php if ($canEdit): ?>
            <div class="row">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            </div>
        <?php endif ?>
    </form>
</div>

<?php if ($canEdit): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('throne.alert.test')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('throne.alert.test_hint')) ?></p>

        <form method="post" action="<?= $e($target) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="test">

            <div class="row">
                <?php foreach ($placeholders as $platzhalter): ?>
                    <label class="field grow">
                        <span class="hint"><code>{{ <?= $e($platzhalter) ?> }}</code></span>
                        <input class="input" type="text" name="preview[<?= $e($platzhalter) ?>]"
                               value="<?= $e($preview[$platzhalter] ?? '') ?>">
                    </label>
                <?php endforeach ?>
            </div>

            <?php /*
                Welcher Fall getestet wird, ist eine Wahl und kein
                dritter Knopf: die drei Texte unterscheiden sich, und
                man will den sehen, an dem man gerade schreibt.
            */ ?>
            <div class="row">
                <label class="field">
                    <span class="hint"><?= $e(translate('throne.alert.test_case')) ?></span>
                    <select class="input" name="case">
                        <?php foreach ($cases as $schluessel => $beschriftung): ?>
                            <option value="<?= $e($schluessel) ?>"><?= $e($beschriftung) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>

                <button class="btn btn-ghost" type="submit"><?= $e(translate('throne.alert.test_send')) ?></button>
            </div>
        </form>
    </div>
<?php endif ?>
