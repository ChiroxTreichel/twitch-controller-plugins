<?php
/**
 * Ein Reiter: ein Alert-Typ.
 *
 * Aufbau wie im alten System - Kopfzeile mit Schalter, darunter die
 * Platzhalter-Zeile, dann die Fälle beziehungsweise Stufen als
 * aufklappbare Blöcke, zuletzt Testdaten und Speichern / Test senden.
 *
 * Dieselbe Vorlage für alle sechs Typen: Fälle und Stufen
 * unterscheiden sich nur in dem, was `definition` sagt.
 *
 * @var \TwitchController\Core\Http\View $view
 * @var callable $e
 * @var callable $url
 * @var string $type
 * @var array<string, mixed> $definition
 * @var array{enabled: bool, cases: array<string, array<string, mixed>>, tiers: list<array<string, mixed>>, preview: array<string, string>} $config
 * @var string $area  Rechte-Bereich, z.B. "TwitchAlerts.Follow"
 * @var string $csrf
 * @var int $defaultDuration
 */

$darfAendern = permission($area . '.Edit');
$darfSchalten = permission($area . '.Toggle');
$darfTesten = permission($area . '.Test');

$ziel = $url('/display/alerts/twitch/' . rawurlencode($type));
$stufen = $definition['mode'] === 'tiers';
$feldnamen = \TwitchController\Plugin\TwitchAlerts\Types::fieldLabels();
$feldoptionen = \TwitchController\Plugin\TwitchAlerts\Types::fieldOptions();

/**
 * Ein Feld für eine Datei-Adresse. Eintippen oder auswählen - der
 * Knopf löst die verborgene Dateiauswahl aus (siehe admin.js im
 * Alerts-Plugin).
 */
$dateifeld = static function (string $name, string $wert, string $accept) use ($e, $darfAendern): void {
    $id = 'f-' . substr(hash('crc32b', $name), 0, 8);
    ?>
    <div class="file-field">
        <input class="input" type="text" name="<?= $e($name) ?>" value="<?= $e($wert) ?>"
               placeholder="/uploads/alerts/… <?= $e(translate('twitch_alerts.or_url')) ?>"
            <?= $darfAendern ? '' : 'readonly' ?>>
        <?php if ($darfAendern): ?>
            <button class="file-field-button" type="button"
                    data-file-trigger="<?= $e($id) ?>"
                    title="<?= $e(translate('twitch_alerts.choose_file')) ?>">↑</button>
            <input class="file-field-native" id="<?= $e($id) ?>" type="file" accept="<?= $e($accept) ?>">
        <?php endif ?>
    </div>
    <?php
};
?>
<div class="card">
    <div class="head-row">
        <h2 style="margin:0;"><?= $e($definition['label']) ?></h2>

        <?php if ($darfSchalten): ?>
            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="switch<?= $config['enabled'] ? ' is-on' : '' ?>" type="submit"
                        title="<?= $e(translate('twitch_alerts.toggle_hint', ['type' => $definition['label']])) ?>"
                        aria-label="<?= $e(translate('twitch_alerts.toggle_hint', ['type' => $definition['label']])) ?>">
                    <span class="switch-track"><span class="switch-knob"></span></span>
                </button>
            </form>
        <?php else: ?>
            <span class="badge <?= $config['enabled'] ? 'badge-ok' : 'badge-off' ?>">
                <?= $e($config['enabled'] ? translate('alerts.on') : translate('alerts.off')) ?>
            </span>
        <?php endif ?>
    </div>

    <?php if (!$config['enabled']): ?>
        <div class="note note-warn">
            <?= $e(translate('twitch_alerts.type_off', ['type' => $definition['label']])) ?>
        </div>
    <?php endif ?>

    <?php if ($definition['placeholders'] !== []): ?>
        <p class="hint placeholders">
            <?= $e(translate('twitch_alerts.placeholders')) ?>
            <?php foreach ($definition['placeholders'] as $platzhalter): ?>
                <code>{{ <?= $e($platzhalter) ?> }}</code>
            <?php endforeach ?>
        </p>
    <?php endif ?>

    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">

        <h3><?= $e(translate('twitch_alerts.content')) ?></h3>

        <?php if ($stufen): ?>
            <?php /* Stufen: "ab 100 Bits". Die höchste passende gewinnt. */ ?>
            <p class="hint"><?= $e(translate('twitch_alerts.tiers_hint', ['unit' => $definition['unit']])) ?></p>

            <?php foreach ($config['tiers'] as $i => $stufe): ?>
                <details class="case"<?= $i === 0 ? ' open' : '' ?>>
                    <summary>
                        <?= $e(translate('twitch_alerts.from_amount', [
                            'amount' => (string) $stufe['min'],
                            'unit'   => $definition['unit'],
                        ])) ?>
                    </summary>
                    <div class="case-body">
                        <div class="row">
                            <label class="field">
                                <span class="hint"><?= $e(translate('twitch_alerts.min_amount')) ?></span>
                                <input class="input" type="number" min="0" step="1"
                                       name="tiers[<?= (int) $i ?>][min]" value="<?= (int) $stufe['min'] ?>"
                                    <?= $darfAendern ? '' : 'readonly' ?>>
                            </label>
                            <label class="field">
                                <span class="hint"><?= $e(translate('twitch_alerts.duration')) ?></span>
                                <input class="input" type="number" min="0" max="120" step="1"
                                       name="tiers[<?= (int) $i ?>][duration]"
                                       value="<?= $stufe['duration'] > 0 ? (int) $stufe['duration'] : '' ?>"
                                       placeholder="<?= (int) $defaultDuration ?>"
                                    <?= $darfAendern ? '' : 'readonly' ?>>
                            </label>
                        </div>

                        <label class="field">
                            <span class="hint"><?= $e(translate('twitch_alerts.text')) ?></span>
                            <input class="input" type="text" maxlength="200"
                                   name="tiers[<?= (int) $i ?>][text]" value="<?= $e($stufe['text']) ?>"
                                <?= $darfAendern ? '' : 'readonly' ?>>
                        </label>

                        <div class="field">
                            <span class="hint"><?= $e(translate('twitch_alerts.video')) ?></span>
                            <?php $dateifeld('tiers[' . $i . '][video]', (string) $stufe['video'], 'video/*'); ?>
                        </div>

                        <div class="field">
                            <span class="hint"><?= $e(translate('twitch_alerts.audio')) ?></span>
                            <?php $dateifeld('tiers[' . $i . '][audio]', (string) $stufe['audio'], 'audio/*'); ?>
                        </div>
                    </div>
                </details>
            <?php endforeach ?>

        <?php else: ?>
            <?php foreach ($definition['cases'] as $key => $label): ?>
                <?php $fall = $config['cases'][$key] ?? ['text' => '', 'video' => '', 'audio' => '', 'duration' => 0]; ?>
                <details class="case"<?= array_key_first($definition['cases']) === $key ? ' open' : '' ?>>
                    <summary><?= $e($label) ?></summary>
                    <div class="case-body">
                        <div class="row">
                            <label class="field grow">
                                <span class="hint"><?= $e(translate('twitch_alerts.text')) ?></span>
                                <input class="input" type="text" maxlength="200"
                                       name="cases[<?= $e((string) $key) ?>][text]" value="<?= $e($fall['text']) ?>"
                                    <?= $darfAendern ? '' : 'readonly' ?>>
                            </label>
                            <label class="field">
                                <span class="hint"><?= $e(translate('twitch_alerts.duration')) ?></span>
                                <input class="input" type="number" min="0" max="120" step="1"
                                       name="cases[<?= $e((string) $key) ?>][duration]"
                                       value="<?= $fall['duration'] > 0 ? (int) $fall['duration'] : '' ?>"
                                       placeholder="<?= (int) $defaultDuration ?>"
                                    <?= $darfAendern ? '' : 'readonly' ?>>
                            </label>
                        </div>

                        <div class="field">
                            <span class="hint"><?= $e(translate('twitch_alerts.video')) ?></span>
                            <?php $dateifeld('cases[' . $key . '][video]', (string) $fall['video'], 'video/*'); ?>
                        </div>

                        <div class="field">
                            <span class="hint"><?= $e(translate('twitch_alerts.audio')) ?></span>
                            <?php $dateifeld('cases[' . $key . '][audio]', (string) $fall['audio'], 'audio/*'); ?>
                        </div>
                    </div>
                </details>
            <?php endforeach ?>
        <?php endif ?>

        <div class="row" style="margin-top:16px;">
            <?php if ($darfAendern): ?>
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>
        </div>
    </form>

    <?php if ($darfAendern && $stufen): ?>
        <form method="post" action="<?= $e($ziel) ?>" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="add_tier">
            <button class="btn btn-ghost btn-small" type="submit">
                <?= $e(translate('twitch_alerts.add_tier', ['unit' => $definition['unit']])) ?>
            </button>
        </form>
    <?php endif ?>
</div>

<?php if ($darfTesten): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('twitch_alerts.test_heading')) ?></h2>
        </div>
        <p class="hint"><?= $e(translate('twitch_alerts.test_hint')) ?></p>

        <?php /*
            Die Werte stehen HIER und nicht im Speichern-Formular: sie
            sind zum Wegwerfen. Vorher musste man sie erst sichern,
            damit ein Test sie benutzt - ein Umweg, und dauerhaft
            gespeicherte Wegwerfwerte.
        */ ?>
        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="test">

            <div class="row">
                <?php foreach ($config['preview'] as $key => $wert): ?>
                    <label class="field">
                        <span class="hint"><?= $e($feldnamen[$key] ?? $key) ?></span>
                        <?php if (isset($feldoptionen[$key])): ?>
                            <select class="input" name="preview[<?= $e((string) $key) ?>]">
                                <?php foreach ($feldoptionen[$key] as $option): ?>
                                    <option value="<?= $e($option) ?>"
                                        <?= $wert === $option ? 'selected' : '' ?>><?= $e($option) ?></option>
                                <?php endforeach ?>
                            </select>
                        <?php else: ?>
                            <input class="input" type="text" maxlength="200"
                                   name="preview[<?= $e((string) $key) ?>]" value="<?= $e($wert) ?>">
                        <?php endif ?>
                    </label>
                <?php endforeach ?>
            </div>

            <div class="row" style="margin-top:14px;">
                <?php if ($stufen): ?>
                    <?php /* Bei Stufen entscheidet die Anzahl, welche greift. */ ?>
                    <button class="btn btn-ghost" type="submit"><?= $e(translate('twitch_alerts.send_test')) ?></button>
                <?php else: ?>
                    <?php /*
                        Ein Knopf je Fall: welcher Fall gemeint ist,
                        sagt der Name des Knopfes. So braucht es keine
                        zusaetzliche Auswahlliste daneben.
                    */ ?>
                    <?php foreach ($definition['cases'] as $key => $label): ?>
                        <button class="btn btn-ghost btn-small" type="submit"
                                name="case" value="<?= $e((string) $key) ?>"><?= $e($label) ?></button>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </form>
    </div>
<?php endif ?>

<?php if ($darfAendern && $stufen && count($config['tiers']) > 1): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('twitch_alerts.delete_tier_heading')) ?></h2>
        </div>
        <div class="row">
            <?php foreach ($config['tiers'] as $i => $stufe): ?>
                <?= $view->render('_confirm', [
                    'label'    => translate('twitch_alerts.from_amount', [
                        'amount' => (string) $stufe['min'],
                        'unit'   => $definition['unit'],
                    ]),
                    'question' => translate('twitch_alerts.confirm_delete_tier', [
                        'amount' => (string) $stufe['min'],
                        'unit'   => $definition['unit'],
                    ]),
                    'confirm'  => translate('twitch_alerts.confirm_delete_tier_yes'),
                    'action'   => $ziel,
                    'fields'   => [
                        'csrf'   => $csrf,
                        'action' => 'delete_tier',
                        'tier'   => (string) $i,
                    ],
                ], null) ?>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>
