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
 * @var string $tab  'rewards' oder 'groups'
 * @var list<array<string, mixed>> $groups  alle Gruppen, alphabetisch
 * @var list<array{reward: array<string, mixed>, remote: bool, auto: bool, want: bool|null, why: string, groups: list<array<string, mixed>>}> $rewards
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
) use ($e, $einheiten, $limits, &$bedingungen): void {
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

    <?php $bedingungen($b, $darfAendern) ?>
    <?php
};

/**
 * Die beiden Bedingungsbloecke.
 *
 * Eigene Funktion, weil eine Gruppe dieselben vier Felder traegt -
 * nur ohne die Felder von Twitch davor. Zweimal geschrieben liefen
 * sie mit der Zeit auseinander.
 */
$bedingungen = static function (array $b, bool $darfAendern) use ($e, $ausListe): void {
    ?>
    <?php /*
        Hier gehoert nichts Twitch. Die beiden Bloecke sind deshalb
        farbig abgesetzt - gruen schaltet ein, rot schaltet aus. Wer
        im Dialog scrollt, soll nicht erst die Ueberschrift lesen
        muessen, um zu wissen, wo er gerade ist.
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
/**
 * Ein Suchfeld, das im Browser filtert.
 *
 * Es steht als hidden in der Vorlage: ohne das Skript taete es
 * nichts, und ein Feld, in das man tippt und bei dem nichts
 * geschieht, ist schlimmer als gar keines. channel-points.js nimmt
 * das Attribut weg, sobald es den Filter angehaengt hat.
 */
$filterfeld = static function (string $platzhalter) use ($e): void {
    ?>
    <input class="input cp-filter" type="search" data-filter hidden
           placeholder="<?= $e($platzhalter) ?>"
           aria-label="<?= $e($platzhalter) ?>">
    <?php
};

/**
 * Die Felder einer Gruppe: Name, Mitglieder, Bedingungen.
 *
 * Die Mitglieder sind Schalter und keine Mehrfachauswahl: eine
 * <select multiple> bedient sich mit der Maus nur mit gedrueckter
 * Steuerungstaste, und wer das nicht weiss, loescht mit dem zweiten
 * Klick seine erste Wahl.
 */
$gruppenFelder = static function (array $g) use ($e, $rewards, $darfAendern, &$bedingungen, $filterfeld): void {
    $mitglieder = array_map('strval', is_array($g['members'] ?? null) ? $g['members'] : []);
    ?>
    <label class="field">
        <span class="hint"><?= $e(translate('channel_points.group.field.name')) ?></span>
        <input class="input" type="text" name="name" maxlength="60"
               value="<?= $e((string) $g['name']) ?>" <?= $darfAendern ? '' : 'disabled' ?>>
    </label>

    <div class="field" data-filter-scope>
        <span class="hint"><?= $e(translate('channel_points.group.field.members')) ?></span>

        <?php if ($rewards === []): ?>
            <p class="hint"><?= $e(translate('channel_points.group.no_rewards')) ?></p>
        <?php else: ?>
            <?php $filterfeld(translate('channel_points.filter.members')) ?>
        <?php endif ?>

        <div class="cp-members">
            <?php foreach ($rewards as $zeile): ?>
                <?php $rid = (string) $zeile['reward']['id']; ?>
                <label class="switch-field" data-filter-item>
                    <input type="checkbox" name="members[]" value="<?= $e($rid) ?>"
                           <?= in_array($rid, $mitglieder, true) ? 'checked' : '' ?>
                           <?= $darfAendern ? '' : 'disabled' ?>>
                    <span class="switch-track"><span class="switch-knob"></span></span>
                    <span><?= $e((string) $zeile['reward']['title']) ?></span>
                </label>
            <?php endforeach ?>

            <?php /*
                Eine ausgeblendete Zeile bleibt angehakt - der Filter
                sucht, er waehlt nicht ab. Wer tippt, um etwas zu
                finden, will nicht nebenbei seine Auswahl verlieren.
            */ ?>
            <p class="hint" data-filter-empty hidden>
                <?= $e(translate('channel_points.filter.nothing')) ?>
            </p>
        </div>
    </div>

    <?php $bedingungen($g, $darfAendern) ?>
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

<div class="tabs">
    <a class="tab<?= $tab === 'rewards' ? ' is-active' : '' ?>"
       href="<?= $e($url('/stream/points')) ?>"><?= $e(translate('channel_points.name')) ?></a>
    <a class="tab<?= $tab === 'groups' ? ' is-active' : '' ?>"
       href="<?= $e($url('/stream/points/groups')) ?>"><?= $e(translate('channel_points.groups')) ?></a>
</div>

<?php if ($tab === 'rewards'): ?>

<div data-filter-scope>

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
            <details class="confirm cp-dialog">
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
<?php else: ?>
    <div class="cp-search">
        <?php $filterfeld(translate('channel_points.filter.rewards')) ?>
        <p class="hint" data-filter-empty hidden>
            <?= $e(translate('channel_points.filter.nothing')) ?>
        </p>
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
        <article class="cp-tile<?= empty($b['is_enabled']) ? ' is-off' : '' ?>" data-filter-item
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

                            <?php
                            /*
                             * Bei einer fremden Belohnung steht nur
                             * der Hinweis da - und darunter die zwei
                             * Knoepfe, die hier ueberhaupt etwas
                             * bewirken.
                             *
                             * Das Formular bleibt stehen, nur
                             * unsichtbar: seine Felder gehen weiter
                             * mit, wenn doch jemand abschickt, und
                             * beim Neuanlegen steht damit alles
                             * bereit. Ein weggelassenes Formular
                             * haette einen halben Datensatz
                             * geschickt.
                             */
                            $stumm = !$eigen && !$nurHier;
                            ?>

                            <?php if ($stumm): ?>
                                <div class="note note-warn"><?= $e(translate('channel_points.foreign_hint')) ?></div>
                            <?php endif ?>

                            <?php if ($nurHier): ?>
                                <div class="note note-warn"><?= $e(translate('channel_points.local_hint')) ?></div>
                            <?php endif ?>

                            <form class="cp-form<?= $stumm ? ' is-hidden' : '' ?>"
                                  method="post" action="<?= $e($ziel) ?>">
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
                                ], null) ?>
                            </div>
                        </div>
                    </details>

                </div>
            <?php endif ?>
        </article>
    <?php endforeach ?>
</div>

</div><?php /* data-filter-scope */ ?>

<?php else: ?>

<?php /* ================= Gruppen ================= */ ?>
<?php /*
    Eine Gruppe fasst Belohnungen zusammen und traegt dieselben vier
    Bedingungsfelder. Sie ersetzt die einzelnen nicht, sie kommt dazu -
    und wer AUS sagt, gewinnt.
*/ ?>
<div class="card cp-bar">
    <div class="cp-bar-text">
        <strong><?= $e(translate('channel_points.groups')) ?></strong>
        <p class="hint"><?= $e(translate('channel_points.groups_hint')) ?></p>
    </div>

    <?php if ($darfAendern): ?>
        <div class="cp-bar-buttons">
            <details class="confirm cp-dialog">
                <summary class="btn">+ <?= $e(translate('channel_points.group.new_button')) ?></summary>

                <div class="confirm-panel">
                    <h3 class="cp-dialog-head"><?= $e(translate('channel_points.group.new')) ?></h3>

                    <form method="post" action="<?= $e($ziel) ?>">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="action" value="group_create">
                        <input type="hidden" name="id" value="">

                        <?php $gruppenFelder([
                            'name'      => '',
                            'members'   => [],
                            'title_on'  => '',
                            'game_on'   => '',
                            'title_off' => [],
                            'game_off'  => [],
                        ]) ?>

                        <div class="row cp-dialog-actions">
                            <button class="btn" type="submit">
                                <?= $e(translate('channel_points.group.create_button')) ?>
                            </button>
                            <button class="btn btn-ghost" type="button" data-confirm-cancel>
                                <?= $e(translate('common.cancel')) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    <?php endif ?>
</div>

<?php if ($groups === []): ?>
    <div class="card">
        <p class="hint"><?= $e(translate('channel_points.group.empty')) ?></p>
    </div>
<?php endif ?>

<div class="cp-grid">
    <?php foreach ($groups as $gruppe): ?>
        <?php
        $gid = (string) $gruppe['id'];
        $anzahl = count($gruppe['members']);
        ?>
        <article class="cp-tile<?= empty($gruppe['enabled']) ? ' is-off' : '' ?>">
            <div class="cp-tile-color" aria-hidden="true"></div>

            <h3 class="cp-tile-name"><?= $e((string) $gruppe['name']) ?></h3>

            <p class="cp-tile-badges">
                <span class="badge"><?= $e(translate('channel_points.group.members', [
                    'count' => (string) $anzahl,
                ])) ?></span>

                <?php if (empty($gruppe['enabled'])): ?>
                    <span class="badge badge-off"
                          title="<?= $e(translate('channel_points.group.off_hint')) ?>">
                        <?= $e(translate('channel_points.inactive')) ?>
                    </span>
                <?php endif ?>
            </p>

            <?php /*
                Die Mitglieder stehen NICHT hier.
                
                Bei einundzwanzig Belohnungen wird aus der Kachel eine
                Wand aus Namen, und die Zahl daneben sagt dasselbe in
                zwei Worten. Wer wissen will, welche es sind, macht
                den Dialog auf - dort stehen sie als Schalter.

                Was bleibt, ist der eine Fall, in dem die Zahl nicht
                genuegt: keine Mitglieder heisst, die Gruppe tut
                nichts, und das sieht man ihr sonst nicht an.
            */ ?>
            <p class="hint cp-tile-why">
                <?php if ($anzahl === 0): ?>
                    <?= $e(translate('channel_points.group.no_members')) ?>
                <?php endif ?>
            </p>

            <?php if ($darfAendern): ?>
                <div class="cp-tile-actions">
                    <?php /*
                        Der Schalter sagt, ob die REGEL gilt - nicht,
                        ob die Belohnungen an sind. Aus heisst: die
                        Gruppe legt sich fuer heute schlafen, ihre
                        Mitglieder richten sich nach ihren eigenen
                        Bedingungen.
                    */ ?>
                    <form method="post" action="<?= $e($ziel) ?>">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="action" value="group_toggle">
                        <input type="hidden" name="id" value="<?= $e($gid) ?>">
                        <button class="switch<?= !empty($gruppe['enabled']) ? ' is-on' : '' ?>" type="submit"
                                title="<?= $e(translate('channel_points.group.toggle_hint')) ?>"
                                aria-label="<?= $e(translate('channel_points.group.toggle_hint')) ?>">
                            <span class="switch-track"><span class="switch-knob"></span></span>
                        </button>
                    </form>

                    <details class="confirm cp-dialog">
                        <summary class="btn btn-small cp-pen"
                                 title="<?= $e(translate('channel_points.edit_button')) ?>"
                                 aria-label="<?= $e(translate('channel_points.edit_button')) ?>">&#9998;</summary>

                        <div class="confirm-panel">
                            <h3 class="cp-dialog-head"><?= $e((string) $gruppe['name']) ?></h3>

                            <form method="post" action="<?= $e($ziel) ?>">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="action" value="group_save">
                                <input type="hidden" name="id" value="<?= $e($gid) ?>">
                                <input type="hidden" name="enabled"
                                       value="<?= !empty($gruppe['enabled']) ? '1' : '' ?>">

                                <?php $gruppenFelder($gruppe) ?>

                                <div class="row cp-dialog-actions">
                                    <button class="btn" type="submit">
                                        <?= $e(translate('common.save')) ?>
                                    </button>
                                    <button class="btn btn-ghost" type="button" data-confirm-cancel>
                                        <?= $e(translate('common.cancel')) ?>
                                    </button>
                                </div>
                            </form>

                            <div class="cp-danger">
                                <?= $view->render('_confirm', [
                                    'label'    => translate('common.remove'),
                                    'question' => translate('channel_points.group.delete_question'),
                                    'note'     => translate('channel_points.group.delete_note'),
                                    'confirm'  => translate('channel_points.delete_confirm'),
                                    'action'   => $ziel,
                                    'fields'   => ['csrf' => $csrf, 'action' => 'group_delete', 'id' => $gid],
                                    'danger'   => true,
                                ], null) ?>
                            </div>
                        </div>
                    </details>
                </div>
            <?php endif ?>
        </article>
    <?php endforeach ?>
</div>

<?php endif ?>
