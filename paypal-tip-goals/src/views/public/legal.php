<?php
/**
 * Impressum, Datenschutz oder AGB.
 *
 * Der Text kommt aus den Einstellungen und ist durch denselben
 * Markdown-Wandler gegangen wie die Plugin-Beschreibungen - darum ohne
 * $e(): er ist bereits HTML, und zwar gesaeubertes.
 *
 * Die Ueberschrift steht im Rahmen und nicht hier.
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

<div class="legal"><?= $body ?></div>

<?= $view->render('public/_foot', compact('brand', 'legal'), null) ?>
