<?php
/**
 * Der Chat im Verwaltungsbereich.
 *
 * Ohne Skript ein gewoehnliches Formular: Abschicken laedt die Seite
 * neu, und mit ihr den Verlauf. Mit Skript kommt beides ohne
 * Neuladen - siehe assets/mod-chat.js.
 *
 * @var callable $e
 * @var callable $url
 * @var \TwitchController\Core\Http\View $view
 * @var list<array<string, mixed>> $messages
 * @var array<string, mixed> $config
 * @var string $csrf
 * @var int $maxLength
 * @var string $dockUrl
 * @var string $error
 */
?>
<h1><?= $e(translate('mod_chat.name')) ?></h1>
<p class="lead"><?= $e(translate('mod_chat.lead')) ?></p>

<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif; ?>

<div class="card modchat" data-mod-chat>
    <div class="modchat-window">
        <div class="modchat-log" data-log>
            <?= $view->render('_messages', ['messages' => $messages], null) ?>
        </div>
        <button class="modchat-jump" type="button" data-jump hidden><?= $e(translate('mod_chat.jump')) ?></button>
    </div>

    <div class="note note-error" data-error hidden></div>

    <form class="modchat-form" method="post" action="<?= $e($url('/stream/chat')) ?>" data-form>
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <textarea class="input" name="text" rows="2" maxlength="<?= $e((string) $maxLength) ?>"
                  placeholder="<?= $e(translate('mod_chat.placeholder')) ?>"
                  aria-label="<?= $e(translate('mod_chat.placeholder')) ?>" required></textarea>
        <button class="btn" type="submit"><?= $e(translate('mod_chat.send')) ?></button>
    </form>
    <p class="hint modchat-keys"><?= $e(translate('mod_chat.keys')) ?></p>

    <?php // Ohne $e: JSON fuer das Skript, die HEX-Schalter halten "</script>" heraus. ?>
    <script type="application/json" data-config><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</div>

<p class="hint">
    <?php // Ohne $e: die Platzhalter sind eigenes Markup. ?>
    <?= translate('mod_chat.dock_hint', [
        'menu' => '<strong>' . $e(translate('account.activity.obs_menu')) . '</strong>',
        'url'  => '<span class="mono">' . $e($dockUrl) . '</span>',
    ]) ?>
</p>
<p class="hint"><?= $e(translate('mod_chat.keep_hint', ['days' => (string) \TwitchController\Plugin\ModChat\Messages::KEEP_DAYS])) ?></p>
