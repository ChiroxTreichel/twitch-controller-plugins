<?php

declare(strict_types=1);

/**
 * Das Aussehen: HTML und CSS des Ziel-Gerüsts.
 *
 * Erreichbar über Plugin → Einstellungen, nicht als Reiter in Goals —
 * es ist eine Einstellung dieses Plugins und gehört nicht zwischen
 * Follower- und Sub-Ziel.
 *
 * @var callable $e
 * @var callable $url
 * @var string $html
 * @var string $css
 * @var bool $custom
 * @var list<string> $missing
 * @var array<string, string> $required  Name => Klarname
 * @var array<string, string> $fills
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$darfAendern = permission('TwitchGoals.Global.Edit');
$ziel = $url('/display/goals/twitch/appearance');
?>
<div class="row">
    <a class="btn btn-ghost" href="<?= $e($url('/account/plugins')) ?>">
        <?= $e(translate('common.back')) ?>
    </a>
    <a class="btn btn-ghost" href="<?= $e($url('/display/goals/twitch')) ?>">
        <?= $e(translate('twitch_goals.to_values')) ?>
    </a>
</div>

<h1><?= $e(translate('twitch_goals.settings_title')) ?></h1>
<p class="lead"><?= $e(translate('twitch_goals.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /*
    Fehlende Pflichtelemente stehen oben, nicht versteckt: ein Ziel
    ohne sein Element zeigt im Overlay nichts an, und das faellt erst
    mitten im Stream auf.
*/ ?>
<?php if ($missing !== []): ?>
    <div class="note note-error">
        <strong><?= $e(translate('twitch_goals.missing_hint')) ?></strong>
        <?php foreach ($missing as $eines): ?>
            <br><span class="mono"><?= $e($eines) ?></span>
        <?php endforeach ?>
    </div>
<?php endif ?>

<div class="card">
    <h2><?= $e(translate('twitch_goals.required')) ?></h2>
    <p class="hint"><?= $e(translate('twitch_goals.required_hint')) ?></p>

    <table>
        <tbody>
            <?php foreach ($required as $name => $klarname): ?>
                <tr>
                    <td><span class="mono">data-bind="<?= $e((string) $name) ?>"</span></td>
                    <td><?= $e($klarname) ?></td>
                </tr>
            <?php endforeach ?>
            <?php foreach ($fills as $name => $klarname): ?>
                <tr>
                    <td><span class="mono">data-fill="<?= $e((string) $name) ?>"</span></td>
                    <td><?= $e($klarname) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <p class="hint"><?= $e(translate('twitch_goals.format_hint')) ?></p>
</div>

<div class="card">
    <h2><?= $e(translate('twitch_goals.appearance')) ?></h2>

    <?php if (!$custom): ?>
        <div class="note note-warn"><?= $e(translate('twitch_goals.is_default')) ?></div>
    <?php endif ?>

    <?php /*
        Der Knopf steht ausserhalb des Formulars und findet es ueber
        form="…". Anders geht es nicht: die Rueckfrage bringt ihr
        eigenes Formular mit, und ein Formular in einem Formular ist
        ungueltiges HTML.
    */ ?>
    <form id="goals-appearance" method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">

        <label class="field">
            <span class="hint"><?= $e(translate('twitch_goals.field.html')) ?></span>
            <textarea class="input goals-code" name="html" rows="16" spellcheck="false"
                      <?= $darfAendern ? '' : 'disabled' ?>><?= $e($html) ?></textarea>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('twitch_goals.field.css')) ?></span>
            <textarea class="input goals-code" name="css" rows="18" spellcheck="false"
                      <?= $darfAendern ? '' : 'disabled' ?>><?= $e($css) ?></textarea>
        </label>

        <p class="hint"><?= $e(translate('twitch_goals.no_script_hint')) ?></p>
    </form>

    <?php if ($darfAendern): ?>
        <div class="row">
            <button class="btn" type="submit" form="goals-appearance">
                <?= $e(translate('common.save')) ?>
            </button>

            <?= $view->render('_confirm', [
                'label'    => translate('twitch_goals.reset_label'),
                'question' => translate('twitch_goals.reset_question'),
                'confirm'  => translate('twitch_goals.reset_label'),
                'action'   => $ziel,
                'fields'   => ['csrf' => $csrf, 'action' => 'reset'],
                'danger'   => true,
                'small'    => false,
            ], null) ?>
        </div>
    <?php endif ?>
</div>
