<?php
/**
 * Der Reiter "Ich folge": wem der Kanal folgt und wer davon noch
 * streamt.
 *
 * Der Stern ist ein Absende-Knopf in seinem eigenen Formular - kein
 * JavaScript. Das alte System schickte dafuer eine AJAX-Anfrage; ein
 * Formular tut dasselbe und funktioniert auch dann, wenn ein Skript
 * fehlt.
 *
 * Das Suchfeld ist dagegen JavaScript, und das ist richtig: es filtert
 * eine Liste, die schon da ist. Ueber den Server waere es ein Aufruf je
 * Tastendruck fuer etwas, das der Browser umsonst kann.
 *
 * @var callable $e
 * @var callable $url
 * @var list<array{login: string, display_name: string, profile_image_url: string, favorite: bool}> $channels
 * @var int $total       Wie viele Kanaele die Liste kennt
 * @var int $pending     Wie viele noch auf ihre Pruefung warten
 * @var int $syncedAt    Wann die Follow-Liste zuletzt geholt wurde
 * @var int $activeDays
 * @var bool $canRead    Hat der Kanal die Twitch-Freigabe?
 * @var bool $canEdit
 * @var bool $canSync
 * @var string $csrf
 */

use TwitchController\Core\Support\Dates;
?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('raids.tab.follows')) ?></h2>

        <?php if ($canSync): ?>
            <form method="post" action="<?= $e($url('/stream/raids/sync')) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <button class="btn btn-ghost btn-small" type="submit"
                        <?= $canRead ? '' : 'disabled' ?>>
                    <?= $e(translate('raids.sync_now')) ?>
                </button>
            </form>
        <?php endif ?>
    </div>

    <?php if (!$canRead): ?>
        <div class="note note-warn">
            <strong><?= $e(translate('raids.scope_missing')) ?></strong>
            <?= $e(translate('raids.scope_missing_hint')) ?>
            <?php if (permission('Account.Settings.View')): ?>
                <a href="<?= $e($url('/account/settings/channel')) ?>">
                    <?= $e(translate('raids.to_settings')) ?>
                </a>
            <?php endif ?>
        </div>
    <?php endif ?>

    <p class="hint">
        <?= $e(translate('raids.window_hint', ['days' => (string) $activeDays])) ?>
        <?php if ($syncedAt > 0): ?>
            <?php /*
                Der Zeitstempel ist eine Sekundenzahl, Dates erwartet
                aber eine Datumszeichenkette aus der Datenbank - mit
                Offset, damit die Zeitzone stimmt. Genau so macht es
                Twitch-Goals mit seinem checked_at.
            */ ?>
            <?= $e(translate('raids.synced_at', [
                'when' => Dates::long((string) date('Y-m-d H:i:sP', $syncedAt)),
            ])) ?>
        <?php endif ?>
    </p>

    <?php /*
        Wie viele noch auf ihre erste Pruefung warten.

        Wichtig genug fuer eine eigene Zeile: direkt nach der
        Installation ist die Liste leer und fuellt sich ueber Minuten,
        weil das Pruefen ein Aufruf JE KANAL ist. Ohne diesen Hinweis
        sieht das nach einem Fehler aus.
    */ ?>
    <?php if ($pending > 0): ?>
        <p class="hint"><?= $e(translate('raids.pending', [
            'count' => (string) $pending,
            'total' => (string) $total,
        ])) ?></p>
    <?php endif ?>

    <?php if ($channels !== []): ?>
        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('raids.search')) ?></span>
                <input class="input" type="text" id="raid-search"
                       autocomplete="off" spellcheck="false"
                       data-grid="raid-grid"
                       placeholder="<?= $e(translate('raids.search_example')) ?>">
            </label>
        </div>

        <div class="raid-grid" id="raid-grid">
            <?php foreach ($channels as $kachel): ?>
                <?php /*
                    Was das Suchfeld vergleicht, steht am Element und
                    wird hier klein geschrieben: so muss das Skript
                    nicht wissen, WAS durchsucht wird - Anzeigename und
                    Login - und der Vergleich ist eine Zeichenkette
                    gegen eine.
                */ ?>
                <div class="raid-tile"
                     data-search="<?= $e(strtolower($kachel['display_name'] . ' ' . $kachel['login'])) ?>">
                    <?php if ($canEdit): ?>
                        <?php /*
                            Ein Formular je Stern. Der Wert ist das
                            Gegenteil des jetzigen Zustands - der Knopf
                            sagt also, was er tun WILL, und nicht, wie
                            es gerade ist. Sonst muesste der Server
                            nachsehen, und zwei Klicks kurz
                            hintereinander koennten sich aufheben.
                        */ ?>
                        <form method="post" action="<?= $e($url('/stream/raids/favorite')) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="login" value="<?= $e($kachel['login']) ?>">
                            <input type="hidden" name="value" value="<?= $kachel['favorite'] ? '0' : '1' ?>">
                            <button class="raid-star<?= $kachel['favorite'] ? ' is-on' : '' ?>"
                                    type="submit"
                                    title="<?= $e($kachel['favorite']
                                        ? translate('raids.unfavorite')
                                        : translate('raids.favorite')) ?>"
                                    aria-label="<?= $e($kachel['favorite']
                                        ? translate('raids.unfavorite')
                                        : translate('raids.favorite')) ?>">&#9733;</button>
                        </form>
                    <?php elseif ($kachel['favorite']): ?>
                        <span class="raid-star is-on" aria-hidden="true">&#9733;</span>
                    <?php endif ?>

                    <a target="_blank" rel="noopener"
                       href="https://twitch.tv/<?= $e(rawurlencode($kachel['login'])) ?>">
                        <?php if ($kachel['profile_image_url'] !== ''): ?>
                            <img class="raid-avatar" src="<?= $e($kachel['profile_image_url']) ?>" alt="">
                        <?php else: ?>
                            <div class="raid-avatar raid-avatar-empty">
                                <?= $e(strtoupper(substr($kachel['display_name'], 0, 1))) ?>
                            </div>
                        <?php endif ?>

                        <div class="raid-name"><?= $e($kachel['display_name']) ?></div>
                    </a>
                </div>
            <?php endforeach ?>
        </div>

        <p class="hint" id="raid-empty" hidden><?= $e(translate('raids.search_empty')) ?></p>
    <?php elseif ($canRead): ?>
        <?php /*
            Leer aus zwei sehr verschiedenen Gruenden: die Liste ist
            noch nicht geholt, oder sie ist geholt und niemand hat in
            den letzten Tagen gestreamt. Der zweite Fall ist eine
            Auskunft, der erste eine Aufgabe.
        */ ?>
        <?php if ($total === 0): ?>
            <p class="hint"><?= $e(translate('raids.not_synced')) ?></p>
        <?php else: ?>
            <p class="hint"><?= $e(translate('raids.none_active', [
                'days'  => (string) $activeDays,
                'total' => (string) $total,
            ])) ?></p>
        <?php endif ?>
    <?php endif ?>
</div>
