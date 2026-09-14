<?php
/**
 * Der Reiter "Spendenziele" - die Liste aus dem alten System.
 *
 * Eine Karte je Ziel mit Nummer, Name, aktuellem Betrag und Zielbetrag,
 * dazu Pfeile zum Verschieben und ein Knopf zum Loeschen.
 *
 * Der Unterschied zum alten System: es gibt keinen eigenen
 * "aktiv"-Zeiger mehr. Das OBERSTE Ziel ist das laufende, und Spenden
 * gehen immer dorthin. Ein Zeiger, der irgendwo in der Liste steht,
 * war eine zweite Sache zum Pflegen - und man sah ihr nicht an, warum
 * ein Betrag beim dritten Eintrag landete.
 *
 * @var callable $e
 * @var callable $url
 * @var list<array{id: int, position: int, title: string, current: float, target: float, percent: float}> $goals
 * @var int $maxGoals
 * @var bool $ready      Zugangsdaten hinterlegt?
 * @var string $lastError
 * @var bool $canEdit
 * @var string $csrf
 */

$ziel = $url('/display/goals/tips');
$letzte = count($goals) - 1;
?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('se_tip.tab')) ?></h2>

        <?php if ($goals !== []): ?>
            <span class="hint"><?= $e(translate('se_tip.count', [
                'count' => (string) count($goals),
            ])) ?></span>
        <?php endif ?>
    </div>

    <?php /*
        Ohne Zugangsdaten sammelt die Liste nichts ein. Das steht hier
        und nicht nur auf der Einstellungsseite: wer die Ziele pflegt,
        ist auf DIESER Seite.
    */ ?>
    <?php if (!$ready): ?>
        <div class="note note-warn">
            <?= $e(translate('se_tip.no_credentials')) ?>
            <a href="<?= $e($url('/display/goals/tips/settings')) ?>"><?= $e(translate('se_tip.to_settings')) ?></a>
        </div>
    <?php endif ?>

    <?php /*
        Was zuletzt schiefging. Eine Abfrage, die still scheitert,
        sieht auf der Seite aus wie eine, bei der nichts passiert ist -
        und das hat hier schon einmal eine Woche gekostet.
    */ ?>
    <?php if ($lastError !== ''): ?>
        <div class="note note-error">
            <strong><?= $e(translate('se_tip.last_failed')) ?></strong>
            <span class="mono"><?= $e($lastError) ?></span>
        </div>
    <?php endif ?>

    <?php if ($goals === []): ?>
        <p class="hint"><?= $e(translate('se_tip.empty')) ?></p>
    <?php else: ?>
        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="save">

            <div class="tip-goals">
                <?php foreach ($goals as $i => $goal): ?>
                    <div class="tip-goal<?= $i === 0 ? ' is-current' : '' ?>">
                        <div class="tip-goal-head">
                            <span class="tip-goal-order">#<?= (int) ($i + 1) ?></span>

                            <?php if ($i === 0): ?>
                                <span class="badge badge-ok"><?= $e(translate('se_tip.current')) ?></span>
                            <?php endif ?>

                            <span class="tip-goal-percent hint"><?= $e(number_format($goal['percent'], 0)) ?>&nbsp;%</span>
                        </div>

                        <div class="row">
                            <label class="field grow">
                                <span class="hint"><?= $e(translate('se_tip.title')) ?></span>
                                <input class="input" type="text"
                                       name="goals[<?= (int) $goal['id'] ?>][title]"
                                       maxlength="80"
                                       value="<?= $e($goal['title']) ?>"
                                       <?= $canEdit ? '' : 'readonly' ?>>
                            </label>

                            <?php /*
                                step="0.01" und min="0": Cent gibt es,
                                negative Spenden nicht.
                            */ ?>
                            <label class="field">
                                <span class="hint"><?= $e(translate('se_tip.current_amount')) ?></span>
                                <input class="input" type="number" step="0.01" min="0"
                                       name="goals[<?= (int) $goal['id'] ?>][current]"
                                       value="<?= $e(number_format($goal['current'], 2, '.', '')) ?>"
                                       <?= $canEdit ? '' : 'readonly' ?>>
                            </label>

                            <label class="field">
                                <span class="hint"><?= $e(translate('se_tip.target_amount')) ?></span>
                                <input class="input" type="number" step="0.01" min="0"
                                       name="goals[<?= (int) $goal['id'] ?>][target]"
                                       value="<?= $e(number_format($goal['target'], 2, '.', '')) ?>"
                                       <?= $canEdit ? '' : 'readonly' ?>>
                            </label>
                        </div>

                        <div class="tip-goal-bar" aria-hidden="true">
                            <span style="width: <?= $e(number_format($goal['percent'], 2, '.', '')) ?>%"></span>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>

            <?php if ($canEdit): ?>
                <div class="row">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                </div>
            <?php endif ?>
        </form>

        <?php /*
            Verschieben und Loeschen stehen in EIGENEN Formularen,
            ausserhalb des Speicherns. Sonst schickte ein Klick auf den
            Pfeil ungespeicherte Eingaben mit ab - oder verwuerfe sie,
            je nach Reihenfolge.
        */ ?>
        <?php if ($canEdit): ?>
            <div class="tip-goal-actions">
                <?php foreach ($goals as $i => $goal): ?>
                    <div class="row">
                        <span class="tip-goal-order">#<?= (int) ($i + 1) ?></span>
                        <span class="grow"><?= $e($goal['title'] !== '' ? $goal['title'] : translate('se_tip.unnamed')) ?></span>

                        <form method="post" action="<?= $e($ziel) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="action" value="up">
                            <input type="hidden" name="id" value="<?= (int) $goal['id'] ?>">
                            <button class="btn btn-small" type="submit" <?= $i === 0 ? 'disabled' : '' ?>
                                    title="<?= $e(translate('se_tip.move_up')) ?>"
                                    aria-label="<?= $e(translate('se_tip.move_up')) ?>">&uarr;</button>
                        </form>

                        <form method="post" action="<?= $e($ziel) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="action" value="down">
                            <input type="hidden" name="id" value="<?= (int) $goal['id'] ?>">
                            <button class="btn btn-small" type="submit" <?= $i === $letzte ? 'disabled' : '' ?>
                                    title="<?= $e(translate('se_tip.move_down')) ?>"
                                    aria-label="<?= $e(translate('se_tip.move_down')) ?>">&darr;</button>
                        </form>

                        <?php /*
                            Mit Rueckfrage: ein geloeschtes Ziel nimmt
                            seinen Stand mit, und der ist nirgends sonst
                            aufgeschrieben.
                        */ ?>
                        <?= $view->render('_confirm', [
                            'label'    => translate('common.remove'),
                            'question' => translate('se_tip.confirm_delete', [
                                'name' => $goal['title'] !== '' ? $goal['title'] : translate('se_tip.unnamed'),
                            ]),
                            'confirm'  => translate('common.remove'),
                            'action'   => $ziel,
                            'fields'   => [
                                'csrf'   => $csrf,
                                'action' => 'remove',
                                'id'     => (string) $goal['id'],
                            ],
                            'danger'   => true,
                            'small'    => true,
                            'right'    => true,
                        ], null) ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    <?php endif ?>

    <?php if ($canEdit && count($goals) < $maxGoals): ?>
        <form class="row" method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <button class="btn" type="submit"><?= $e(translate('se_tip.add')) ?></button>
        </form>
    <?php endif ?>
</div>
