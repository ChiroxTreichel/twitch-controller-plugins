<?php
/**
 * Das Dock fuer OBS: nur der Verlauf, ohne Menue und ohne Eingabe.
 *
 * Eine eigene, ganze Seite und nicht das Layout des Kerns - das
 * braechte die Seitenleiste mit, und in einem schmalen Dock bliebe fuer
 * den Chat nichts uebrig. Stylesheet und Skript stehen deshalb hier
 * selbst; admin.assets gilt nur fuer das Layout.
 *
 * @var callable $e
 * @var callable $asset
 * @var \TwitchController\Core\Http\View $view
 * @var string $language
 * @var string $title
 * @var list<array<string, mixed>> $messages
 * @var array<string, mixed> $config
 */
?>
<!doctype html>
<html lang="<?= $e($language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title . ' · ' . \TwitchController\Core\App::NAME) ?></title>
    <link rel="stylesheet" href="<?= $e($asset('/assets/admin.css')) ?>">
    <link rel="stylesheet" href="<?= $e($asset('/plugin/mod-chat/assets/mod-chat.css')) ?>">
</head>
<body class="modchat-dock-body">
<div class="modchat modchat-dock" data-mod-chat>
    <div class="modchat-window">
        <div class="modchat-log" data-log>
            <?= $view->render('_messages', ['messages' => $messages], null) ?>
        </div>
        <button class="modchat-jump" type="button" data-jump hidden><?= $e(translate('mod_chat.jump')) ?></button>
    </div>

    <div class="note note-error" data-error hidden></div>

    <?php // Ohne $e: JSON fuer das Skript, die HEX-Schalter halten "</script>" heraus. ?>
    <script type="application/json" data-config><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>
<script src="<?= $e($asset('/plugin/mod-chat/assets/mod-chat.js')) ?>"></script>
</body>
</html>
