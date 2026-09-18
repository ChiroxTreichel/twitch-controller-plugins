<?php

declare(strict_types=1);

/**
 * Die Kanalpunkt-Seite: ein Kachelgitter, und jede Kachel oeffnet
 * ihren Dialog.
 *
 * Die Kachel zeigt, was man beim Ueberfliegen braucht - Farbe, Name,
 * Kosten, und ob sie gerade an ist. Alles andere steckt im Dialog:
 * der Aufbau von Twitch (Name, Beschreibung, Texteingabe, Kosten,
 * Farbe, Warteschlange, Abklingzeit und Begrenzungen) und darunter
 * die Bedingungen. Was dort das Symbol ist, fehlt hier - die
 * Schnittstelle kennt kein Feld dafuer.
 *
 * Der Dialog ist ein <details> mit der Klasse "confirm": damit erbt
 * er, was der Kern fuer Rueckfragen schon kann - Escape, Klick
 * daneben, immer nur einer offen. Und ohne JavaScript klappt er
 * trotzdem auf und zu.
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
 * Eine Zeile einer Aus-Liste.
 *
 * Auch die Vorlage im <template> geht hier durch - so gibt es die
 * Zeile nur einmal, und eine per JavaScript angehaengte sieht aus wie
 * eine vom Server gelieferte.
 */
$ausZeile = static function (string $feld, string $wert, bool $offen) use ($e): void {
    ?>
    <div class="cp-entry" data-off-row>
        <input class="input" type="text" name="<?= $e($feld) ?>[]" maxlength="200"
               value="<?= $e($wert) ?>" <?= $offen ? '' : 'disabled' ?>>
        <button class="btn btn-ghost btn-small cp-entry-off" type="button" data-remove-off
                title="<?= $e(translate('channel_points.remove_entry')) ?>"
                aria-label="<?= $e(translate('channel_points.remove_entry')) ?>">&times;</button>
    </div>
    <?php
};

/**
 * Eine ganze Aus-Liste samt Vorlage und "+"-Knopf.
 *
 * Am Ende steht immer eine leere Zeile. Das ist der Rueckfall fuer
 * den Fall, dass das Skript fehlt: dann laesst sich wenigstens eine
 * weitere Ausnahme je Speichern eintragen. Mit Skript haengt "+" so
 * viele an, wie man mag.
 */
$ausListe = static function (
    string $feld,
    array $werte,
    string $beschriftung,
    string $knopf,
    bool $offen
) use ($e, $ausZeile): void {
    ?>
    <div class="field cp-list" data-off-list>
        <span class="hint"><?= $e($beschriftung) ?></span>

        <?php foreach ($werte as $wert): ?>
            <?php $ausZeile($feld, (string) $wert, $offen) ?>
        <?php endforeach ?>

        <?php $ausZeile($feld, '', $offen) ?>

        <?php /*
            Die neue Zeile kommt aus dieser Vorlage und wird nicht im
            Skript zusammengesetzt: sonst gaebe es die Zeile zweimal,
            hier und dort, und beide liefen mit der Zeit auseinander.
        */ ?>
        <template data-off-template><?php $ausZeile($feld, '', $offen) ?></template>

        <?php if ($offen): ?>
            <button class="btn btn-ghost btn-small cp-add" type="button" data-add-off>
                + <?= $e($knopf) ?>
            </button>
        <?php endif ?>
    </div>
    <?php
};

/**
 * Der ganze Inhalt eines Dialogs: die Felder von Twitch, dann die
 * Bedingungen.
 *
 * $offen sagt, ob die Twitch-Felder bedienbar sind. Bei einer fremden
 * Belohnung sind sie es nicht: Twitch nimmt die Aenderung ohnehin
 * nicht an, und ein Feld, das sich tippen laesst und nichts bewirkt,
 * ist schlimmer als ein graues.
 */
$formular = static function (
    array $b,
    bool $offen,
    bool $darfAendern
) use ($e, $einheiten, $limits, $ausListe): void {
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
    </div>

    <div class="row">
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

    <?php /*
        Ab hier gehoert nichts mehr Twitch. Die beiden Bloecke sind
        deshalb farbig abgesetzt - gruen schaltet ein, rot schaltet
        aus. Wer im Dialog scrollt, soll nicht erst die Ueberschrift
        lesen muessen, um zu wissen, wo er gerade ist.
    */ ?>
    <div class="cp-cond cp-cond-on">
        <h4 class="cp-cond-head"><?= $e(translate('channel_points.cond.on')) ?></h4>
        <p class="hint"><?= $e(translate('channel_points.cond.on_hint')) ?></p>

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
    </div>

    <div class="cp-cond cp-cond-off">
        <h4 class="cp-cond-head"><?= $e(translate('channel_points.cond.off')) ?></h4>
        <p class="hint"><?= $e(translate('channel_points.cond.off_hint')) ?></p>

        <?php
        $ausListe(
            'title_off',
            is_array($b['title_off']) ? $b['title_off'] : [],
            translate('channel_points.field.title_off'),
            translate('channel_points.add_title_off'),
            $darfAendern
        );

        $ausListe(
            'game_off',
            is_array($b['game_off']) ? $b['game_off'] : [],
            translate('channel_points.field.game_off'),
            translate('channel_points.add_game_off'),
            $darfAendern
        );
        ?>
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
    <div class="card cp-bar">
        <div class="cp-bar-text">
            <strong><?= $e(translate('channel_points.import')) ?></strong>
            <p class="hint"><?= $e(translate('channel_points.import_hint')) ?></p>
        </div>

        <div class="cp-bar-buttons">
            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="import">
                <button class="btn" type="submit"><?= $e(translate('channel_points.import_button')) ?></button>
            </form>

            <?php /*
                Anlegen im selben Dialog wie Aendern - derselbe Block
                Felder, damit beim Anlegen nichts fehlt, was beim
                Aendern da ist.
            */ ?>
            <details class="confirm cp-dialog confirm-right">
                <summary class="btn">+ <?= $e(translate('channel_points.new_button')) ?></summary>

                <div class="confirm-panel">
                    <h3 class="cp-dialog-head"><?= $e(translate('channel_points.new')) ?></h3>
                    <p class="hint"><?= $e(translate('channel_points.new_hint')) ?></p>

                    <form method="post" action="<?= $e($ziel) ?>">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="id" value="">

                        <?php $formular([
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
                            'title_on'      => '',
                            'game_on'       => '',
                            'title_off'     => [],
                            'game_off'      => [],
                        ], true, true) ?>

                        <div class="row cp-dialog-actions">
                            <button class="btn" type="submit">
                                <?= $e(translate('channel_points.create_button')) ?>
                            </button>
                            <button class="btn btn-ghost" type="button" data-confirm-cancel>
                                <?= $e(translate('common.cancel')) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </div>
<?php endif ?>

<?php if ($rewards === []): ?>
    <div class="card">
        <p class="hint"><?= $e(translate('channel_points.empty')) ?></p>
    </div>
<?php endif ?>

<div class="cp-grid">
    <?php foreach ($rewards as $zeile): ?>
        <?php
        $b = $zeile['reward'];
        $id = (string) $b['id'];
        $eigen = !empty($b['manageable']);
        $nurHier = !$zeile['remote'];
        $farbe = $b['color'] === '' ? '#9146FF' : (string) $b['color'];
        ?>
        <article class="cp-tile<?= empty($b['is_enabled']) ? ' is-off' : '' ?>"
                 style="--cp-tile: <?= $e($farbe) ?>;">
            <?php /* Der Streifen traegt die Farbe der Belohnung - wie bei Twitch. */ ?>
            <div class="cp-tile-color" aria-hidden="true"></div>

            <h3 class="cp-tile-name"><?= $e((string) $b['title']) ?></h3>

            <p class="cp-tile-cost"><?= $e(number_format((int) $b['cost'], 0, ',', '.')) ?></p>

            <?php /*
                Der Grund haengt an der Plakette, die ihn erklaert -
                "fremd" und "nur hier" sagen kurz, was los ist, und
                der Satz dazu steht im title. Unter der Kachel waere
                er eine Zeile, die auf jeder zweiten Kachel dasselbe
                sagt.
            */ ?>
            <p class="cp-tile-badges">
                <?php if ($nurHier): ?>
                    <span class="badge badge-warn" title="<?= $e($zeile['why']) ?>">
                        <?= $e(translate('channel_points.badge.local')) ?>
                    </span>
                <?php elseif (!$eigen): ?>
                    <span class="badge badge-off" title="<?= $e($zeile['why']) ?>">
                        <?= $e(translate('channel_points.badge.foreign')) ?>
                    </span>
                <?php endif ?>

                <?php if ($zeile['auto']): ?>
                    <span class="badge badge-ok"><?= $e(translate('channel_points.badge.auto')) ?></span>
                <?php endif ?>

                <?php if (empty($b['is_enabled'])): ?>
                    <span class="badge badge-off"><?= $e(translate('channel_points.inactive')) ?></span>
                <?php endif ?>
            </p>

            <?php /*
                Bleibt nur die Zeile fuer die Faelle, die keine
                Plakette haben: eine eigene Belohnung, bei der die
                Bedingungen gerade greifen oder eben nicht.
            */ ?>
            <?php if ($eigen): ?>
                <p class="hint cp-tile-why"><?= $e($zeile['why']) ?></p>
            <?php else: ?>
                <p class="cp-tile-why"></p>
            <?php endif ?>

            <?php if ($darfAendern): ?>
                <div class="cp-tile-actions">
                    <details class="confirm cp-dialog">
                        <?php /*
                            Nur der Stift. Das Wort stand auf jeder
                            Kachel dasselbe und trug nichts bei - der
                            Name der Belohnung steht darueber.
                        */ ?>
                        <summary class="btn btn-small cp-pen"
                                 title="<?= $e(translate('channel_points.edit_button')) ?>"
                                 aria-label="<?= $e(translate('channel_points.edit_button')) ?>">&#9998;</summary>

                        <div class="confirm-panel">
                            <h3 class="cp-dialog-head"><?= $e((string) $b['title']) ?></h3>

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

                                <?php $formular($b, $eigen, true) ?>

                                <div class="row cp-dialog-actions">
                                    <button class="btn" type="submit">
                                        <?= $e(translate('common.save')) ?>
                                    </button>
                                    <button class="btn btn-ghost" type="button" data-confirm-cancel>
                                        <?= $e(translate('common.cancel')) ?>
                                    </button>
                                </div>
                            </form>

                            <?php /*
                                Hinter dem Speichern-Formular, nicht
                                darin: eine Rueckfrage bringt ihr
                                eigenes Formular mit, und eines im
                                anderen ist kein gueltiges HTML - der
                                Browser wirft das innere weg. Die
                                Rueckfrage schickte dann nichts ab.

                                (Und der Pruefer liest hier mit: ein
                                ausgeschriebenes Form-Tag im Kommentar
                                zaehlt er als geoeffnetes Formular.)
                            */ ?>
                            <div class="cp-danger">
                                <?php if ($nurHier): ?>
                                    <form method="post" action="<?= $e($ziel) ?>">
                                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="action" value="push">
                                        <input type="hidden" name="id" value="<?= $e($id) ?>">
                                        <button class="btn btn-small" type="submit">
                                            <?= $e(translate('channel_points.push_button')) ?>
                                        </button>
                                    </form>
                                <?php elseif (!$eigen): ?>
                                    <?php /*
                                        Kein Loeschen durch uns: Twitch
                                        laesst das bei einer fremden
                                        Belohnung ohnehin nicht zu. Der
                                        Knopf sagt nur, dass es von
                                        Hand schon geschehen ist -
                                        danach legen wir sie neu an,
                                        diesmal als eigene.
                                    */ ?>
                                    <?= $view->render('_confirm', [
                                        'label'    => translate('channel_points.recreate_button'),
                                        'question' => translate('channel_points.recreate_question'),
                                        'note'     => translate('channel_points.recreate_note'),
                                        'confirm'  => translate('channel_points.recreate_confirm'),
                                        'action'   => $ziel,
                                        'fields'   => ['csrf' => $csrf, 'action' => 'recreate', 'id' => $id],
                                        'danger'   => false,
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
                        </div>
                    </details>

                </div>
            <?php endif ?>
        </article>
    <?php endforeach ?>
</div>
