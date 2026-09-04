<?php
/**
 * Der Rahmen der Streaminfo-Seite: Kopf, Meldungen, Reiter.
 *
 * Streaminfo bringt einen Reiter mit - Titel und Kategorie, in
 * views/tab.php. Was daran haengt, kommt als eigener Reiter dazu; siehe
 * Streaminfo::tabs().
 *
 * Die Meldungen stehen HIER und nicht in den Reitern. Jeder Reiter
 * schickt sein Formular an seine eigene Adresse und landet mit
 * ?notice=… wieder hier - stuende der Kasten auch im Reiter, saehe man
 * ihn zweimal.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, array{label: string, order: int, render: callable|null}> $tabs
 * @var string $open
 * @var string $content
 * @var string $notice
 * @var string $error
 */
?>
<h1><?= $e(translate('streaminfo.name')) ?></h1>
<p class="lead"><?= $e(translate('streaminfo.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /*
    Ein einzelner Reiter ist keine Auswahl - dann steht nur der Inhalt
    da. Die Leiste erscheint erst, wenn eine Erweiterung installiert
    ist und es wirklich etwas zu waehlen gibt.
*/ ?>
<?php if (count($tabs) > 1): ?>
    <div class="tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <a class="tab<?= $open === $key ? ' is-active' : '' ?>"
               href="<?= $e($url('/stream/info/' . rawurlencode((string) $key))) ?>"><?= $e($tab['label']) ?></a>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?= $content ?>
