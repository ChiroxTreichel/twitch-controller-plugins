<?php
/**
 * Nach der Rueckkehr von PayPal.
 *
 * Dieselbe Seite fuer geglueckt und gescheitert, nur mit anderem Text:
 * wer hier landet, hat eine Frage - "ist mein Geld angekommen?" - und
 * die wird oben beantwortet.
 *
 * @var callable $e
 * @var callable $url
 * @var callable $view
 * @var string $brand
 * @var bool $ok
 * @var string $heading
 * @var string $body
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var list<string> $legal
 */

echo $view->render('public/_head', compact('brand', 'heading', 'identity'), null);
?>

<div class="tp-note <?= $ok ? 'tp-ok' : 'tp-error' ?>">
    <strong><?= $e($heading) ?></strong>
</div>

<p class="tp-lead"><?= $e($body) ?></p>

<a class="tp-button" href="<?= $e($url('/tips')) ?>"><?= $e(translate('pp_tip.done.back')) ?></a>

<?= $view->render('public/_foot', compact('legal'), null) ?>
