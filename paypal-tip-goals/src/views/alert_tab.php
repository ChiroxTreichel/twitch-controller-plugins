<?php
/**
 * Der Reiter "Spende" auf der Alerts-Seite.
 *
 * Aufbau wie die Reiter von Alerts - Twitch daneben - derselbe Kasten,
 * derselbe Schalter oben rechts, dieselben Felder. Ein Reiter, der
 * anders aussieht als seine Nachbarn, faellt auf, ohne etwas zu
 * sagen.
 *
 * @var callable $e
 * @var callable $url
 * @var array{enabled: bool, text: string, video: string, audio: string, duration: int} $config
 * @var list<string> $placeholders
 * @var array<string, string> $preview
 * @var string $target      Adresse zum Speichern
 * @var bool $canEdit
 * @var bool $canToggle
 * @var bool $canTest
 * @var int $defaultDuration
 * @var int $maxText
 * @var string $csrf
 */

/**
 * Ein Feld für eine Datei-Adresse. Eintippen oder auswählen - der
 * Knopf löst die verborgene Dateiauswahl aus. Verdrahtet ist das im
 * Kern (layout.php) und nicht hier: die Datei geht an /account/uploads,
 * und der Pfad, der zurueckkommt, landet im Feld.
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
                    title="<?= $e(translate('pp_tip.alert.choose_file')) ?>">↑</button>
            <input class="file-field-native" id="<?= $e($id) ?>" type="file" accept="<?= $e($accept) ?>">
        <?php endif ?>
    </div>
    <?php
};
?>
<div class="card">
    <div class="head-row">
        <h2 style="margin:0;"><?= $e(translate('pp_tip.alert.title')) ?></h2>

        <?php if ($canToggle): ?>
            <form method="post" action="<?= $e($target) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="btn btn-small<?= $config['enabled'] ? '' : ' btn-ghost' ?>" type="submit">
                    <?= $e($config['enabled']
                        ? translate('pp_tip.alert.on')
                        : translate('pp_tip.alert.off')) ?>
                </button>
            </form>
        <?php else: ?>
            <span class="badge <?= $config['enabled'] ? 'badge-ok' : 'badge-off' ?>">
                <?= $e($config['enabled']
                    ? translate('pp_tip.alert.on')
                    : translate('pp_tip.alert.off')) ?>
            </span>
        <?php endif ?>
    </div>

    <p class="hint"><?= $e(translate('pp_tip.alert.lead')) ?></p>

    <p class="hint">
        <?= $e(translate('pp_tip.alert.placeholders')) ?>
        <?php foreach ($placeholders as $platzhalter): ?>
            <code>{{ <?= $e($platzhalter) ?> }}</code>
        <?php endforeach ?>
    </p>

    <form method="post" action="<?= $e($target) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">

        <label class="field">
            <span class="hint"><?= $e(translate('pp_tip.alert.text')) ?></span>
            <input class="input" type="text" name="text" maxlength="<?= (int) $maxText ?>"
                   value="<?= $e($config['text']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
        </label>

        <div class="field">
            <span class="hint"><?= $e(translate('pp_tip.alert.video')) ?></span>
            <?php $dateifeld('video', $config['video'], 'video/*'); ?>
        </div>

        <div class="field">
            <span class="hint"><?= $e(translate('pp_tip.alert.audio')) ?></span>
            <?php $dateifeld('audio', $config['audio'], 'audio/*'); ?>
        </div>

        <?php /*
            Leer heisst "die Vorgabe von Alerts". Darum steht sie als
            Platzhalter im Feld und nicht als Wert - ein eingetragener
            Wert liesse sich nie wieder auf "Vorgabe" zuruecksetzen.
        */ ?>
        <label class="field">
            <span class="hint"><?= $e(translate('pp_tip.alert.duration')) ?></span>
            <input class="input" type="number" min="0" max="120" step="1" name="duration"
                   value="<?= $config['duration'] > 0 ? (int) $config['duration'] : '' ?>"
                   placeholder="<?= (int) $defaultDuration ?>"
                <?= $canEdit ? '' : 'readonly' ?>>
        </label>

        <?php if ($canEdit): ?>
            <div class="row">
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            </div>
        <?php endif ?>
    </form>
</div>

<?php if ($canTest): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('pp_tip.alert.test')) ?></h2>
        </div>

        <?php /*
            Die Testwerte werden NICHT gespeichert - sie gelten fuer
            diesen einen Test. Vorher musste man eine echte Spende
            abwarten, um zu sehen, ob der Text stimmt.
        */ ?>
        <p class="hint"><?= $e(translate('pp_tip.alert.test_hint')) ?></p>

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

            <div class="row">
                <button class="btn btn-ghost" type="submit"><?= $e(translate('pp_tip.alert.test_send')) ?></button>
            </div>
        </form>
    </div>
<?php endif ?>
