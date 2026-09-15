<?php
/**
 * Die Seite ist nicht eingerichtet.
 *
 * Kein Impressum - dann geht sie nicht online. Und kein Wort darueber,
 * WAS fehlt: das ist eine Auskunft fuer den Betreiber, nicht fuer den
 * Besucher, und sie stuende sonst fuer jeden im Netz.
 *
 * @var callable $e
 * @var callable $view
 * @var string $brand
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var list<string> $legal
 */

$heading = translate('pp_tip.public.closed');
echo $view->render('public/_head', compact('brand', 'heading', 'identity'), null);
?>

<div class="hero">
    <p class="lead"><?= $e(translate('pp_tip.public.closed_hint')) ?></p>
</div>

<?= $view->render('public/_foot', compact('brand', 'legal'), null) ?>
