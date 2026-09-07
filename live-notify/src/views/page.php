<?php
/**
 * Die Seite: wer beobachtet wird, und mit welchen Zielen.
 *
 * Kacheln wie im alten System - Bild, Name, ein Haken je Ziel, und ein
 * Kreuz in der Ecke. Wer sieben Kanaele beobachtet, findet den
 * gesuchten am Bild und nicht am Text.
 *
 * Nur die Kanaele; die Einstellungen jedes Ziels stehen unter
 * Plugins > Einstellungen. Dort sucht man sie, und hier waeren sie im
 * Weg.
 *
 * Welche Ziele es gibt, weiss diese Vorlage nicht: sie laeuft ueber
 * $targets, und die kommen aus dem Hook. Ein neues Ziel-Plugin
 * erscheint hier also von selbst.
 *
 * @var callable $e
 * @var callable $url
 * @var list<array{login: string, display_name: string, targets: list<string>, live: bool, checked_at: ?string}> $channels
 * @var array<string, string> $images  Login => Bildadresse, frisch von Twitch
 * @var array<string, array{label: string, order: int, ready: bool, hint: string}> $targets
 * @var bool $enabled
 * @var bool $canEdit
 * @var bool $canAdd
 * @var bool $canDelete
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
    <?php foreach ($targets as $ziel): ?>
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
            niemand erfaehrt es.
        */ ?>
        <p class="hint"><?= $e(translate('live_notify.no_targets')) ?></p>
    <?php else: ?>
        <div class="ln-grid">
            <?php foreach ($channels as $kanal): ?>
                <div class="ln-tile">
                    <?php if ($canDelete): ?>
                        <?php /*
                            Das Kreuz in der Ecke, wie im alten System -
                            ohne Rueckfrage.

                            Bei einer Loeschung ist eine Rueckfrage sonst
                            Pflicht; hier nicht: einen Kanal wieder
                            aufzunehmen kostet einen Tastendruck, und
                            verloren geht dabei nur der Haken. Eine
                            Rueckfrage je Kachel waere teurer als der
                            Fehlgriff, den sie verhindert.
                        */ ?>
                        <form class="ln-remove" method="post" action="<?= $e($kanalZiel) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="login" value="<?= $e($kanal['login']) ?>">
                            <button type="submit"
                                    title="<?= $e(translate('live_notify.remove_channel', [
                                        'name' => $kanal['display_name'],
                                    ])) ?>"
                                    aria-label="<?= $e(translate('live_notify.remove_channel', [
                                        'name' => $kanal['display_name'],
                                    ])) ?>">&times;</button>
                        </form>
                    <?php endif ?>

                    <?php /*
                        Das Bild kommt frisch von Twitch und nicht aus
                        der Tabelle: gespeichert veraltet es, sobald
                        jemand sein Bild wechselt. Siehe
                        LiveNotify::profiles().
                    */ ?>
                    <?php $bild = $images[$kanal['login']] ?? ''; ?>

                    <?php /*
                        Live wird am BILD gezeigt, nicht als Schildchen
                        daneben: das Bild ist das Grosse in der Kachel,
                        und ein gruener Schein daran sieht man im ganzen
                        Gitter auf einen Blick. Ein Schildchen muesste
                        man lesen, und es verschiebt die Kachelhoehe je
                        nachdem, wer gerade streamt.

                        Farbe allein darf die Aussage aber nicht tragen -
                        darum steht sie zusaetzlich im title des Links.
                    */ ?>
                    <?php $leuchten = $kanal['live'] ? ' is-live' : ''; ?>

                    <a class="ln-head" target="_blank" rel="noopener"
                       href="https://twitch.tv/<?= $e(rawurlencode($kanal['login'])) ?>"
                       title="<?= $e($kanal['live']
                           ? $kanal['display_name'] . ' – ' . translate('live_notify.is_live')
                           : $kanal['display_name']) ?>">
                        <?php if ($bild !== ''): ?>
                            <img class="ln-avatar<?= $leuchten ?>" src="<?= $e($bild) ?>" alt="">
                        <?php else: ?>
                            <?php /*
                                Kein Bild: der erste Buchstabe. Das
                                kommt vor, wenn Twitch gerade nicht
                                antwortet - eine Seite ohne Bilder ist
                                besser als eine mit einer Fehlermeldung.
                            */ ?>
                            <div class="ln-avatar ln-avatar-empty<?= $leuchten ?>">
                                <?= $e(strtoupper(substr($kanal['display_name'], 0, 1))) ?>
                            </div>
                        <?php endif ?>

                        <div class="ln-name"><?= $e($kanal['display_name']) ?></div>
                    </a>

                    <div class="ln-targets">
                        <?php foreach ($targets as $schluessel => $ziel): ?>
                            <?php $an = in_array($schluessel, $kanal['targets'], true); ?>

                            <?php if ($canEdit): ?>
                                <?php /*
                                    Sieht aus wie ein Kaestchen, ist ein
                                    Absende-Knopf.

                                    Ein echtes <input type="checkbox">
                                    muesste beim Anklicken abschicken,
                                    und das kann nur JavaScript. Ein
                                    Knopf tut es ohne, und die ganze
                                    Zeile ist anklickbar statt nur der
                                    Kasten.

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
                                    <button class="ln-check<?= $an ? ' is-on' : '' ?><?= $ziel['ready'] ? '' : ' is-stuck' ?>"
                                            type="submit"
                                            title="<?= $e($ziel['ready']
                                                ? $ziel['label']
                                                : $ziel['label'] . ' – ' . $ziel['hint']) ?>">
                                        <span class="ln-box" aria-hidden="true"></span>
                                        <span><?= $e($ziel['label']) ?></span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="ln-check<?= $an ? ' is-on' : '' ?>">
                                    <span class="ln-box" aria-hidden="true"></span>
                                    <span><?= $e($ziel['label']) ?></span>
                                </span>
                            <?php endif ?>
                        <?php endforeach ?>
                    </div>

                    <?php /*
                        Wann zuletzt nachgesehen wurde. Unten und klein:
                        man braucht es nur, wenn man sich fragt, ob
                        ueberhaupt etwas laeuft.
                    */ ?>
                    <div class="ln-foot hint">
                        <?php if ($kanal['checked_at'] !== null): ?>
                            <?= $e(translate('live_notify.checked_at', [
                                'when' => Dates::short($kanal['checked_at']),
                            ])) ?>
                        <?php else: ?>
                            <?= $e(translate('live_notify.never_checked')) ?>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
