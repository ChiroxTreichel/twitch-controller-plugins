<?php
/**
 * Der Kopf jeder oeffentlichen Seite.
 *
 * Aufbau und Klassennamen kommen aus dem alten spenden.talutah.de:
 * Kopfzeile mit Marke links und Anmeldung rechts, darunter der Inhalt
 * in einer schmalen Spalte. Die Ueberschrift steht HIER und nicht in
 * den einzelnen Seiten - im alten System stand sie auch im Rahmen, und
 * so kann keine Seite sie vergessen.
 *
 * Eigenes Stylesheet, nicht admin.css: das bringt Navigation, Tabellen
 * und Karten mit, und davon braucht diese Seite nichts. Wer hier
 * landet, ist kein Benutzer dieses Systems, sondern jemand mit einem
 * einzigen Anliegen.
 *
 * noindex: oeffentlich ist die Seite, weil sie ohne Anmeldung
 * erreichbar sein muss - nicht, damit sie in einer Suchmaschine steht.
 *
 * @var callable $e
 * @var callable $url
 * @var callable $asset
 * @var string $language
 * @var string $brand
 * @var string $heading
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 */
?>
<!doctype html>
<html lang="<?= $e($language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e(($heading !== '' ? $heading . ' · ' : '') . $brand) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= $e($asset('/plugin/paypal-tip-goals/assets/tips.css')) ?>">

    <?php /*
        Ohne JavaScript ist das Karussell keins: sichtbar waere genau
        eine Karte, und weiterblaettern koennte niemand. Dann liegen
        alle Ziele untereinander und die Radioknoepfe, die sonst
        verborgen sind, treten hervor. Aussehen verliert man dabei -
        die Auswahl nicht.
    */ ?>
    <noscript>
        <style>
            .goal-carousel-viewport { overflow: visible; }
            .goal-carousel-track { display: grid; gap: 4px; transform: none !important; }
            .goal-slide { flex: none; display: flex; gap: 12px; align-items: flex-start; }
            .goal-slide > input[type="radio"] {
                position: static;
                width: 18px;
                height: 18px;
                margin-top: 4px;
                opacity: 1;
                pointer-events: auto;
                accent-color: var(--accent);
            }
            .goal-slide-body { flex: 1 1 auto; min-width: 0; }
            .goal-carousel-nav,
            .goal-carousel-dots,
            .net-info { display: none; }
        </style>
    </noscript>
</head>
<body>
<header class="page-header">
    <a class="brand" href="<?= $e($url('/tips')) ?>">
        <?= $e($brand) ?> <span><?= $e(translate('pp_tip.public.brand_word')) ?></span>
    </a>

    <nav class="user-nav">
        <?php if ($identity !== null): ?>
            <span class="user-name">@<?= $e($identity['login']) ?></span>
            <a class="link-button" href="<?= $e($url('/tips/logout')) ?>">
                <?= $e(translate('pp_tip.logout')) ?>
            </a>
        <?php endif ?>
    </nav>
</header>

<main class="page-main">
    <?php if ($heading !== ''): ?>
        <h1><?= $e($heading) ?></h1>
    <?php endif ?>
