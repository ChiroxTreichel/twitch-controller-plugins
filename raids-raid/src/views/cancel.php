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
 * @var string $watch  Login des Raid-Ziels direkt nach dem Start, sonst leer
 */
?>
<form method="post" action="<?= $e($url('/networking/raids/raid/cancel')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <button class="btn btn-small btn-danger" type="submit">
        <?= $e(translate('raids_raid.cancel')) ?>
    </button>
</form>

<?php if ($watch !== ''): ?>
    <?php /*
        Die Rueckfrage nach dem Start: den Stream des Ziels in einem
        eigenen Fenster oeffnen? Man sieht ihn sonst nicht - die eigene
        Seite zeigt nur Kacheln.

        Ein Link und kein Knopf: ohne JavaScript oeffnet er Twitch in
        einem neuen Tab, mit JavaScript in einem eigenen Fenster
        (raids-raid.js). Das Fenster geht erst auf den Klick hin auf -
        ein Fenster, das die Seite von selbst oeffnet, haelt jeder
        Browser fuer Werbung und blockiert es.
    */ ?>
    <dialog class="raid-watch" data-raid-watch>
        <h2><?= $e(translate('raids_raid.watch.title', ['name' => $watch])) ?></h2>
        <p><?= $e(translate('raids_raid.watch.text')) ?></p>
        <p class="hint" data-raid-watch-blocked hidden><?= $e(translate('raids_raid.watch.blocked')) ?></p>
        <div class="raid-watch-actions">
            <button class="btn btn-ghost btn-small" type="button" data-raid-watch-close>
                <?= $e(translate('raids_raid.watch.no')) ?>
            </button>
            <a class="btn btn-small raid-start" data-raid-watch-open
               href="<?= $e('https://www.twitch.tv/' . rawurlencode($watch)) ?>"
               target="_blank" rel="noopener">
                <?= $e(translate('raids_raid.watch.yes')) ?>
            </a>
        </div>
    </dialog>
<?php endif ?>
