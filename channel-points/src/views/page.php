<?php

declare(strict_types=1);

/**
 * Die Kanalpunkt-Seite: eine Belohnung je Klappfeld, unten eine zum
 * Anlegen.
 *
 * Der Aufbau folgt dem Dialog von Twitch - Name, Beschreibung,
 * Texteingabe, Kosten, Farbe, Warteschlange, Abklingzeit und
 * Begrenzungen. Was dort das Symbol ist, fehlt hier: die
 * Schnittstelle kennt kein Feld dafuer.
 *
 * Darunter, und das ist der eigentliche Grund fuer dieses Plugin, die
 * Bedingungen: bei welchem Titel und welcher Kategorie die Belohnung
 * an sein soll und bei welchen aus.
 *
 * @var callable $e
 * @var callable $url
 * @var \TwitchController\Core\View\View $view
 * @var bool $enabled
 * @var bool $allowed
 * @var bool $canEdit
 * @var bool $canToggle
 * @var array{live: bool, started_at: int, title: string, game: string, checked_at: int} $stream
 * @var list<array{reward: array<string, mixed>, remote: bool, auto: bool, want: bool|null, why: string}> $rewards
 *      why ist der fertige Satz, nicht sein Schluessel.
 * @var string $csrf
 * @var array{title: int, prompt: int, cooldown: int} $limits
 * @var string $notice
 * @var string $error
 */

$darfAendern = $canEdit;
$ziel = $url('/stream/points');

$einheiten = [
    'seconds' => translate('channel_points.unit.seconds'),
    'minutes' => translate('channel_points.unit.minutes'),
    'hours'   => translate('channel_points.unit.hours'),
    'days'    => translate('channel_points.unit.days'),
];

/**
 * Die Felder einer Belohnung.
 *
 * Einmal fuer jede vorhandene und einmal fuer die neue - derselbe
 * Block, damit beim Anlegen nichts fehlt, was beim Aendern da ist.
 *
 * $offen sagt, ob die Twitch-Felder bedienbar sind. Bei einer fremden
 * Belohnung sind sie es nicht: Twitch nimmt die Aenderung ohnehin
 * nicht an, und ein Feld, das sich tippen laesst und nichts bewirkt,
 * ist schlimmer als ein graues.
 */
$felder = static function (array $b, bool $offen) use ($e, $einheiten, $limits): void {
    ?>
    <div class="row">
        <label class="field grow">
            <span class="hint"><?= $e(translate('channel_points.field.title')) ?></span>
            <input class="input" type="text" name="title" maxlength="<?= (int) $limits['title'] ?>"
                   value="<?= $e((string) $b['title']) ?>" <?= $offen ? '' : 'disabled' ?>>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('channel_points.field.cost')) ?></span>
            <input class="input" type="number" name="cost" min="1" step="1"
                   value="<?= (int) $b['cost'] ?>" <?= $offen ? '' : 'disabled' ?>>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('channel_points.field.color')) ?></span>
            <input class="input cp-color" type="color" name="color"
                   value="<?= $e($b['color'] === '' ? '#9146FF' : (string) $b['color']) ?>"
                   <?= $offen ? '' : 'disabled' ?>>
        </label>
    </div>

    <label class="field">
        <span class="hint"><?= $e(translate('channel_points.field.prompt')) ?></span>
        <textarea class="input" name="prompt" rows="2" maxlength="<?= (int) $limits['prompt'] ?>"
                  <?= $offen ? '' : 'disabled' ?>><?= $e((string) $b['prompt']) ?></textarea>
    </label>

    <label class="switch-field">
        <input type="checkbox" name="user_input" value="1"
               <?= !empty($b['user_input']) ? 'checked' : '' ?> <?= $offen ? '' : 'disabled' ?>>
        <span class="switch-track"><span class="switch-knob"></span></span>
        <span><?= $e(translate('channel_points.field.user_input')) ?></span>
    </label>

    <label class="switch-field">
        <input type="checkbox" name="skip_queue" value="1"
               <?= !empty($b['skip_queue']) ? 'checked' : '' ?> <?= $offen ? '' : 'disabled' ?>>
        <span class="switch-track"><span class="switch-knob"></span></span>
        <span><?= $e(translate('channel_points.field.skip_queue')) ?></span>
    </label>

    <label class="switch-field">
        <input type="checkbox" name="is_enabled" value="1"
               <?= !empty($b['is_enabled']) ? 'checked' : '' ?> <?= $offen ? '' : 'disabled' ?>>
        <span class="switch-track"><span class="switch-knob"></span></span>
        <span><?= $e(translate('channel_points.field.is_enabled')) ?></span>
    </label>

    <?php /*
        Der Sammelschalter aus dem Dialog. Steht er aus, gelten die
        drei Zahlen darunter nicht - stehen bleiben sie trotzdem,
        damit man sie beim Wiedereinschalten nicht neu tippt.
    */ ?>
    <label class="switch-field">
        <input type="checkbox" name="limits" value="1"
               <?= !empty($b['limits']) ? 'checked' : '' ?> <?= $offen ? '' : 'disabled' ?>>
        <span class="switch-track"><span class="switch-knob"></span></span>
        <span><?= $e(translate('channel_points.field.limits')) ?></span>
    </label>

    <div class="row">
        <label class="field">
            <span class="hint"><?= $e(translate('channel_points.field.cooldown')) ?></span>
            <input class="input" type="number" name="cooldown" min="0" step="1"
                   value="<?= (int) $b['cooldown'] ?>" <?= $offen ? '' : 'disabled' ?>>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('channel_points.field.cooldown_unit')) ?></span>
            <select class="input" name="cooldown_unit" <?= $offen ? '' : 'disabled' ?>>
                <?php foreach ($einheiten as $wert => $beschriftung): ?>
                    <option value="<?= $e($wert) ?>"
                        <?= (string) $b['cooldown_unit'] === $wert ? 'selected' : '' ?>>
                        <?= $e($beschriftung) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('channel_points.field.per_stream')) ?></span>
            <input class="input" type="number" name="per_stream" min="0" step="1"
                   value="<?= (int) $b['per_stream'] ?>" <?= $offen ? '' : 'disabled' ?>>
        </label>

        <label class="field">
            <span class="hint"><?= $e(translate('channel_points.field.per_user')) ?></span>
            <input class="input" type="number" name="per_user" min="0" step="1"
                   value="<?= (int) $b['per_user'] ?>" <?= $offen ? '' : 'disabled' ?>>
        </label>
    </div>

    <p class="hint"><?= $e(translate('channel_points.limits_hint')) ?></p>
    <?php
};

/**
 * Die vier Bedingungsfelder.
 *
 * Die bleiben auch bei einer fremden Belohnung bedienbar - sie
 * gehoeren uns, nicht Twitch. Nur schalten laesst sich damit dann
 * nichts, und genau das steht darueber.
 */
$bedingungen = static function (array $b, bool $darfAendern) use ($e): void {
    ?>
    <h3 class="cp-sub"><?= $e(translate('channel_points.conditions')) ?></h3>
    <p class="hint"><?= $e(translate('channel_points.conditions_hint')) ?></p>

    <div class="row">
        <label class="field grow">
            <span class="hint"><?= $e(translate('channel_points.field.title_on')) ?></span>
            <input class="input" type="text" name="title_on" maxlength="200"
                   placeholder="<?= $e(translate('channel_points.placeholder.title')) ?>"
                   value="<?= $e((string) $b['title_on']) ?>" <?= $darfAendern ? '' : 'disabled' ?>>
        </label>

        <label class="field grow">
            <span class="hint"><?= $e(translate('channel_points.field.game_on')) ?></span>
            <input class="input" type="text" name="game_on" maxlength="200"
                   placeholder="<?= $e(translate('channel_points.placeholder.game')) ?>"
                   value="<?= $e((string) $b['game_on']) ?>" <?= $darfAendern ? '' : 'disabled' ?>>
        </label>
    </div>

    <div class="row">
        <label class="field grow">
            <span class="hint"><?= $e(translate('channel_points.field.title_off')) ?></span>
            <input class="input" type="text" name="title_off" maxlength="200"
                   placeholder="<?= $e(translate('channel_points.placeholder.title')) ?>"
                   value="<?= $e((string) $b['title_off']) ?>" <?= $darfAendern ? '' : 'disabled' ?>>
        </label>

        <label class="field grow">
            <span class="hint"><?= $e(translate('channel_points.field.game_off')) ?></span>
            <input class="input" type="text" name="game_off" maxlength="200"
                   placeholder="<?= $e(translate('channel_points.placeholder.game')) ?>"
                   value="<?= $e((string) $b['game_off']) ?>" <?= $darfAendern ? '' : 'disabled' ?>>
        </label>
    </div>
    <?php
};
?>
<div class="head-row">
    <h1><?= $e(translate('channel_points.name')) ?></h1>

    <?php if ($canToggle): ?>
        <form method="post" action="<?= $e($url('/stream/points/toggle')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <button class="switch<?= $enabled ? ' is-on' : '' ?>" type="submit"
                    title="<?= $e(translate('channel_points.toggle_hint')) ?>"
                    aria-label="<?= $e(translate('channel_points.toggle_hint')) ?>">
                <span class="switch-track"><span class="switch-knob"></span></span>
            </button>
        </form>
    <?php else: ?>
        <span class="badge <?= $enabled ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($enabled ? translate('channel_points.on') : translate('channel_points.off')) ?>
        </span>
    <?php endif ?>
</div>

<?php /*
    Der Stream-Zustand steht oben, weil er die haeufigste Antwort auf
    "warum schaltet nichts" ist: ohne Stream entscheidet das Plugin
    bewusst gar nichts.
*/ ?>
<p class="hint">
    <strong><?= $e(translate('channel_points.stream')) ?></strong>
    <?php if ($stream['live']): ?>
        <?= $e(translate('channel_points.stream.live')) ?>
        <?php if ($stream['game'] !== ''): ?>
            &middot; <?= $e($stream['game']) ?>
        <?php endif ?>
        <?php if ($stream['title'] !== ''): ?>
            &middot; <?= $e($stream['title']) ?>
        <?php endif ?>
    <?php else: ?>
        <?= $e(translate('channel_points.stream.offline')) ?>
    <?php endif ?>
</p>

<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('channel_points.all_off_hint')) ?></div>
<?php endif ?>

<?php if (!$allowed): ?>
    <div class="note note-error"><?= $e(translate('channel_points.error.scope_missing')) ?></div>
<?php endif ?>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if ($darfAendern): ?>
    <div class="card cp-load">
        <div>
            <strong><?= $e(translate('channel_points.import')) ?></strong>
            <p class="hint"><?= $e(translate('channel_points.import_hint')) ?></p>
        </div>

        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="import">
            <button class="btn" type="submit"><?= $e(translate('channel_points.import_button')) ?></button>
        </form>
    </div>
<?php endif ?>

<div class="card">
    <?php if ($rewards === []): ?>
        <p class="hint"><?= $e(translate('channel_points.empty')) ?></p>
    <?php endif ?>

    <?php foreach ($rewards as $zeile): ?>
        <?php
        $b = $zeile['reward'];
        $id = (string) $b['id'];
        $eigen = !empty($b['manageable']);
        $nurHier = !$zeile['remote'];
        ?>
        <details class="case">
            <summary>
                <?= $e((string) $b['title']) ?>
                <span class="cp-cost"><?= $e(number_format((int) $b['cost'], 0, ',', '.')) ?></span>

                <?php if ($nurHier): ?>
                    <span class="badge badge-warn"><?= $e(translate('channel_points.badge.local')) ?></span>
                <?php elseif (!$eigen): ?>
                    <span class="badge badge-off"><?= $e(translate('channel_points.badge.foreign')) ?></span>
                <?php endif ?>

                <?php if ($zeile['auto']): ?>
                    <span class="badge badge-ok"><?= $e(translate('channel_points.badge.auto')) ?></span>
                <?php endif ?>

                <?php if (empty($b['is_enabled'])): ?>
                    <span class="cp-off">&middot; <?= $e(translate('channel_points.inactive')) ?></span>
                <?php endif ?>
            </summary>

            <div class="case-body">
                <p class="hint"><?= $e($zeile['why']) ?></p>

                <?php if (!$eigen && !$nurHier): ?>
                    <div class="note note-warn"><?= $e(translate('channel_points.foreign_hint')) ?></div>
                <?php endif ?>

                <?php if ($nurHier): ?>
                    <div class="note note-warn"><?= $e(translate('channel_points.local_hint')) ?></div>
                <?php endif ?>

                <form method="post" action="<?= $e($ziel) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $e($id) ?>">

                    <?php $felder($b, $darfAendern && $eigen) ?>
                    <?php $bedingungen($b, $darfAendern) ?>

                    <?php if ($darfAendern): ?>
                        <div class="row cp-actions">
                            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                        </div>
                    <?php endif ?>
                </form>

                <?php if ($darfAendern): ?>
                    <div class="row cp-actions">
                        <?php if ($nurHier): ?>
                            <form method="post" action="<?= $e($ziel) ?>">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="action" value="push">
                                <input type="hidden" name="id" value="<?= $e($id) ?>">
                                <button class="btn" type="submit">
                                    <?= $e(translate('channel_points.push_button')) ?>
                                </button>
                            </form>
                        <?php elseif (!$eigen): ?>
                            <?= $view->render('_confirm', [
                                'label'    => translate('channel_points.adopt_button'),
                                'question' => translate('channel_points.adopt_question'),
                                'note'     => translate('channel_points.adopt_note'),
                                'confirm'  => translate('channel_points.adopt_confirm'),
                                'action'   => $ziel,
                                'fields'   => ['csrf' => $csrf, 'action' => 'adopt', 'id' => $id],
                                'danger'   => true,
                            ], null) ?>
                        <?php endif ?>

                        <?= $view->render('_confirm', [
                            'label'    => translate('common.remove'),
                            'question' => $eigen && !$nurHier
                                ? translate('channel_points.delete_question')
                                : translate('channel_points.remove_question'),
                            'confirm'  => translate('channel_points.delete_confirm'),
                            'action'   => $ziel,
                            'fields'   => ['csrf' => $csrf, 'action' => 'delete', 'id' => $id],
                            'danger'   => true,
                            'right'    => true,
                        ], null) ?>
                    </div>
                <?php endif ?>
            </div>
        </details>
    <?php endforeach ?>
</div>

<?php if ($darfAendern): ?>
    <div class="card">
        <h2><?= $e(translate('channel_points.new')) ?></h2>
        <p class="hint"><?= $e(translate('channel_points.new_hint')) ?></p>

        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="id" value="">

            <?php $felder([
                'title'         => '',
                'prompt'        => '',
                'cost'          => 100,
                'user_input'    => false,
                'color'         => '',
                'skip_queue'    => false,
                'is_enabled'    => true,
                'limits'        => false,
                'cooldown'      => 0,
                'cooldown_unit' => 'minutes',
                'per_stream'    => 0,
                'per_user'      => 0,
            ], true) ?>

            <?php $bedingungen([
                'title_on'  => '',
                'game_on'   => '',
                'title_off' => '',
                'game_off'  => '',
            ], true) ?>

            <div class="row cp-actions">
                <button class="btn" type="submit"><?= $e(translate('channel_points.create_button')) ?></button>
            </div>
        </form>
    </div>
<?php endif ?>
