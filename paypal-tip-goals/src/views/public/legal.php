<?php
/**
 * Impressum, Datenschutz oder AGB.
 *
 * Der Text kommt aus den Einstellungen und ist durch denselben
 * Markdown-Wandler gegangen wie die Plugin-Beschreibungen - darum ohne
 * $e(): er ist bereits HTML, und zwar gesaeubertes.
 *
 * @var callable $e
 * @var callable $view
 * @var string $brand
 * @var string $heading
 * @var string $body
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var list<string> $legal
 */

echo $view->render('public/_head', compact('brand', 'heading', 'identity'), null);
?>

<h1><?= $e($heading) ?></h1>

<div class="tp-text"><?= $body ?></div>

<?= $view->render('public/_foot', compact('legal'), null) ?>
