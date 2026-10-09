<?php
/**
 * Der Rahmen der oeffentlichen Umfrage-Seite.
 *
 * Eigenes Stylesheet, nicht admin.css: das bringt Navigation, Tabellen
 * und Karten mit, und davon braucht diese Seite nichts. Wer hier
 * landet, ist kein Benutzer dieses Systems, sondern jemand mit einer
 * einzigen Frage vor sich.
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
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 */
?>
<!doctype html>
<html lang="<?= $e($language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e(($title !== '' ? $title . ' · ' : '') . ($brand !== '' ? $brand : translate('polls.name'))) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= $e($asset('/plugin/polls/assets/public.css')) ?>">
</head>
<body>

<header class="pp-top">
    <span class="pp-brand">
        <span class="pp-dot"></span>
        <?= $e($brand !== '' ? $brand : translate('polls.name')) ?>
    </span>
    <?php if ($identity !== null): ?>
        <span class="pp-who"><?= $e($identity['display_name']) ?></span>
    <?php endif ?>
</header>

<main class="pp">
    <?= $content ?>
</main>

<script src="<?= $e($asset('/plugin/polls/assets/public.js')) ?>"></script>
</body>
</html>
