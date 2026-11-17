<?php
/**
 * Blur Chats: Kategorien oben, darunter der Editor der gewaehlten.
 *
 * Der Editor ist eine Flaeche im Seitenverhaeltnis der Buehne. Darin
 * steht die Buehne in voller Groesse und wird nur verkleinert gezeigt
 * (blur-chats.js) - so gelten Rand und Ecken in der Vorschau genau so
 * wie im Overlay, und alle Zahlen bleiben Buehnenpixel.
 *
 * Jeder Bereich ist darunter eine eigene Zeile. Ohne JavaScript bleibt
 * die Seite bedienbar: man aendert die Zahlen in den Zeilen und
 * speichert.
 *
 * @var callable $e
 * @var callable $url
 * @var \TwitchController\Core\Http\View $view
 * @var bool $enabled
 * @var list<array{id: string, name: string, count: int}> $categories
 * @var array{id: string, name: string} $current
 * @var array{id: string, name: string, count: int}|null $selected
 * @var list<array<string, mixed>> $regions
 * @var array{width: int, height: int} $stage
 * @var string $screenUrl
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\BlurChats\Regions;

$ziel = $url('/stream/blur-chats');
$schreibschutz = $canEdit ? '' : 'readonly';

$bekannt = array_column($categories, 'id');
$laufendBekannt = $current['id'] !== '' && in_array($current['id'], $bekannt, true);

/**
 * Eine Zeile des Editors. Auch fuer die Vorlage, aus der das Skript
 * neue Zeilen baut - dann mit $i = null und den Vorgaben.
 *
 * @param array<string, mixed> $b
 */
$zeile = static function (?int $i, array $b) use ($e, $schreibschutz, $canEdit): void {
    $name = static fn (string $feld): string => $i === null ? '' : 'regions[' . $i . '][' . $feld . ']';
    ?>
    <tr class="bc-row" data-bc-row>
        <td>
            <input class="input bc-label" type="text" data-field="label" name="<?= $e($name('label')) ?>"
                   value="<?= $e((string) $b['label']) ?>" maxlength="<?= $e((string) Regions::MAX_LABEL) ?>"
                   placeholder="<?= $e(translate('blur_chats.field.label_example')) ?>"
                   aria-label="<?= $e(translate('blur_chats.col.label')) ?>" <?= $schreibschutz ?>>
        </td>
        <?php foreach (['x', 'y', 'w', 'h'] as $feld): ?>
            <td>
                <input class="input bc-num" type="number" data-field="<?= $e($feld) ?>" name="<?= $e($name($feld)) ?>"
                       value="<?= $e((string) $b[$feld]) ?>" step="1"
                       min="<?= $e(in_array($feld, ['w', 'h'], true) ? (string) Regions::MIN_SIZE : '0') ?>"
                       aria-label="<?= $e(match ($feld) {
                           'x'     => translate('blur_chats.col.x'),
                           'y'     => translate('blur_chats.col.y'),
                           'w'     => translate('blur_chats.col.w'),
                           default => translate('blur_chats.col.h'),
                       }) ?>" <?= $schreibschutz ?>>
            </td>
        <?php endforeach ?>
        <td>
            <input class="bc-color" type="color" data-field="color" name="<?= $e($name('color')) ?>"
                   value="<?= $e((string) $b['color']) ?>"
                   aria-label="<?= $e(translate('blur_chats.col.color')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
        </td>
        <td>
            <span class="bc-opacity">
                <input type="range" data-field="opacity" name="<?= $e($name('opacity')) ?>"
                       value="<?= $e((string) $b['opacity']) ?>" min="0" max="100" step="1"
                       aria-label="<?= $e(translate('blur_chats.col.opacity')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                <output class="hint mono" data-bc-percent><?= $e((string) $b['opacity']) ?> %</output>
            </span>
        </td>
        <td>
            <input class="input bc-num" type="number" data-field="feather" name="<?= $e($name('feather')) ?>"
                   value="<?= $e((string) $b['feather']) ?>" min="0" max="<?= $e((string) Regions::MAX_FEATHER) ?>" step="1"
                   aria-label="<?= $e(translate('blur_chats.col.feather')) ?>" <?= $schreibschutz ?>>
        </td>
        <td>
            <input class="input bc-num" type="number" data-field="radius" name="<?= $e($name('radius')) ?>"
                   value="<?= $e((string) $b['radius']) ?>" min="0" max="<?= $e((string) Regions::MAX_RADIUS) ?>" step="1"
                   aria-label="<?= $e(translate('blur_chats.col.radius')) ?>" <?= $schreibschutz ?>>
        </td>
        <td class="actions">
            <?php if ($canEdit): ?>
                <button class="btn btn-ghost btn-small" type="button" data-bc-remove
                        title="<?= $e(translate('blur_chats.region_remove')) ?>">&ndash;</button>
            <?php endif ?>
        </td>
    </tr>
    <?php
};
?>
<h1><?= $e(translate('blur_chats.name')) ?></h1>
<p class="lead"><?= $e(translate('blur_chats.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>
<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('blur_chats.off_hint')) ?></div>
<?php endif ?>

<?php /* ================= Kategorien ================= */ ?>
<div class="card">
    <div class="head-row">
        <h2><?= $e(translate('blur_chats.categories')) ?></h2>
        <?php if ($current['id'] !== ''): ?>
            <span class="badge badge-ok"><?= $e(translate('blur_chats.live_now', ['name' => $current['name']])) ?></span>
        <?php else: ?>
            <span class="badge badge-off"><?= $e(translate('blur_chats.no_live')) ?></span>
        <?php endif ?>
    </div>

    <?php if ($categories === []): ?>
        <div class="empty"><?= $e(translate('blur_chats.categories_empty')) ?></div>
    <?php else: ?>
        <ul class="bc-cats">
            <?php foreach ($categories as $k): ?>
                <?php
                $ist = $selected !== null && $selected['id'] === $k['id'];
                $laeuft = $k['id'] === $current['id'];
                ?>
                <li>
                    <a class="bc-cat<?= $ist ? ' is-selected' : '' ?><?= $laeuft ? ' is-live' : '' ?>"
                       href="<?= $e($ziel . '?' . http_build_query(['category' => $k['id']])) ?>">
                        <span class="bc-cat-name"><?= $e($k['name']) ?></span>
                        <span class="hint"><?= $e(translate('blur_chats.region_count', ['count' => (string) $k['count']])) ?></span>
                        <?php if ($laeuft): ?>
                            <span class="badge badge-ok"><?= $e(translate('blur_chats.live')) ?></span>
                        <?php endif ?>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <?php if ($canEdit): ?>
        <div class="row bc-add">
            <?php /*
                Die Suche schreibt in zwei verborgene Felder. Abgeschickt
                wird die ID, denn Twitch kennt Kategorien nur daran - der
                Name steht nur zur Anzeige dabei.
            */ ?>
            <form class="row grow" method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="add_category">
                <input type="hidden" name="category_id" data-bc-search-id>
                <input type="hidden" name="category_name" data-bc-search-name>
                <label class="field grow bc-search">
                    <span class="hint"><?= $e(translate('blur_chats.field.search')) ?></span>
                    <input class="input" type="text" data-bc-search
                           autocomplete="off" spellcheck="false"
                           data-url="<?= $e($url('/stream/blur-chats/categories')) ?>"
                           placeholder="<?= $e(translate('blur_chats.field.search_example')) ?>">
                    <ul class="bc-suggest" data-bc-suggest hidden></ul>
                </label>
                <button class="btn" type="submit"><?= $e(translate('blur_chats.add_category')) ?></button>
            </form>

            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="add_current">
                <button class="btn btn-ghost" type="submit" <?= $current['id'] === '' ? 'disabled' : '' ?>
                        title="<?= $e($current['id'] !== '' ? $current['name'] : translate('blur_chats.no_live')) ?>">
                    <?= $e(translate('blur_chats.add_current')) ?>
                </button>
            </form>
        </div>
        <?php if ($current['id'] !== '' && !$laufendBekannt): ?>
            <p class="hint"><?= $e(translate('blur_chats.current_missing', ['name' => $current['name']])) ?></p>
        <?php endif ?>
    <?php endif ?>
</div>

<?php /* ================= Editor ================= */ ?>
<?php if ($selected !== null): ?>
    <div class="card bc-card"
         data-bc-editor
         data-stage-w="<?= $e((string) $stage['width']) ?>"
         data-stage-h="<?= $e((string) $stage['height']) ?>"
         data-can-edit="<?= $canEdit ? '1' : '0' ?>"
         data-max="<?= $e((string) Regions::MAX_REGIONS) ?>"
         data-upload-url="<?= $e($url('/stream/blur-chats/screen')) ?>"
         data-csrf="<?= $e($csrf) ?>"
         data-category="<?= $e($selected['id']) ?>"
         data-category-name="<?= $e($selected['name']) ?>"
         data-text-uploading="<?= $e(translate('blur_chats.screen_uploading')) ?>"
         data-text-uploaded="<?= $e(translate('blur_chats.screen_uploaded')) ?>"
         data-text-failed="<?= $e(translate('blur_chats.screen_failed')) ?>"
         data-text-no-image="<?= $e(translate('blur_chats.error.screen_empty')) ?>">

        <div class="head-row">
            <h2><?= $e($selected['name']) ?></h2>
            <?php if ($canEdit): ?>
                <span class="right">
                    <?= $view->render('_confirm', [
                        'label'    => translate('blur_chats.remove_category'),
                        'question' => translate('blur_chats.remove_question', ['name' => $selected['name']]),
                        'note'     => translate('blur_chats.remove_note'),
                        'confirm'  => translate('blur_chats.remove_confirm'),
                        'action'   => $ziel,
                        'fields'   => ['csrf' => $csrf, 'action' => 'remove_category', 'category' => $selected['id']],
                        'right'    => true,
                    ], null) ?>
                </span>
            <?php endif ?>
        </div>

        <p class="hint">
            <?= $e(translate('blur_chats.stage_hint', [
                'width'  => (string) $stage['width'],
                'height' => (string) $stage['height'],
            ])) ?>
        </p>

        <?php /* ---------- Screenshot ---------- */ ?>
        <?php if ($canEdit): ?>
            <div class="row bc-screen">
                <button class="btn btn-ghost btn-small" type="button" data-bc-file-trigger>
                    <?= $e(translate('blur_chats.screen_pick')) ?>
                </button>
                <input class="bc-file" type="file" accept="image/*" data-bc-file hidden>
                <span class="hint"><?= $e(translate('blur_chats.screen_paste')) ?></span>
                <span class="hint" data-bc-screen-status role="status"></span>
                <form class="right" method="post" action="<?= $e($ziel) ?>" data-bc-screen-remove <?= $screenUrl === '' ? 'hidden' : '' ?>>
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="screen_remove">
                    <input type="hidden" name="category" value="<?= $e($selected['id']) ?>">
                    <button class="btn btn-ghost btn-small" type="submit"><?= $e(translate('blur_chats.screen_remove')) ?></button>
                </form>
            </div>
        <?php endif ?>

        <?php /* ---------- Die Flaeche ---------- */ ?>
        <?php
        // Die Buehne in voller Groesse - verkleinert wird sie erst im
        // Browser. Die Bildadresse ist eine eigene (rawurlencode), sie
        // steht trotzdem escaped im Attribut.
        $buehnenStil = 'width: ' . $stage['width'] . 'px; height: ' . $stage['height'] . 'px;'
            . ($screenUrl !== '' ? " background-image: url('" . $screenUrl . "');" : '');
        ?>
        <div class="bc-editor" style="aspect-ratio: <?= $e((string) $stage['width']) ?> / <?= $e((string) $stage['height']) ?>;">
            <div class="bc-stage" data-bc-stage style="<?= $e($buehnenStil) ?>"></div>
        </div>
        <p class="hint bc-screen-note"><?= $e(translate('blur_chats.screen_note')) ?></p>

        <?php /* ---------- Die Zeilen ---------- */ ?>
        <form method="post" action="<?= $e($ziel) ?>" data-bc-form>
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category" value="<?= $e($selected['id']) ?>">
            <input type="hidden" name="category_name" value="<?= $e($selected['name']) ?>">

            <div class="bc-table">
                <table>
                    <thead>
                        <tr>
                            <th><?= $e(translate('blur_chats.col.label')) ?></th>
                            <th><?= $e(translate('blur_chats.col.x')) ?></th>
                            <th><?= $e(translate('blur_chats.col.y')) ?></th>
                            <th><?= $e(translate('blur_chats.col.w')) ?></th>
                            <th><?= $e(translate('blur_chats.col.h')) ?></th>
                            <th><?= $e(translate('blur_chats.col.color')) ?></th>
                            <th><?= $e(translate('blur_chats.col.opacity')) ?></th>
                            <th><?= $e(translate('blur_chats.col.feather')) ?></th>
                            <th><?= $e(translate('blur_chats.col.radius')) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-bc-rows>
                        <?php foreach ($regions as $i => $b): ?>
                            <?php $zeile($i, $b) ?>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>

            <p class="empty" data-bc-empty <?= $regions === [] ? '' : 'hidden' ?>><?= $e(translate('blur_chats.regions_empty')) ?></p>

            <?php if ($canEdit): ?>
                <?php
                // Die Vorlage fuer "+ Bereich": mittig, mit den Vorgaben.
                // Dieselbe Rechnung wie fuer einen Bereich ohne Lage.
                $neu = Regions::normalize([], $stage['width'], $stage['height']);
                ?>
                <template data-bc-template>
                    <?php $zeile(null, $neu) ?>
                </template>

                <div class="row bc-actions">
                    <button class="btn btn-ghost" type="button" data-bc-add>+ <?= $e(translate('blur_chats.region_add')) ?></button>
                    <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                    <span class="hint" data-bc-dirty hidden><?= $e(translate('blur_chats.unsaved')) ?></span>
                </div>
            <?php endif ?>
        </form>
    </div>
<?php endif ?>
