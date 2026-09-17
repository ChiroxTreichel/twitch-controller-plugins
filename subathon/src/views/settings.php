<?php
/**
 * Die Einstellungen des Plugins - aus der Plugin-Liste erreichbar.
 *
 * Was den laufenden Subathon betrifft, steht im Reiter
 * "Einstellungen": Startzeit, Obergrenze, was ein Abo bringt. Hier
 * steht, was das Plugin betrifft.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $showManual
 * @var array<string, float> $prices
 * @var bool $canEdit
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */
?>
<h1><?= $e(translate('subathon.settings')) ?></h1>
<p class="lead"><?= $e(translate('subathon.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $e($url('/tools/subathon/settings')) ?>">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('subathon.tab.manual')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('subathon.show_manual_hint')) ?></p>

        <label class="switch-field">
            <input type="checkbox" name="show_manual" value="1"
                   <?= $showManual ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
            <span class="switch-track"><span class="switch-knob"></span></span>
            <span><?= $e(translate('subathon.field.show_manual')) ?></span>
        </label>

    </div>

    <?php /*
        Was ein Abo kostet. Gebraucht wird daraus nur das Verhaeltnis:
        Stufe 1 bringt die eingestellten Minuten, die anderen
        entsprechend ihrem Preis. Im Programm standen die drei Zahlen
        fest im Code - Twitch verlangt aber nicht ueberall dasselbe.
    */ ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('subathon.prices')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('subathon.prices_hint')) ?></p>

        <div class="row">
            <?php foreach ([
                '1000' => translate('subathon.price.tier1'),
                '2000' => translate('subathon.price.tier2'),
                '3000' => translate('subathon.price.tier3'),
            ] as $stufe => $beschriftung): ?>
                <label class="field">
                    <span class="hint"><?= $e($beschriftung) ?></span>
                    <input class="input" type="number" name="price_tier<?= $e($stufe) ?>"
                           min="0.01" max="9999" step="0.01"
                           value="<?= $e(number_format($prices[$stufe], 2, '.', '')) ?>"
                           <?= $canEdit ? '' : 'readonly' ?>>
                </label>
            <?php endforeach ?>
        </div>
    </div>

    <?php if ($canEdit): ?>
        <div class="row">
            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
        </div>
    <?php endif ?>
</form>

<p class="hint">
    <a href="<?= $e($url('/tools/subathon')) ?>"><?= $e(translate('subathon.to_page')) ?></a>
</p>
