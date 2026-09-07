<?php
/**
 * Der Reiter "Anfragen": wer sich gemeldet hat.
 *
 * Kacheln wie im Rest von Raids, mit einem Haken und einem Kreuz. Der
 * Haken nimmt an - dann taucht der Kanal im Live-Reiter auf, sobald er
 * streamt. Das Kreuz lehnt ab.
 *
 * Abgelehnte Anfragen stehen nicht in dieser Liste. Sie bleiben in der
 * Tabelle, damit der Anfrager auf /raidme liest, woran er ist - aber
 * hier waeren sie eine Liste von Entscheidungen, die man schon
 * getroffen hat.
 *
 * @var callable $e
 * @var callable $url
 * @var list<array{login: string, display_name: string, status: string, requested_at: string}> $requests
 * @var array<string, string> $images  Login => Bildadresse, frisch von Twitch
 * @var bool $open       Nimmt die oeffentliche Seite Anfragen an?
 * @var string $publicUrl
 * @var bool $canDecide
 * @var string $csrf
 */

use TwitchController\Core\Support\Dates;

$ziel = $url('/networking/raids/requests');
?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('raids_req.tab')) ?></h2>

        <?php /*
            Der Schalter gehoert hierher und nicht in die Navigation:
            geschlossen ist nicht dasselbe wie abgeschaltet. Die Liste
            bleibt, die angenommenen Kanaele bleiben im Live-Reiter -
            es kommt nur nichts Neues dazu.
        */ ?>
        <?php if ($canDecide): ?>
            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="switch switch-small<?= $open ? ' is-on' : '' ?>" type="submit"
                        title="<?= $e(translate('raids_req.toggle_hint')) ?>"
                        aria-label="<?= $e(translate('raids_req.toggle_hint')) ?>">
                    <span class="switch-track"><span class="switch-knob"></span></span>
                </button>
            </form>
        <?php else: ?>
            <span class="badge <?= $open ? 'badge-ok' : 'badge-off' ?>">
                <?= $e($open ? translate('raids_req.is_open') : translate('raids_req.is_closed')) ?>
            </span>
        <?php endif ?>
    </div>

    <?php if (!$open): ?>
        <div class="note note-warn"><?= $e(translate('raids_req.closed_hint')) ?></div>
    <?php endif ?>

    <?php /*
        Die Adresse zum Weitergeben. Sie steht als Text und nicht nur
        als Link: sie wird in einen Chat getippt oder in ein Panel
        geschrieben, und dafuer muss man sie LESEN koennen.
    */ ?>
    <p class="hint">
        <?= $e(translate('raids_req.public_hint')) ?>
        <a class="mono" href="<?= $e($publicUrl) ?>" target="_blank" rel="noopener"><?= $e($publicUrl) ?></a>
    </p>

    <?php if ($requests === []): ?>
        <p class="hint"><?= $e(translate('raids_req.none')) ?></p>
    <?php else: ?>
        <div class="raid-grid">
            <?php foreach ($requests as $anfrage): ?>
                <?php $bild = $images[$anfrage['login']] ?? ''; ?>

                <div class="raid-tile">
                    <a target="_blank" rel="noopener"
                       href="https://twitch.tv/<?= $e(rawurlencode($anfrage['login'])) ?>">
                        <?php if ($bild !== ''): ?>
                            <img class="raid-avatar" src="<?= $e($bild) ?>" alt="">
                        <?php else: ?>
                            <div class="raid-avatar raid-avatar-empty">
                                <?= $e(strtoupper(substr($anfrage['display_name'], 0, 1))) ?>
                            </div>
                        <?php endif ?>

                        <div class="raid-name"><?= $e($anfrage['display_name']) ?></div>
                    </a>

                    <?php /*
                        Angenommen: das steht an der Kachel und nicht in
                        einer eigenen Liste. Sonst muesste man zwei
                        Listen lesen, um zu wissen, wer heute Abend
                        dabei ist.
                    */ ?>
                    <?php if ($anfrage['status'] === 'accepted'): ?>
                        <div class="raid-meta">
                            <span class="badge badge-ok"><?= $e(translate('raids_req.status.accepted')) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="raid-meta"><?= $e(translate('raids_req.asked_at', [
                            'when' => Dates::short($anfrage['requested_at']),
                        ])) ?></div>
                    <?php endif ?>

                    <?php if ($canDecide): ?>
                        <div class="row raid-decide">
                            <?php /*
                                Zwei Formulare und nicht eines mit zwei
                                Knoepfen: sonst entscheidet der Name des
                                gedrueckten Knopfes, und der kommt bei
                                einem Absenden per Tastatur nicht immer
                                mit.
                            */ ?>
                            <?php if ($anfrage['status'] === 'pending'): ?>
                                <form method="post" action="<?= $e($ziel) ?>">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="login" value="<?= $e($anfrage['login']) ?>">
                                    <button class="btn btn-small raid-accept" type="submit"
                                            title="<?= $e(translate('raids_req.accept')) ?>"
                                            aria-label="<?= $e(translate('raids_req.accept')) ?>">&check;</button>
                                </form>
                            <?php endif ?>

                            <form method="post" action="<?= $e($ziel) ?>">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="action" value="decline">
                                <input type="hidden" name="login" value="<?= $e($anfrage['login']) ?>">
                                <button class="btn btn-small raid-decline" type="submit"
                                        title="<?= $e(translate('raids_req.decline')) ?>"
                                        aria-label="<?= $e(translate('raids_req.decline')) ?>">&times;</button>
                            </form>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
