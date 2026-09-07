<?php
/**
 * Der Abbruch-Knopf ueber dem Live-Gitter.
 *
 * Steht nur da, solange ein Raid im Vorlauf sein KANN - siehe
 * Raid::pending(). Gefragt wird das nicht bei Twitch: dafuer gibt es
 * keinen Endpunkt, gemerkt wird der Start.
 *
 * @var callable $e
 * @var callable $url
 * @var string $csrf
 */
?>
<form method="post" action="<?= $e($url('/networking/raids/raid/cancel')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <button class="btn btn-small btn-danger" type="submit">
        <?= $e(translate('raids_raid.cancel')) ?>
    </button>
</form>
