<?php
/**
 * Die Seite: wer beobachtet wird, und mit welchen Zielen.
 *
 * Nur die Kanaele - das ist die Arbeit. Die Einstellungen (Webhook,
 * Nachrichtenvorlage) stehen unter Plugins > Einstellungen; dort sucht
 * man sie, und hier waeren sie im Weg.
 *
 * Eine Zeile je Kanal, ein Haken je Ziel. Jeder Haken ist ein
 * Absende-Knopf in seinem eigenen Formular - kein JavaScript. Das alte
 * System schickte dafuer eine AJAX-Anfrage; ein Formular tut dasselbe
 * und funktioniert auch dann, wenn ein Skript fehlt.
 *
 * Welche Ziele es gibt, weiss diese Vorlage nicht: sie laeuft ueber
 * $targets, und die kommen aus dem Hook. Ein neues Ziel-Plugin
 * erscheint hier also von selbst.
 *
 * @var callable $e
 * @var callable $url
 * @var list<array{login: string, display_name: string, targets: list<string>, live: bool, checked_at: ?string}> $channels
 * @var array<string, array{label: string, order: int, ready: bool, hint: string}> $targets
 * @var bool $canEdit
 * @var bool $canAdd
 * @var bool $canDelete
 * @var bool $enabled
 * @var string $notice
 * @var string $error
 * @var string $csrf
 */

use TwitchController\Core\Support\Dates;

$kanalZiel = $url('/networking/live/channels');
$zielZiel = $url('/networking/live/target');
?>
<h1><?= $e(translate('live_notify.nav.item')) ?></h1>
<p class="lead"><?= $e(translate('live_notify.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /*
    Abgeschaltet, und das steht hier - nicht nur als Schalter in der
    Seitenleiste. Wer die Liste ansieht und sich fragt, warum keine
    Meldung kam, soll den Grund auf DIESER Seite lesen.
*/ ?>
<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('live_notify.is_off')) ?></div>
<?php endif ?>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('live_notify.channels')) ?></h2>

        <?php if ($channels !== []): ?>
            <span class="hint"><?= $e(translate('live_notify.channel_count', [
                'count' => (string) count($channels),
            ])) ?></span>
        <?php endif ?>
    </div>

    <?php if ($canAdd): ?>
        <form class="row" method="post" action="<?= $e($kanalZiel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="add">

            <label class="field grow">
                <span class="hint"><?= $e(translate('live_notify.add_label')) ?></span>
                <?php /*
                    Eine ganze Adresse ist ein haeufiger Fehlgriff - wer
                    einen Kanal aufnehmen will, hat oft die Adresse in
                    der Hand. LiveNotify::normalizeLogin() nimmt sie
                    darum entgegen und holt den Login heraus; der
                    Platzhalter sagt das.
                */ ?>
                <input class="input" type="text" name="login" maxlength="120"
                       autocomplete="off" spellcheck="false"
                       placeholder="<?= $e(translate('live_notify.add_example')) ?>">
            </label>

            <button class="btn" type="submit"><?= $e(translate('live_notify.add')) ?></button>
        </form>
    <?php endif ?>

    <?php /*
        Ein Ziel, das gerade nicht kann - fehlende Adresse, fehlende
        Freigabe - sagt das hier, oben und einmal. Ohne diesen Hinweis
        waeren die Haken in seiner Spalte Schalter, die stillschweigend
        nichts tun.
    */ ?>
    <?php foreach ($targets as $schluessel => $ziel): ?>
        <?php if (!$ziel['ready'] && $ziel['hint'] !== ''): ?>
            <div class="note note-warn">
                <strong><?= $e($ziel['label']) ?>:</strong>
                <?= $e($ziel['hint']) ?>
            </div>
        <?php endif ?>
    <?php endforeach ?>

    <?php if ($channels === []): ?>
        <p class="hint"><?= $e(translate('live_notify.no_channels')) ?></p>
    <?php elseif ($targets === []): ?>
        <?php /*
            Kanaele ohne ein einziges Ziel: dann wird beobachtet und
            niemand erfaehrt es. Kann eigentlich nicht passieren -
            dieses Plugin bringt Discord mit - aber wer es abschaltet,
            soll den Grund lesen und nicht raten.
        */ ?>
        <p class="hint"><?= $e(translate('live_notify.no_targets')) ?></p>
    <?php else: ?>
        <div class="ln-list">
            <?php foreach ($channels as $kanal): ?>
                <div class="ln-row">
                    <div class="ln-channel">
                        <a target="_blank" rel="noopener"
                           href="https://twitch.tv/<?= $e(rawurlencode($kanal['login'])) ?>">
                            <?= $e($kanal['display_name']) ?>
                        </a>

                        <?php if ($kanal['live']): ?>
                            <span class="badge badge-ok"><?= $e(translate('live_notify.is_live')) ?></span>
                        <?php endif ?>

                        <?php if ($kanal['checked_at'] !== null): ?>
                            <span class="hint"><?= $e(translate('live_notify.checked_at', [
                                'when' => Dates::short($kanal['checked_at']),
                            ])) ?></span>
                        <?php else: ?>
                            <span class="hint"><?= $e(translate('live_notify.never_checked')) ?></span>
                        <?php endif ?>
                    </div>

                    <div class="ln-targets">
                        <?php foreach ($targets as $schluessel => $ziel): ?>
                            <?php $an = in_array($schluessel, $kanal['targets'], true); ?>

                            <?php if ($canEdit): ?>
                                <?php /*
                                    Der Wert ist das Gegenteil des
                                    jetzigen Zustands - der Knopf sagt,
                                    was er tun WILL. Sonst muesste der
                                    Server nachsehen, und zwei Klicks
                                    kurz hintereinander koennten sich
                                    aufheben.
                                */ ?>
                                <form method="post" action="<?= $e($zielZiel) ?>">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="login" value="<?= $e($kanal['login']) ?>">
                                    <input type="hidden" name="target" value="<?= $e($schluessel) ?>">
                                    <input type="hidden" name="value" value="<?= $an ? '0' : '1' ?>">
                                    <button class="ln-target<?= $an ? ' is-on' : '' ?><?= $ziel['ready'] ? '' : ' is-stuck' ?>"
                                            type="submit"
                                            title="<?= $e($ziel['ready']
                                                ? $ziel['label']
                                                : $ziel['label'] . ' – ' . $ziel['hint']) ?>">
                                        <?= $e($ziel['label']) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="ln-target<?= $an ? ' is-on' : '' ?>"><?= $e($ziel['label']) ?></span>
                            <?php endif ?>
                        <?php endforeach ?>
                    </div>

                    <?php if ($canDelete): ?>
                        <?= $view->render('_confirm', [
                            'label'    => translate('live_notify.remove'),
                            'question' => translate('live_notify.confirm_remove', [
                                'name' => $kanal['display_name'],
                            ]),
                            'confirm'  => translate('live_notify.confirm_remove_yes'),
                            'action'   => $kanalZiel,
                            'fields'   => [
                                'csrf'   => $csrf,
                                'action' => 'remove',
                                'login'  => $kanal['login'],
                            ],
                        ], null) ?>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
