<?php
/**
 * Der Rahmen der Raid-Seite: Kopf, Meldungen, Reiter.
 *
 * Raids bringt zwei Reiter mit; das Raiden selbst, das Roulette und die
 * Anfragen kommen als eigene Plugins dazu - siehe Raids::tabs().
 *
 * Die Meldungen stehen HIER und nicht in den Reitern. Jeder Reiter
 * schickt sein Formular an seine eigene Adresse und landet mit
 * ?notice=… wieder hier; stuende der Kasten auch im Reiter, saehe man
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
<h1><?= $e(translate('raids.name')) ?></h1>
<p class="lead"><?= $e(translate('raids.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /*
    Ein einzelner Reiter ist keine Auswahl - dann steht nur der Inhalt
    da. Bei Raids sind es immer zwei, aber die Regel gilt trotzdem:
    wer die Rechte nur fuer einen hat, soll keine Leiste mit einem
    Eintrag sehen.
*/ ?>
<?php if (count($tabs) > 1): ?>
    <div class="tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <a class="tab<?= $open === $key ? ' is-active' : '' ?>"
               href="<?= $e($url('/stream/raids/' . rawurlencode((string) $key))) ?>"><?= $e($tab['label']) ?></a>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?= $content ?>
