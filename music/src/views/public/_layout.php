<?php
/**
 * Der Rahmen der oeffentlichen Musikseite.
 *
 * Aufbau wie im alten musik.talutah.de - Kopfzeile mit Marke links und
 * Anmeldung rechts, darunter eine schmale Spalte - aber im Farbstil
 * dieses Systems statt im hellen Blau von damals.
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
 * @var list<string> $rules
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 */
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
<header class="top">
    <a class="brand" href="<?= $e($url('/music')) ?>">
        <span class="brand-dot"></span>
        <span><?= $e($brand !== '' ? $brand : translate('music.public.title')) ?></span>
    </a>

    <?php /*
        Die Regeln stehen oben links neben dem Kanalnamen und nicht
        unten auf der Seite: sie gelten fuer alles, was man hier tut,
        und wer sie nachlesen will, sucht sie dort, wo er hergekommen
        ist. Im alten System war es ein Knopf "Regeln anzeigen" - hier
        ein <details>, das ohne JavaScript auskommt.
    */ ?>
    <details class="rules-pop">
        <summary><?= $e(translate('music.public.rules_title')) ?></summary>
        <div class="rules-panel">
            <ul class="rules">
                <?php foreach ($rules as $regel): ?>
                    <li><?= $e($regel) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    </details>

    <div class="who">
        <?php if ($identity === null): ?>
            <a class="btn btn-small" href="<?= $e($url('/music/login')) ?>">
                <?= $e(translate('music.public.login')) ?>
            </a>
        <?php else: ?>
            <span class="who-name"><?= $e($identity['display_name']) ?></span>
            <a class="btn btn-ghost btn-small" href="<?= $e($url('/music/logout')) ?>">
                <?= $e(translate('music.public.logout')) ?>
            </a>
        <?php endif ?>
    </div>
</header>

<main class="page">
    <?= $content ?>
</main>

<footer class="foot">
    <span><?= $e(translate('music.public.powered')) ?></span>
</footer>

<script src="<?= $e($asset('/plugin/music/assets/music.js')) ?>"></script>
</body>
</html>
