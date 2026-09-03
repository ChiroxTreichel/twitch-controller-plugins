<?php

declare(strict_types=1);

/**
 * Der Rahmen: Titel, Reiter, Meldungen. Den Inhalt bringt basic.php
 * oder custom.php - siehe plugin.php.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, array{label: string, permission: string}> $tabs
 * @var string $open
 * @var string $content
 * @var string $notice
 * @var string $error
 */
?>
<div class="tabs">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="tab<?= $open === $key ? ' is-active' : '' ?>"
           href="<?= $e($url('/chat/commands/' . rawurlencode((string) $key))) ?>"><?= $e($tab['label']) ?></a>
    <?php endforeach ?>
</div>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?= $content ?>
