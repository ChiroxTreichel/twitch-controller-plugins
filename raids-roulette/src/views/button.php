<?php
/**
 * Der Wuerfel.
 *
 * Ein <button type="button"> und kein Formular: hier wird nichts
 * abgeschickt, das Roulette laeuft im Browser. Ohne JavaScript ist es
 * ein Knopf, der nichts tut - darum sagt data-roulette dem Skript, wo
 * er ist, und das Skript blendet ihn ein. Ohne Skript ist er nicht da.
 *
 * @var callable $e
 */
?>
<button class="btn btn-small raid-roulette" type="button"
        data-roulette
        data-grid="raid-live-grid"
        hidden
        title="<?= $e(translate('raids_roulette.spin_hint')) ?>">
    <span aria-hidden="true">&#127922;</span>
    <span><?= $e(translate('raids_roulette.spin')) ?></span>
</button>
