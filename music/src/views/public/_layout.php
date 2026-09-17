<?php
/**
 * Der Rahmen der oeffentlichen Musikseite.
 *
 * Aufbau Zeile fuer Zeile aus dem alten musik.talutah.de
 * (public/index.php): oben eine Leiste mit "Regeln anzeigen" links und
 * "Einstellungen" rechts, darunter das Regelfenster, darunter die
 * Seite. Geaendert sind nur die Farben - der Stil dieses Systems statt
 * des hellen Blau von damals.
 *
 * Zwei Dinge kommen hinzu, die es dort nicht gab: der Kanalname neben
 * dem Regelknopf, und das Abmelden. Das alte System lief fuer genau
 * einen Kanal und meldete niemanden ab.
 *
 * Eigenes Stylesheet, nicht admin.css: das bringt Navigation,
 * Tabellen und Karten mit, und davon braucht diese Seite nichts. Wer
 * hier landet, ist kein Benutzer dieses Systems, sondern jemand mit
 * einem einzigen Anliegen.
 *
 * noindex: oeffentlich ist die Seite, weil sie ohne Anmeldung
 * erreichbar sein muss - nicht, damit sie in einer Suchmaschine steht.
 *
 * @var callable $e
 * @var callable $url
 * @var callable $asset
 * @var string $language
 * @var string $content
 * @var string $title
 * @var string $brand
 * @var string $csrf
 * @var list<string> $rules
 * @var bool $accepted
 * @var bool $canSettings
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 */

/*
 * Verschlossen ist das Fenster, solange jemand angemeldet ist und die
 * Regeln noch nicht angenommen hat - dann geht es von selbst auf und
 * laesst sich nicht wegklicken. Genau so im alten System
 * ($shouldShowRulesModal).
 */
$regelnZeigen = $identity !== null && !$accepted;
?>
<!doctype html>
<html lang="<?= $e($language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e(($title !== '' ? $title . ' · ' : '') . ($brand !== '' ? $brand : 'Musik')) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= $e($asset('/plugin/music/assets/music.css')) ?>">
</head>
<body>

<div id="topmenu">
    <div class="topmenu-left">
        <span class="brand">
            <span class="brand-dot"></span>
            <span class="brand-name"><?= $e($brand !== '' ? $brand : translate('music.public.title')) ?></span>
        </span>

        <button type="button" class="link-button" id="open_rules_btn">
            📜 <?= $e(translate('music.public.rules_show')) ?>
        </button>
    </div>

    <div class="topmenu-right">
        <?php if ($identity !== null): ?>
            <span class="who-name"><?= $e($identity['display_name']) ?></span>
            <a href="<?= $e($url('/music/logout')) ?>"><?= $e(translate('music.public.logout')) ?></a>
        <?php endif ?>

        <?php /*
            Im alten System hing dieser Verweis an zwei fest
            eingetragenen Twitch-Kennungen. Hier haengt er an einem
            Recht - siehe Privilege.
        */ ?>
        <?php if ($canSettings): ?>
            <a href="<?= $e($url('/display/music')) ?>"><?= $e(translate('music.public.settings')) ?></a>
        <?php endif ?>
    </div>
</div>

<div id="rules_modal"
     class="modal-overlay"
     data-show="<?= $regelnZeigen ? 'true' : 'false' ?>"
     data-locked="<?= $regelnZeigen ? 'true' : 'false' ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="rules_modal_title" tabindex="-1">
        <header class="modal-header">
            <h2 id="rules_modal_title">📜 <?= $e(translate('music.public.rules_title')) ?></h2>
            <?php if (!$regelnZeigen): ?>
                <button type="button" class="modal-close" data-close-modal
                        aria-label="<?= $e(translate('music.public.modal_close')) ?>">&times;</button>
            <?php endif ?>
        </header>

        <div class="modal-body">
            <ul class="rules-list">
                <?php foreach ($rules as $regel): ?>
                    <li><?= $e($regel) ?></li>
                <?php endforeach ?>
            </ul>

            <?php if ($regelnZeigen): ?>
                <form method="post" action="<?= $e($url('/music')) ?>" class="accept-rules" id="rules_accept_form">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="accept_rules">
                    <button type="submit" class="primary" autofocus><?= $e(translate('music.public.accept')) ?></button>
                </form>
            <?php elseif ($identity !== null): ?>
                <p class="muted"><?= $e(translate('music.public.rules_already')) ?></p>
                <div class="modal-actions">
                    <button type="button" class="primary" data-close-modal><?= $e(translate('music.public.rules_ok')) ?></button>
                </div>
            <?php else: ?>
                <p class="muted"><?= $e(translate('music.public.rules_login')) ?></p>
                <div class="modal-actions">
                    <button type="button" class="primary" data-close-modal><?= $e(translate('music.public.rules_close')) ?></button>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<div id="pagewrapper">
    <?= $content ?>
</div>

<script src="<?= $e($asset('/plugin/music/assets/music.js')) ?>"></script>
</body>
</html>
