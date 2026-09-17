<?php

declare(strict_types=1);

/**
 * Die Timer-Seite: einer je Klappfeld, unten einer zum Anlegen.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $enabled
 * @var array{live: bool, started_at: int, title: string, game: string, checked_at: int} $stream
 * @var list<array{timer: array<string, mixed>, progress: array<string, mixed>}> $timers
 * @var string $csrf
 * @var array{interval_min: int, interval_max: int, message: int} $limits
 * @var string $notice
 * @var string $error
 */

$darfAendern = permission('Timers.Global.Edit');
$ziel = $url('/chat/timers');

/** Sekunden als "90s" oder "2h 15min" - kurz genug fuer unter den Balken. */
$dauer = static function (int $sekunden) use ($e): string {
    if ($sekunden < 60) {
        return $e($sekunden . 's');
    }

    $minuten = intdiv($sekunden, 60);
    if ($minuten < 60) {
        return $e($minuten . 'min');
    }

    $stunden = intdiv($minuten, 60);
    $rest = $minuten % 60;

    return $e($rest === 0 ? $stunden . 'h' : $stunden . 'h ' . $rest . 'min');
};
?>
<div class="head-row">
    <h1><?= $e(translate('timers.name')) ?></h1>

    <?php if (permission('Timers.Global.Toggle')): ?>
        <form method="post" action="<?= $e($url('/chat/timers/toggle')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <button class="switch<?= $enabled ? ' is-on' : '' ?>" type="submit"
                    title="<?= $e(translate('timers.toggle_hint')) ?>"
                    aria-label="<?= $e(translate('timers.toggle_hint')) ?>">
                <span class="switch-track"><span class="switch-knob"></span></span>
            </button>
        </form>
    <?php else: ?>
        <span class="badge <?= $enabled ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($enabled ? translate('timers.on') : translate('timers.off')) ?>
        </span>
    <?php endif ?>
</div>

<?php /*
    Der Stream-Zustand steht ganz oben, weil er die haeufigste Antwort
    auf "warum passiert nichts" ist: ohne Stream posten Timer nicht.
*/ ?>
<p class="hint">
    <strong><?= $e(translate('timers.stream')) ?></strong>
    <?php if ($stream['live']): ?>
        <?= $e(translate('timers.stream.live')) ?>
        <?php if ($stream['game'] !== ''): ?>
            &middot; <?= $e($stream['game']) ?>
        <?php endif ?>
    <?php else: ?>
        <?= $e(translate('timers.stream.offline')) ?>
    <?php endif ?>
</p>

<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('timers.all_off_hint')) ?></div>
<?php endif ?>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
    <?php foreach ($timers as $zeile): ?>
        <?php
        $timer = $zeile['timer'];
        $fortschritt = $zeile['progress'];
        $id = (string) $timer['id'];
        $formular = 'timer-' . $id;
        ?>
        <details class="case">
            <summary>
                <?= $e((string) $timer['title']) ?>
                <?php if (empty($timer['enabled'])): ?>
                    <span class="timer-off">&middot; <?= $e(translate('timers.inactive')) ?></span>
                <?php endif ?>
            </summary>

            <div class="case-body">
                <form id="<?= $e($formular) ?>" method="post" action="<?= $e($ziel) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $e($id) ?>">

                    <?php /*
                        Enter in einem Feld schickt das Formular ueber den
                        ERSTEN Absende-Knopf darin ab - und das waere
                        "Loeschen" bei der ersten Nachricht. Dieser hier
                        steht davor und traegt nichts bei: Enter speichert,
                        wie man es erwartet.
                    */ ?>
                    <button class="default-submit" type="submit" tabindex="-1" aria-hidden="true"></button>

                    <div class="row">
                        <label class="field grow">
                            <span class="hint"><?= $e(translate('timers.field.title')) ?></span>
                            <input class="input" type="text" name="title" maxlength="80"
                                   value="<?= $e((string) $timer['title']) ?>"
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                        </label>

                        <label class="field">
                            <span class="hint"><?= $e(translate('timers.field.interval')) ?></span>
                            <input class="input" type="number" name="interval_minutes"
                                   min="<?= $e((string) $limits['interval_min']) ?>"
                                   max="<?= $e((string) $limits['interval_max']) ?>" step="1"
                                   value="<?= $e((string) $timer['interval_minutes']) ?>"
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                        </label>

                        <label class="field">
                            <span class="hint"><?= $e(translate('timers.field.min_lines')) ?></span>
                            <input class="input" type="number" name="min_lines" min="0" step="1"
                                   value="<?= $e((string) $timer['min_lines']) ?>"
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                        </label>
                    </div>

                    <div class="row">
                        <label class="field grow">
                            <span class="hint"><?= $e(translate('timers.field.keywords')) ?></span>
                            <input class="input" type="text" name="title_keywords" maxlength="200"
                                   placeholder="<?= $e(translate('timers.field.keywords_hint')) ?>"
                                   value="<?= $e((string) $timer['title_keywords']) ?>"
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                        </label>

                        <label class="field grow">
                            <span class="hint"><?= $e(translate('timers.field.game')) ?></span>
                            <input class="input" type="text" name="game" maxlength="80"
                                   placeholder="<?= $e(translate('timers.field.game_hint')) ?>"
                                   value="<?= $e((string) $timer['game']) ?>"
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                        </label>
                    </div>

                    <?php /*
                        Eine Eingabe je Nachricht, wie im alten System -
                        kein Textfeld. Nur so gibt es ein "Loeschen" fuer
                        die einzelne Zeile.

                        Hinzufuegen und Loeschen macht timers.js im
                        Browser, wie im alten System. Die Knoepfe sind
                        trotzdem Absende-Knoepfe: ohne das Skript
                        uebernimmt der Server, und das Formular
                        funktioniert weiter.
                    */ ?>
                    <span class="hint"><?= $e(translate('timers.field.messages')) ?></span>

                    <div class="timer-message-list" data-message-list>
                        <?php $nachrichten = (array) $timer['messages']; ?>
                        <?php foreach ($nachrichten as $n => $nachricht): ?>
                            <div class="row timer-message-row" data-message-row>
                                <textarea class="input timer-message grow" name="messages[]" rows="2"
                                          maxlength="<?= $e((string) $limits['message']) ?>"
                                          <?= $darfAendern ? '' : 'disabled' ?>><?= $e((string) $nachricht) ?></textarea>

                                <?php /*
                                    Die letzte bleibt stehen: ein Timer ohne
                                    Nachricht kann nichts tun. Stumpf und nicht
                                    weg - ein Knopf, der verschwindet und
                                    wiederkommt, laesst die Zeile springen.
                                */ ?>
                                <?php if ($darfAendern): ?>
                                    <button class="btn btn-ghost btn-small" type="submit"
                                            name="remove_message" value="<?= $e((string) $n) ?>"
                                            data-remove-message
                                            <?= count($nachrichten) > 1 ? '' : 'disabled' ?>>
                                        <?= $e(translate('timers.delete')) ?>
                                    </button>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>

                        <?php if ($darfAendern): ?>
                            <?php /*
                                Woraus timers.js die naechste Zeile baut. Im
                                Formular und nicht im Skript: so stehen
                                Klassen und Grenzen an einer Stelle.
                            */ ?>
                            <template data-message-template>
                                <div class="row timer-message-row" data-message-row>
                                    <textarea class="input timer-message grow" name="messages[]" rows="2"
                                              maxlength="<?= $e((string) $limits['message']) ?>"></textarea>
                                    <button class="btn btn-ghost btn-small" type="submit"
                                            name="remove_message" value=""
                                            data-remove-message><?= $e(translate('timers.delete')) ?></button>
                                </div>
                            </template>
                        <?php endif ?>
                    </div>

                    <?php if ($darfAendern): ?>
                        <div class="row">
                            <button class="btn btn-ghost btn-small" type="submit"
                                    name="add_message" value="1" data-add-message>
                                <?= $e(translate('timers.add_message')) ?>
                            </button>
                        </div>
                    <?php endif ?>

                    <div class="row">
                        <label class="switch-field">
                            <input type="checkbox" name="enabled" value="1"
                                   <?= $timer['enabled'] ? 'checked' : '' ?>
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                            <span class="switch-track"><span class="switch-knob"></span></span>
                            <span><?= $e(translate('timers.field.active')) ?></span>
                        </label>

                        <label class="switch-field">
                            <input type="checkbox" name="allow_as_command" value="1"
                                   <?= $timer['allow_as_command'] ? 'checked' : '' ?>
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                            <span class="switch-track"><span class="switch-knob"></span></span>
                            <span>
                                <?= $e(translate('timers.field.as_command')) ?>
                                <?php $befehl = \TwitchController\Plugin\Timers\Timers::commandName((string) $timer['title']); ?>
                                <?php if ($befehl !== ''): ?>
                                    <code>!<?= $e($befehl) ?></code>
                                <?php else: ?>
                                    <span class="hint"><?= $e(translate('timers.field.as_command_impossible')) ?></span>
                                <?php endif ?>
                            </span>
                        </label>
                    </div>
                </form>

                <?php /*
                    Die zwei Bedingungen als Balken: erst wenn BEIDE voll
                    sind, ist der Timer dran. Ohne die Anzeige raet man,
                    warum er schweigt.
                */ ?>
                <div class="row timer-bars">
                    <div class="grow">
                        <span class="hint"><?= $e(translate('timers.progress.time')) ?></span>
                        <div class="timer-bar">
                            <div class="timer-bar-fill"
                                 style="width: <?= $e((string) round($fortschritt['time_ratio'] * 100)) ?>%"></div>
                        </div>
                        <span class="hint">
                            <?= $dauer((int) $fortschritt['seconds']) ?> /
                            <?= $dauer((int) $fortschritt['seconds_needed']) ?>
                        </span>
                    </div>

                    <div class="grow">
                        <span class="hint"><?= $e(translate('timers.progress.lines')) ?></span>
                        <div class="timer-bar">
                            <div class="timer-bar-fill"
                                 style="width: <?= $e((string) round($fortschritt['lines_ratio'] * 100)) ?>%"></div>
                        </div>
                        <span class="hint">
                            <?= $e((string) $fortschritt['lines']) ?> /
                            <?= $e((string) $fortschritt['lines_needed']) ?>
                        </span>
                    </div>
                </div>

                <?php if ($darfAendern): ?>
                    <div class="row">
                        <button class="btn" type="submit" form="<?= $e($formular) ?>">
                            <?= $e(translate('common.save')) ?>
                        </button>

                        <?= $view->render('_confirm', [
                            'label'    => translate('timers.delete'),
                            'question' => translate('timers.delete_question', ['title' => (string) $timer['title']]),
                            'confirm'  => translate('timers.delete'),
                            'action'   => $ziel,
                            'fields'   => ['csrf' => $csrf, 'action' => 'delete', 'id' => $id],
                            'danger'   => true,
                            'small'    => false,
                        ], null) ?>
                    </div>
                <?php endif ?>
            </div>
        </details>
    <?php endforeach ?>

    <?php if ($darfAendern): ?>
        <details class="case"<?= $timers === [] ? ' open' : '' ?>>
            <summary><?= $e(translate('timers.new')) ?></summary>

            <div class="case-body">
                <form method="post" action="<?= $e($ziel) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="create">

                    <?php /* Wie oben: Enter soll anlegen, nicht loeschen. */ ?>
                    <button class="default-submit" type="submit" tabindex="-1" aria-hidden="true"></button>

                    <div class="row">
                        <label class="field grow">
                            <span class="hint"><?= $e(translate('timers.field.title')) ?></span>
                            <input class="input" type="text" name="title" maxlength="80"
                                   placeholder="<?= $e(translate('timers.title_example')) ?>">
                        </label>

                        <label class="field">
                            <span class="hint"><?= $e(translate('timers.field.interval')) ?></span>
                            <input class="input" type="number" name="interval_minutes"
                                   min="<?= $e((string) $limits['interval_min']) ?>"
                                   max="<?= $e((string) $limits['interval_max']) ?>" step="1"
                                   value="30">
                        </label>

                        <label class="field">
                            <span class="hint"><?= $e(translate('timers.field.min_lines')) ?></span>
                            <input class="input" type="number" name="min_lines" min="0" step="1" value="0">
                        </label>
                    </div>

                    <?php /*
                        Dieselben Felder wie beim vorhandenen Timer. Sie
                        fehlten hier, und der Server nahm sie trotzdem
                        entgegen - man musste also anlegen, aufklappen und
                        gleich wieder speichern, um einzutragen, was man
                        schon wusste.
                    */ ?>
                    <div class="row">
                        <label class="field grow">
                            <span class="hint"><?= $e(translate('timers.field.keywords')) ?></span>
                            <input class="input" type="text" name="title_keywords" maxlength="200"
                                   placeholder="<?= $e(translate('timers.field.keywords_hint')) ?>">
                        </label>

                        <label class="field grow">
                            <span class="hint"><?= $e(translate('timers.field.game')) ?></span>
                            <input class="input" type="text" name="game" maxlength="80"
                                   placeholder="<?= $e(translate('timers.field.game_hint')) ?>">
                        </label>
                    </div>

                    <?php /*
                        Auch hier eine Liste und kein einzelnes Feld: wer
                        einen Timer anlegt, hat oft schon zwei Nachrichten
                        im Kopf, und im alten System konnte man sie gleich
                        beide eintragen. Vorher musste man anlegen,
                        aufklappen und nachtragen.
                    */ ?>
                    <span class="hint"><?= $e(translate('timers.field.messages')) ?></span>

                    <div class="timer-message-list" data-message-list>
                        <div class="row timer-message-row" data-message-row>
                            <textarea class="input timer-message grow" name="messages[]" rows="2"
                                      maxlength="<?= $e((string) $limits['message']) ?>"
                                      placeholder="<?= $e(translate('timers.messages_example')) ?>"></textarea>
                            <button class="btn btn-ghost btn-small" type="submit"
                                    name="remove_message" value="0"
                                    data-remove-message disabled>
                                <?= $e(translate('timers.delete')) ?>
                            </button>
                        </div>

                        <template data-message-template>
                            <div class="row timer-message-row" data-message-row>
                                <textarea class="input timer-message grow" name="messages[]" rows="2"
                                          maxlength="<?= $e((string) $limits['message']) ?>"></textarea>
                                <button class="btn btn-ghost btn-small" type="submit"
                                        name="remove_message" value=""
                                        data-remove-message><?= $e(translate('timers.delete')) ?></button>
                            </div>
                        </template>
                    </div>

                    <div class="row">
                        <button class="btn btn-ghost btn-small" type="submit"
                                name="add_message" value="1" data-add-message>
                            <?= $e(translate('timers.add_message')) ?>
                        </button>
                    </div>

                    <div class="row">
                        <label class="switch-field">
                            <input type="checkbox" name="enabled" value="1" checked>
                            <span class="switch-track"><span class="switch-knob"></span></span>
                            <span><?= $e(translate('timers.field.active')) ?></span>
                        </label>

                        <?php /*
                            Welcher Befehl daraus wird, steht erst fest,
                            wenn der Titel getippt ist - hier also der
                            Hinweis statt des Namens. Beim vorhandenen
                            Timer steht an derselben Stelle "!spende".
                        */ ?>
                        <label class="switch-field">
                            <input type="checkbox" name="allow_as_command" value="1">
                            <span class="switch-track"><span class="switch-knob"></span></span>
                            <span>
                                <?= $e(translate('timers.field.as_command')) ?>
                                <span class="hint"><?= $e(translate('timers.field.as_command_impossible')) ?></span>
                            </span>
                        </label>
                    </div>

                    <div class="row">
                        <button class="btn" type="submit"><?= $e(translate('timers.create')) ?></button>
                    </div>
                </form>
            </div>
        </details>
    <?php elseif ($timers === []): ?>
        <div class="empty"><?= $e(translate('timers.none')) ?></div>
    <?php endif ?>
</div>
