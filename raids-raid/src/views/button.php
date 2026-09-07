<?php
/**
 * Der Raid-Knopf einer Live-Kachel.
 *
 * Ein Formular und kein Link: Raiden aendert etwas, und ein Link, der
 * etwas aendert, wird irgendwann von einem Vorlader angeklickt, den
 * niemand bestellt hat.
 *
 * data-raid-start traegt der Knopf fuer das Roulette: das findet
 * darueber den Knopf in der Gewinnerkachel und drueckt ihn. Ist das
 * Roulette nicht installiert, ist es ein Attribut, das niemand liest.
 *
 * Keine Rueckfrage. Twitch gibt neunzig Sekunden Vorlauf, und darueber
 * steht ein Knopf zum Abbrechen - eine Rueckfrage waere ein Klick fuer
 * etwas, das man ohnehin noch zurueckholen kann.
 *
 * @var callable $e
 * @var callable $url
 * @var string $login
 * @var string $name
 * @var bool $ready   Twitch-Freigabe vorhanden?
 * @var string $csrf
 */
?>
<form method="post" action="<?= $e($url('/networking/raids/raid')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="login" value="<?= $e($login) ?>">

    <?php /*
        Ohne Freigabe bleibt der Knopf sichtbar und wird abgeschaltet.
        Verstecken waere schlechter: dann fehlte auf der Seite etwas,
        von dem man gelesen hat, und niemand wuesste, warum.
    */ ?>
    <button class="btn btn-small raid-start" type="submit"
            data-raid-start
            <?= $ready ? '' : 'disabled' ?>
            title="<?= $e($ready
                ? translate('raids_raid.start_hint', ['name' => $name])
                : translate('raids_raid.scope_missing')) ?>">
        <?= $e(translate('raids_raid.start')) ?>
    </button>
</form>
