<?php
/**
 * Nach der Rueckkehr von PayPal.
 *
 * Dieselbe Seite fuer geglueckt und gescheitert, nur mit anderem Text:
 * wer hier landet, hat eine Frage - "ist mein Geld angekommen?" - und
 * die wird in der Ueberschrift beantwortet.
 *
 * Die Karte darunter ist die des alten Systems, dort hiess sie
 * "Spende vorbereitet".
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

<div class="placeholder-box">
    <?php /*
        Der Rahmen traegt die Farbe mit - Farbe allein unterscheidet
        "angekommen" und "nicht durchgegangen" sonst fuer niemanden,
        der sie nicht unterscheiden kann.
    */ ?>
    <div class="flash <?= $ok ? 'flash-ok' : 'flash-error' ?>"><?= $e($body) ?></div>

    <p>
        <a class="primary-button" href="<?= $e($url('/tips')) ?>">
            <?= $e(translate('pp_tip.done.back')) ?>
        </a>
    </p>
</div>

<?= $view->render('public/_foot', compact('brand', 'legal'), null) ?>
