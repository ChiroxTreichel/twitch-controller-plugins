<?php
/**
 * Streaminfo: Titel und Kategorie des laufenden Streams.
 *
 * @var callable $e
 * @var callable $url
 * @var array{title: string, game_id: string, game_name: string, language: string} $current
 * @var string $loadError     Warum der aktuelle Stand fehlt
 * @var bool $canManage       Hat der Kanal die Twitch-Freigabe?
 * @var bool $canEditTitle
 * @var bool $canEditGame
 * @var int $maxTitle
 * @var string $searchUrl
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$ziel = $url('/stream/info');

// Aendern darf nur, wer das Recht UND die Twitch-Freigabe hat. Beides
// getrennt gemeldet, denn es sind zwei sehr verschiedene Gruende.
$titelAendern = $canEditTitle && $canManage;
$spielAendern = $canEditGame && $canManage;
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
    Die fehlende Freigabe steht hier noch einmal ausdruecklich, obwohl
    der Rahmen sie oben schon meldet: dort steht nur, DASS etwas fehlt.
    Hier steht, was deswegen nicht geht - und das ist auf dieser Seite
    alles, was man tun wollte.
*/ ?>
<?php if (!$canManage): ?>
    <div class="note note-warn">
        <strong><?= $e(translate('streaminfo.scope_missing')) ?></strong>
        <?= $e(translate('streaminfo.scope_missing_hint')) ?>
        <?php if (permission('Account.Settings.View')): ?>
            <a href="<?= $e($url('/account/settings/channel')) ?>">
                <?= $e(translate('streaminfo.to_settings')) ?>
            </a>
        <?php endif ?>
    </div>
<?php endif ?>

<?php if ($loadError !== ''): ?>
    <div class="note note-error">
        <strong><?= $e(translate('streaminfo.load_failed')) ?></strong>
        <span class="mono"><?= $e($loadError) ?></span>
    </div>
<?php endif ?>

<div class="card">
    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <h2><?= $e(translate('streaminfo.title_heading')) ?></h2>

        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('streaminfo.field.title')) ?></span>
                <input class="input" type="text" name="title" id="streaminfo-title"
                       maxlength="<?= $e((string) $maxTitle) ?>"
                       value="<?= $e($current['title']) ?>"
                       <?= $titelAendern ? '' : 'readonly' ?>>
            </label>
        </div>

        <h2><?= $e(translate('streaminfo.category_heading')) ?></h2>

        <?php /*
            Die Suche schreibt in zwei verborgene Felder. Abgeschickt
            wird die ID, denn Twitch kennt Kategorien nur daran - ein
            getippter Name waere im besten Fall eindeutig und im
            schlechtesten der falsche Kanal in der Uebersicht.

            Darum steht im sichtbaren Feld auch der Name und nicht die
            ID: wer eine Kategorie-ID von Hand eintippt, hat etwas
            falsch gemacht.
        */ ?>
        <div class="row">
            <label class="field grow streaminfo-search">
                <span class="hint"><?= $e(translate('streaminfo.field.category')) ?></span>
                <input class="input" type="text" id="streaminfo-category"
                       autocomplete="off" spellcheck="false"
                       data-search="<?= $e($searchUrl) ?>"
                       placeholder="<?= $e(translate('streaminfo.category_example')) ?>"
                       value="<?= $e($current['game_name']) ?>"
                       <?= $spielAendern ? '' : 'disabled' ?>>
                <ul class="streaminfo-suggest" id="streaminfo-suggest" hidden></ul>
            </label>
        </div>

        <input type="hidden" name="game_id" id="streaminfo-game-id"
               value="<?= $e($current['game_id']) ?>">

        <?php if ($current['game_name'] === '' && $loadError === ''): ?>
            <p class="hint"><?= $e(translate('streaminfo.no_category')) ?></p>
        <?php endif ?>

        <?php if ($canEditTitle || $canEditGame): ?>
            <div class="row">
                <button class="btn" type="submit" <?= $canManage ? '' : 'disabled' ?>>
                    <?= $e(translate('common.save')) ?>
                </button>
            </div>
        <?php else: ?>
            <p class="hint"><?= $e(translate('streaminfo.read_only')) ?></p>
        <?php endif ?>
    </form>
</div>
