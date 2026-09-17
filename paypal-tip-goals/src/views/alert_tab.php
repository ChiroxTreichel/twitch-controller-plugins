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
                <?php /*
                    Derselbe Kippschalter wie bei Alerts - Twitch und
                    auf der Alerts-Seite selbst. Vorher stand hier ein
                    Knopf mit "An" - und der ist zweideutig: heisst das
                    "es ist an" oder "hier einschalten"?
                */ ?>
                <button class="switch<?= $config['enabled'] ? ' is-on' : '' ?>" type="submit"
                        title="<?= $e(translate('pp_tip.alert.toggle_hint')) ?>"
                        aria-label="<?= $e(translate('pp_tip.alert.toggle_hint')) ?>">
                    <span class="switch-track"><span class="switch-knob"></span></span>
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

        <?php /*
            Enter in einem Feld schickt das Formular ueber den ERSTEN
            Absende-Knopf darin ab - und das waere "Stufe entfernen".
            Dieser hier steht davor und traegt nichts bei.
        */ ?>
        <button class="default-submit" type="submit" tabindex="-1" aria-hidden="true"></button>

        <?php /*
            Eine Stufe je Block.

            Im alten System hiess das alert_donation_tiers: eine Spende
            von 50 Euro soll nicht dasselbe ausloesen wie eine von
            einem. Wer das nicht braucht, laesst es bei einer Stufe -
            dann sieht der Reiter aus wie vorher.

            Es gilt immer die HOECHSTE Stufe, deren Mindestbetrag
            erreicht ist. Liegt der Betrag unter allen, gilt die
            unterste: ein Alert soll kommen.
        */ ?>
        <p class="hint"><?= $e(translate('pp_tip.alert.tiers_hint')) ?></p>

        <?php foreach ($config['tiers'] as $n => $stufe): ?>
            <div class="case-body" style="border:1px solid var(--line);border-radius:9px;padding:14px;margin:0 0 12px;">
                <div class="row">
                    <label class="field">
                        <span class="hint"><?= $e(translate('pp_tip.alert.min_amount')) ?></span>
                        <input class="input" type="number" min="1" step="1"
                               name="tiers[<?= (int) $n ?>][min_amount]"
                               value="<?= (int) $stufe['min_amount'] ?>"
                            <?= $canEdit ? '' : 'readonly' ?>>
                    </label>

                    <?php /*
                        Die unterste Stufe bleibt stehen - ohne sie
                        gaebe es fuer kleine Betraege keinen Alert.
                    */ ?>
                    <?php if ($canEdit && count($config['tiers']) > 1): ?>
                        <?php /*
                            Der leere Hinweis ueber dem Knopf ist
                            kein Versehen: die Zeile richtet ihre
                            Kinder mittig aus, und ohne ihn saesse der
                            Knopf auf halber Hoehe zwischen
                            Beschriftung und Feld statt neben dem Feld.
                        */ ?>
                        <div class="field">
                            <span class="hint">&nbsp;</span>
                            <button class="btn btn-ghost btn-small" type="submit"
                                    name="remove_tier" value="<?= (int) $n ?>">
                                <?= $e(translate('pp_tip.alert.tier_remove')) ?>
                            </button>
                        </div>
                    <?php endif ?>
                </div>

                <label class="field">
                    <span class="hint"><?= $e(translate('pp_tip.alert.text')) ?></span>
                    <input class="input" type="text" name="tiers[<?= (int) $n ?>][text]"
                           maxlength="<?= (int) $maxText ?>"
                           value="<?= $e($stufe['text']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </label>

                <div class="field">
                    <span class="hint"><?= $e(translate('pp_tip.alert.video')) ?></span>
                    <?php $dateifeld('tiers[' . $n . '][video]', $stufe['video'], 'video/*'); ?>
                </div>

                <div class="field">
                    <span class="hint"><?= $e(translate('pp_tip.alert.audio')) ?></span>
                    <?php $dateifeld('tiers[' . $n . '][audio]', $stufe['audio'], 'audio/*'); ?>
                </div>

                <?php /*
                    Leer heisst "die Vorgabe von Alerts". Darum steht sie als
                    Platzhalter im Feld und nicht als Wert - ein eingetragener
                    Wert liesse sich nie wieder auf "Vorgabe" zuruecksetzen.
                */ ?>
                <label class="field">
                    <span class="hint"><?= $e(translate('pp_tip.alert.duration')) ?></span>
                    <input class="input" type="number" min="0" max="120" step="1"
                           name="tiers[<?= (int) $n ?>][duration]"
                           value="<?= $stufe['duration'] > 0 ? (int) $stufe['duration'] : '' ?>"
                           placeholder="<?= (int) $defaultDuration ?>"
                        <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            </div>
        <?php endforeach ?>

        <?php if ($canEdit && count($config['tiers']) < $maxTiers): ?>
            <div class="row">
                <button class="btn btn-ghost btn-small" type="submit" name="add_tier" value="1">
                    <?= $e(translate('pp_tip.alert.tier_add')) ?>
                </button>
            </div>
        <?php endif ?>

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
