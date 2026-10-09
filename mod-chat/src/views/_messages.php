<?php
/**
 * Die Zeilen des Verlaufs, fuer Seite und Dock.
 *
 * Dasselbe Markup baut mod-chat.js fuer jede Nachricht, die danach
 * dazukommt - wer hier etwas aendert, aendert es dort mit.
 *
 * @var callable $e
 * @var list<array{id: int, name: string, text: string, time: string, day: string, color: string, own: bool}> $messages
 */

$tag = '';
?>
<?php if ($messages === []): ?>
    <p class="empty" data-empty><?= $e(translate('mod_chat.empty')) ?></p>
<?php endif; ?>
<?php foreach ($messages as $nachricht): ?>
    <?php if ($nachricht['day'] !== $tag): ?>
        <?php $tag = $nachricht['day']; ?>
        <div class="modchat-day" data-day="<?= $e($tag) ?>"><span><?= $e($tag) ?></span></div>
    <?php endif; ?>
    <div class="modchat-line<?= $nachricht['own'] ? ' is-own' : '' ?>" data-id="<?= $e((string) $nachricht['id']) ?>">
        <time class="modchat-time"><?= $e($nachricht['time']) ?></time>
        <strong class="modchat-name" style="color: <?= $e($nachricht['color']) ?>"><?= $e($nachricht['name']) ?></strong>
        <span class="modchat-text"><?= $e($nachricht['text']) ?></span>
    </div>
<?php endforeach; ?>
