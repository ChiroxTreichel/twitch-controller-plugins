<?php
/**
 * Subathon - dieselben sieben Reiter wie im Programm.
 *
 *   Übersicht | Einstellungen | Overlay | Nachrichten |
 *   Manuelles Buchen | Happy Hour | Info
 *
 * Der Reiter steht in der ADRESSE und nicht in einem Formularwert: so
 * laesst sich einer neu laden oder verschicken, und der Zurueck-Knopf
 * des Browsers tut, was er soll.
 *
 * Im Programm war jeder Reiter ein Datagrid mit zwei Spalten, in dem
 * man Zellen anklickte. Hier ist es ein Formular je Reiter - dasselbe
 * in Feldern, die man sieht.
 *
 * @var \TwitchController\Core\Http\View $view
 * @var callable $e
 * @var callable $url
 * @var string $tab
 * @var string $status
 * @var int $now
 * @var int $start
 * @var int $end
 * @var int $timer
 * @var int $max
 * @var int $break
 * @var string $owner
 * @var int $perSub
 * @var int $perBits
 * @var int $perCent
 * @var array<string, string> $colors
 * @var list<string> $messages
 * @var list<array{start_hour: int, type: int}> $happy
 * @var list<array<string, mixed>> $history
 * @var array<string, string> $preview
 * @var bool $canEdit
 * @var bool $canBook
 * @var bool $locked
 * @var bool $showManual
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\Subathon\HappyHour;
use TwitchController\Plugin\Subathon\Log;
use TwitchController\Plugin\Subathon\Texts;

$reiterUrl = static fn (string $name): string => $url('/tools/subathon') . '?tab=' . $name;

/** Sekunden als 1:02:03 - wie die Anzeige im Programm. */
$uhr = static function (int $sekunden): string {
    $sekunden = max(0, $sekunden);

    return sprintf('%d:%02d:%02d', intdiv($sekunden, 3600), intdiv($sekunden % 3600, 60), $sekunden % 60);
};

$zeit = static fn (int $stempel): string => $stempel <= 0 ? '—' : date('d.m.Y H:i', $stempel);

/*
 * Laeuft er, sind die Einstellungen zu - unabhaengig davon, was
 * jemand darf. Das eine ist eine Frage der Rechte, das andere eine
 * des Zeitpunkts.
 */
$darfEinstellen = $canEdit && !$locked;
?>
<h1><?= $e(translate('subathon.name')) ?></h1>
<p class="lead"><?= $e(translate('subathon.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php
/*
 * "Manuelles Buchen" steht nur da, wenn es eingeschaltet ist - siehe
 * die Einstellungen. Die anderen sechs immer.
 */
$reiterListe = [
    'overview' => translate('subathon.tab.overview'),
    'settings' => translate('subathon.tab.settings'),
    'overlay'  => translate('subathon.tab.overlay'),
    'messages' => translate('subathon.tab.messages'),
];

if ($showManual) {
    $reiterListe['manual'] = translate('subathon.tab.manual');
}

$reiterListe['happy'] = translate('subathon.tab.happy');
$reiterListe['history'] = translate('subathon.tab.history');
?>
<div class="tabs">
    <?php foreach ($reiterListe as $name => $beschriftung): ?>
        <a class="tab<?= $tab === $name ? ' is-active' : '' ?>" href="<?= $e($reiterUrl($name)) ?>">
            <?= $e($beschriftung) ?>
        </a>
    <?php endforeach ?>
</div>

<?php /* ================= Übersicht ================= */ ?>
<?php if ($tab === 'overview'): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(Texts::status($status)) ?></h2>
        </div>

        <?php /*
            Die grosse Zahl. Sie zaehlt im Browser weiter - der Server
            liefert Ende und seine eigene Uhrzeit, und daraus laesst
            sich die Restzeit jederzeit ausrechnen. Das Programm
            schrieb dafuer jede Sekunde eine Datei.
        */ ?>
        <?php if ($status === 'running' || $status === 'paused'): ?>
            <p class="mono" id="subathon-clock"
               data-end="<?= (int) $end ?>"
               data-now="<?= (int) $now ?>"
               data-paused="<?= $status === 'paused' ? '1' : '0' ?>"
               style="font-size:3rem;font-weight:700;margin:0 0 12px;">
                <?= $e($uhr($end - $now)) ?>
            </p>
        <?php endif ?>

        <table>
            <tbody>
                <tr>
                    <td><?= $e(translate('subathon.field.start')) ?></td>
                    <td class="mono"><?= $e($zeit($start)) ?></td>
                </tr>
                <tr>
                    <td><?= $e(translate('subathon.field.end')) ?></td>
                    <td class="mono"><?= $e($start > 0 ? $zeit($end) : '—') ?></td>
                </tr>
                <tr>
                    <td><?= $e(translate('subathon.field.timer')) ?></td>
                    <td class="mono"><?= $e($uhr($timer)) ?></td>
                </tr>
                <tr>
                    <td><?= $e(translate('subathon.field.max')) ?></td>
                    <td class="mono"><?= $max > 0 ? $e($uhr($max)) : $e(translate('subathon.no_limit')) ?></td>
                </tr>
                <tr>
                    <td><?= $e(translate('subathon.field.break')) ?></td>
                    <td class="mono"><?= $e($uhr($break)) ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($canEdit): ?>
            <div class="row" style="margin-top:16px;">
                <?php /*
                    Genau ein Knopf, wie im Programm: was er tut,
                    haengt am Zustand. Drei Knoepfe nebeneinander, von
                    denen zwei nichts tun, sind drei Gelegenheiten,
                    sich zu vertun.
                */ ?>
                <?php if ($status === 'running'): ?>
                    <form method="post" action="<?= $e($url('/tools/subathon')) ?>">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="action" value="pause">
                        <button class="btn" type="submit"><?= $e(translate('subathon.action.pause')) ?></button>
                    </form>
                <?php elseif ($status === 'paused'): ?>
                    <form method="post" action="<?= $e($url('/tools/subathon')) ?>">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="action" value="resume">
                        <button class="btn" type="submit"><?= $e(translate('subathon.action.resume')) ?></button>
                    </form>
                <?php elseif ($status === 'ended'): ?>
                    <?php /*
                        Zuruecksetzen wirft die Zeit weg - also erst
                        fragen. Der Kasten des Kerns statt eines
                        Browserdialogs, wie ueberall hier.
                    */ ?>
                    <?= $view->render('_confirm', [
                        'label'    => translate('subathon.action.reset'),
                        'question' => translate('subathon.reset_confirm'),
                        'confirm'  => translate('subathon.action.reset'),
                        'action'   => $url('/tools/subathon'),
                        'fields'   => ['csrf' => $csrf, 'action' => 'reset'],
                        'small'    => false,
                    ], null) ?>
                <?php endif ?>
            </div>
        <?php endif ?>
    </div>
<?php endif ?>

<?php /* ================= Einstellungen ================= */ ?>
<?php if ($tab === 'settings'): ?>
    <form method="post" action="<?= $e($url('/tools/subathon')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="settings">

        <div class="card">
            <div class="card-head">
                <h2><?= $e(translate('subathon.tab.settings')) ?></h2>
            </div>

            <p class="hint"><?= $e(translate('subathon.settings_hint')) ?></p>

            <?php if ($locked): ?>
                <?php /*
                    Zu, solange er laeuft. Die Zahlen bleiben sichtbar -
                    man will ja wissen, womit gerade gerechnet wird.
                */ ?>
                <div class="note note-warn"><?= $e(translate('subathon.locked')) ?></div>
            <?php endif ?>

            <div class="row">
                <label class="field">
                    <span class="hint"><?= $e(translate('subathon.field.start')) ?></span>
                    <input class="input" type="datetime-local" name="start"
                           value="<?= $e($start > 0 ? date('Y-m-d\TH:i', $start) : '') ?>"
                           <?= $darfEinstellen ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('subathon.field.max_hours')) ?></span>
                    <input class="input" type="number" name="max_hours" min="0" max="720" step="1"
                           value="<?= (int) intdiv($max, 3600) ?>" <?= $darfEinstellen ? '' : 'readonly' ?>>
                </label>
            </div>

            <label class="field">
                <span class="hint"><?= $e(translate('subathon.field.owner')) ?></span>
                <input class="input" type="text" name="owner" value="<?= $e($owner) ?>"
                       <?= $darfEinstellen ? '' : 'readonly' ?>>
            </label>

            <div class="row">
                <label class="field">
                    <span class="hint"><?= $e(translate('subathon.field.minutes_per_sub')) ?></span>
                    <input class="input" type="number" name="minutes_per_sub" min="1" max="1440" step="1"
                           value="<?= (int) intdiv($perSub, 60) ?>" <?= $darfEinstellen ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('subathon.field.bits_per_sub')) ?></span>
                    <input class="input" type="number" name="bits_per_sub" min="1" max="100000" step="1"
                           value="<?= (int) $perBits ?>" <?= $darfEinstellen ? '' : 'readonly' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('subathon.field.cent_per_sub')) ?></span>
                    <input class="input" type="number" name="cent_per_sub" min="1" max="100000" step="1"
                           value="<?= (int) $perCent ?>" <?= $darfEinstellen ? '' : 'readonly' ?>>
                </label>
            </div>

            <?php /*
                Die abgeleiteten Zahlen - im Programm eigene Zeilen im
                Gitter, die man auch bearbeiten konnte. Hier stehen sie
                da: sie ergeben sich aus den drei Feldern darueber, und
                zwei Stellen fuer dieselbe Zahl laufen auseinander.
            */ ?>
            <table>
                <tbody>
                    <tr>
                        <td><?= $e(translate('subathon.field.seconds_per_bit')) ?></td>
                        <td class="mono"><?= $e($preview['SECONDS_PER_BIT']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $e(translate('subathon.field.seconds_per_cent')) ?></td>
                        <td class="mono"><?= $e($preview['SECONDS_PER_CENT']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $e(translate('subathon.field.euro_per_hour')) ?></td>
                        <td class="mono"><?= $e($preview['EURO_PER_HOUR']) ?></td>
                    </tr>
                    <tr>
                        <td><?= $e(translate('subathon.field.euro_until_full')) ?></td>
                        <td class="mono"><?= $e($preview['EURO_UNTIL_FULL']) ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if ($darfEinstellen): ?>
                <div class="row" style="margin-top:14px;">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                </div>
            <?php endif ?>
        </div>
    </form>
<?php endif ?>

<?php /* ================= Overlay ================= */ ?>
<?php if ($tab === 'overlay'): ?>
    <form method="post" action="<?= $e($url('/tools/subathon')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="colors">

        <div class="card">
            <div class="card-head">
                <h2><?= $e(translate('subathon.tab.overlay')) ?></h2>
            </div>

            <p class="hint"><?= $e(translate('subathon.overlay_hint')) ?></p>

            <?php foreach ([
                'done'          => translate('subathon.color.done'),
                'done_text'     => translate('subathon.color.done_text'),
                'todo'          => translate('subathon.color.todo'),
                'todo_text'     => translate('subathon.color.todo_text'),
                'possible'      => translate('subathon.color.possible'),
                'possible_text' => translate('subathon.color.possible_text'),
            ] as $name => $beschriftung): ?>
                <label class="field">
                    <span class="hint"><?= $e($beschriftung) ?></span>
                    <input class="input" type="text" name="color_<?= $e($name) ?>"
                           value="<?= $e($colors[$name]) ?>" <?= $canEdit ? '' : 'readonly' ?>
                           style="border-left:24px solid <?= $e($colors[$name]) ?>;">
                </label>
            <?php endforeach ?>

            <?php if ($canEdit): ?>
                <div class="row">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                </div>
            <?php endif ?>
        </div>
    </form>
<?php endif ?>

<?php /* ================= Nachrichten ================= */ ?>
<?php if ($tab === 'messages'): ?>
    <form method="post" action="<?= $e($url('/tools/subathon')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="messages">

        <div class="card">
            <div class="card-head">
                <h2><?= $e(translate('subathon.tab.messages')) ?></h2>
            </div>

            <p class="hint"><?= $e(translate('subathon.messages_hint')) ?></p>

            <label class="field">
                <textarea class="input" name="messages" rows="12"
                          style="resize:vertical;font-family:inherit;line-height:1.6;"
                          <?= $canEdit ? '' : 'readonly' ?>><?= $e(implode("\n", $messages)) ?></textarea>
            </label>

            <?php /*
                Die Platzhalter mit ihrem aktuellen Wert daneben - so
                sieht man beim Schreiben, was im Stream stehen wird.
            */ ?>
            <table>
                <tbody>
                    <?php foreach ([
                        'MIN_PER_SUB', 'BITS_PER_SUB', 'SECONDS_PER_BIT', 'CENT_PER_SUB', 'SECONDS_PER_CENT',
                        'CENT_PER_HOUR', 'EURO_PER_HOUR', 'EURO_UNTIL_FULL', 'OWNER',
                        'HAPPY_HOUR_START', 'HAPPY_HOUR_END',
                    ] as $name): ?>
                        <tr>
                            <td class="mono">{{Conf.<?= $e($name) ?>}}</td>
                            <td class="mono"><?= $e($preview[$name]) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>

            <?php if ($canEdit): ?>
                <div class="row">
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                </div>
            <?php endif ?>
        </div>
    </form>
<?php endif ?>

<?php /* ================= Manuelles Buchen ================= */ ?>
<?php if ($tab === 'manual' && $showManual): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('subathon.tab.manual')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('subathon.manual_hint')) ?></p>

        <form method="post" action="<?= $e($url('/tools/subathon')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="book">

            <div class="row">
                <label class="field grow">
                    <span class="hint"><?= $e(translate('subathon.field.who')) ?></span>
                    <input class="input" type="text" name="who" autocomplete="off"
                           <?= $canBook ? '' : 'disabled' ?>>
                </label>

                <label class="field">
                    <span class="hint"><?= $e(translate('subathon.field.amount')) ?></span>
                    <input class="input" type="number" name="amount" min="1" max="1000000" step="1" value="1"
                           <?= $canBook ? '' : 'disabled' ?>>
                </label>
            </div>

            <?php if ($canBook): ?>
                <?php /*
                    Fuenf Knoepfe wie im Programm - Abo, Geschenk-Abos,
                    Bits, Spende, Minuten. Jeder schickt dasselbe
                    Formular mit einer anderen Art.

                    Die Menge heisst je nach Knopf etwas anderes: bei
                    Bits sind es Bits, bei der Spende CENT, bei den
                    Geschenken die Anzahl. So war es dort auch.
                */ ?>
                <div class="row">
                    <button class="btn" type="submit" name="kind" value="sub">
                        <?= $e(translate('subathon.book.sub')) ?>
                    </button>
                    <button class="btn" type="submit" name="kind" value="gift">
                        <?= $e(translate('subathon.book.gift')) ?>
                    </button>
                    <button class="btn" type="submit" name="kind" value="bits">
                        <?= $e(translate('subathon.book.bits')) ?>
                    </button>
                    <button class="btn" type="submit" name="kind" value="donation">
                        <?= $e(translate('subathon.book.donation')) ?>
                    </button>
                    <button class="btn btn-ghost" type="submit" name="kind" value="minutes">
                        <?= $e(translate('subathon.book.minutes')) ?>
                    </button>
                </div>
            <?php endif ?>
        </form>
    </div>
<?php endif ?>

<?php /* ================= Happy Hour ================= */ ?>
<?php if ($tab === 'happy'): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('subathon.tab.happy')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('subathon.happy_hint')) ?></p>

        <table>
            <thead>
                <tr>
                    <th><?= $e(translate('subathon.happy.start')) ?></th>
                    <th><?= $e(translate('subathon.happy.type')) ?></th>
                    <?php if ($canEdit): ?>
                        <th></th>
                    <?php endif ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($happy === []): ?>
                    <tr>
                        <td colspan="3" class="hint"><?= $e(translate('subathon.happy.empty')) ?></td>
                    </tr>
                <?php endif ?>

                <?php foreach ($happy as $eintrag): ?>
                    <tr>
                        <td class="mono"><?= $e(sprintf('%02d:00', $eintrag['start_hour'])) ?></td>
                        <td><?= $e(Texts::happyType((int) $eintrag['type'])) ?></td>
                        <?php if ($canEdit): ?>
                            <td>
                                <form method="post" action="<?= $e($url('/tools/subathon')) ?>">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="action" value="happy_remove">
                                    <input type="hidden" name="hour" value="<?= (int) $eintrag['start_hour'] ?>">
                                    <button class="btn btn-ghost btn-small" type="submit">
                                        <?= $e(translate('subathon.happy.remove')) ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <?php if ($canEdit): ?>
            <form method="post" action="<?= $e($url('/tools/subathon')) ?>" style="margin-top:14px;">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="happy_add">

                <div class="row">
                    <label class="field">
                        <span class="hint"><?= $e(translate('subathon.happy.start')) ?></span>
                        <select class="input" name="hour">
                            <?php for ($stunde = 0; $stunde < 24; $stunde++): ?>
                                <option value="<?= $stunde ?>"><?= $e(sprintf('%02d:00', $stunde)) ?></option>
                            <?php endfor ?>
                        </select>
                    </label>

                    <label class="field grow">
                        <span class="hint"><?= $e(translate('subathon.happy.type')) ?></span>
                        <select class="input" name="type">
                            <?php foreach ([HappyHour::DOUBLE_TIME, HappyHour::EXTEND_ONLY, HappyHour::EXTEND] as $art): ?>
                                <option value="<?= (int) $art ?>"><?= $e(Texts::happyType($art)) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>

                    <button class="btn" type="submit"><?= $e(translate('subathon.happy.add')) ?></button>
                </div>
            </form>
        <?php endif ?>
    </div>
<?php endif ?>

<?php /* ================= Verlauf ================= */ ?>
<?php if ($tab === 'history'): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('subathon.history')) ?></h2>

            <?php if ($canEdit && $history !== []): ?>
                <?= $view->render('_confirm', [
                    'label'    => translate('subathon.log_clear'),
                    'question' => translate('subathon.log_confirm'),
                    'confirm'  => translate('subathon.log_clear'),
                    'action'   => $url('/tools/subathon'),
                    'fields'   => ['csrf' => $csrf, 'action' => 'clear_log'],
                    'right'    => true,
                ], null) ?>
            <?php endif ?>
        </div>

        <?php if ($history === []): ?>
            <p class="hint"><?= $e(translate('subathon.history_empty')) ?></p>
        <?php else: ?>
            <?php /*
                Eine Spalte je Frage - Zeitpunkt, wer, was, wie viel,
                wie viel Zeit. Das Programm schrieb daraus einen Satz
                ("X spendete 5,00 € für 20 Minuten"); der liest sich
                einzeln gut und in hundert Zeilen gar nicht.
            */ ?>
            <table>
                <thead>
                    <tr>
                        <th><?= $e(translate('subathon.log.when')) ?></th>
                        <th><?= $e(translate('subathon.log.who')) ?></th>
                        <th><?= $e(translate('subathon.log.what')) ?></th>
                        <th><?= $e(translate('subathon.log.how_much')) ?></th>
                        <th><?= $e(translate('subathon.log.time')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $eintrag): ?>
                        <?php $sekunden = (int) $eintrag['seconds']; ?>
                        <tr>
                            <td class="hint mono" style="white-space:nowrap;">
                                <?= $e(date('d.m. H:i', strtotime((string) $eintrag['created_at']))) ?>
                            </td>
                            <td><?= $e((string) $eintrag['who']) ?></td>
                            <td><?= $e(Texts::kind((string) $eintrag['kind'])) ?></td>
                            <td class="mono">
                                <?= $e(Texts::amount((string) $eintrag['kind'], (string) $eintrag['amount'])) ?>
                            </td>
                            <?php /*
                                0 Sekunden heisst: es kam an, waehrend
                                nicht lief. Das steht hier als Strich
                                und nicht als "0 Sekunden" - man soll
                                es sehen, ohne es zu lesen.
                            */ ?>
                            <td class="mono" style="white-space:nowrap;">
                                <?= $sekunden > 0 ? $e(Log::duration($sekunden)) : '<span class="hint">—</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
<?php endif ?>
