<?php
/**
 * Der Kopf jeder oeffentlichen Seite.
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
</head>
<body>
<header class="tp-head">
    <a class="tp-brand" href="<?= $e($url('/tips')) ?>"><?= $e($brand) ?></a>

    <?php if ($identity !== null): ?>
        <div class="tp-user">
            <span>@<?= $e($identity['login']) ?></span>
            <a class="tp-quiet" href="<?= $e($url('/tips/logout')) ?>"><?= $e(translate('pp_tip.logout')) ?></a>
        </div>
    <?php endif ?>
</header>

<main class="tp-main">
