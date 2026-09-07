<?php
/**
 * Der Reiter "Live": welche Favoriten gerade streamen.
 *
 * Frisch bei Twitch gefragt und nicht gespeichert - "gerade live" ist
 * in einer Minute falsch, und ein gespeicherter Wert waere genau die
 * Art Auskunft, auf die man sich verlaesst und die dann nicht stimmt.
 *
 * Zwei Stellen zum Einhaengen, und beide sind hier:
 *
 *   $actions      Knoepfe ueber dem Gitter - dort haengt das Roulette
 *   $tileActions  Knoepfe in der Kachel   - dort haengt der Raid-Knopf
 *
 * Deshalb ist die Kachel ein <div> mit einem <a> darin und nicht
 * selbst ein Link: ein Formular in einem Link ist kein gueltiges HTML,
 * und der Browser zieht den Knopf sonst aus der Kachel heraus.
 *
 * @var callable $e
 * @var callable $url
 * @var list<array{login: string, display_name: string, title: string, game_name: string, profile_image_url: string}> $live
 * @var int $favorites   Wie viele Favoriten es ueberhaupt gibt
 * @var string $loadError
 * @var list<array{order: int, render: callable}> $actions
 * @var list<array{order: int, render: callable}> $tileActions
 */
?>
<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('raids.tab.live')) ?></h2>

        <?php if ($live !== []): ?>
            <span class="hint"><?= $e(translate('raids.live_count', [
                'count' => (string) count($live),
            ])) ?></span>
        <?php endif ?>
    </div>

    <?php if ($loadError !== ''): ?>
        <div class="note note-error">
            <strong><?= $e(translate('raids.live_failed')) ?></strong>
            <span class="mono"><?= $e($loadError) ?></span>
        </div>
    <?php endif ?>

    <?php /*
        Die Knoepfe ueber dem Gitter - nur, wenn ueberhaupt jemand live
        ist. Ein Roulette ohne Teilnehmer waere ein Knopf, der nichts
        tut.
    */ ?>
    <?php if ($live !== [] && $actions !== []): ?>
        <div class="row raid-actions">
            <?php foreach ($actions as $knopf): ?>
                <?= ($knopf['render'])() ?>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if ($live !== []): ?>
        <div class="raid-grid" id="raid-live-grid">
            <?php foreach ($live as $kachel): ?>
                <div class="raid-tile" data-raid-login="<?= $e($kachel['login']) ?>">
                    <?php /*
                        rel="noopener" gehoert dazu: ohne das bekaeme die
                        geoeffnete Seite ueber window.opener Zugriff auf
                        diese - und das ist die Adminoberflaeche.
                    */ ?>
                    <a target="_blank" rel="noopener"
                       href="https://twitch.tv/<?= $e(rawurlencode($kachel['login'])) ?>"
                       title="<?= $e($kachel['title']) ?>">
                        <?php if ($kachel['profile_image_url'] !== ''): ?>
                            <img class="raid-avatar" src="<?= $e($kachel['profile_image_url']) ?>" alt="">
                        <?php else: ?>
                            <?php /*
                                Kein Bild: der erste Buchstabe. Besser als
                                eine leere Flaeche - man erkennt die Kachel
                                am Platz, nicht nur am Namen darunter.
                            */ ?>
                            <div class="raid-avatar raid-avatar-empty">
                                <?= $e(strtoupper(substr($kachel['display_name'], 0, 1))) ?>
                            </div>
                        <?php endif ?>

                        <div class="raid-name"><?= $e($kachel['display_name']) ?></div>

                        <?php if ($kachel['game_name'] !== ''): ?>
                            <div class="raid-meta"><?= $e($kachel['game_name']) ?></div>
                        <?php endif ?>
                    </a>

                    <?php foreach ($tileActions as $knopf): ?>
                        <?= ($knopf['render'])($kachel) ?>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php elseif ($loadError === ''): ?>
        <?php /*
            Zwei verschiedene Gruende fuer eine leere Flaeche, und sie
            brauchen verschiedene Saetze: "niemand ist live" ist eine
            Auskunft, "du hast keine Favoriten" ist eine Aufgabe.
        */ ?>
        <?php if ($favorites === 0): ?>
            <p class="hint"><?= $e(translate('raids.no_favorites')) ?></p>
            <div class="row">
                <a class="btn btn-ghost btn-small" href="<?= $e($url('/networking/raids/follows')) ?>">
                    <?= $e(translate('raids.to_follows')) ?>
                </a>
            </div>
        <?php else: ?>
            <p class="hint"><?= $e(translate('raids.nobody_live')) ?></p>
        <?php endif ?>
    <?php endif ?>
</div>
