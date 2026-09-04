<?php

declare(strict_types=1);

/**
 * Löschbot: die Musterliste und darunter das Testfeld.
 *
 * @var callable $e
 * @var callable $url
 * @var bool $enabled
 * @var list<string> $words   alle Zeilen, leere eingeschlossen
 * @var list<string> $usable  nur die, mit denen geprueft wird
 * @var list<string> $invalid
 * @var string $probe
 * @var array{blocked: bool, pattern: string, normalized: string, invalid: list<string>}|null $result
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

$darfAendern = permission('DeleteBot.Global.Edit');
$darfTesten = permission('DeleteBot.Global.Test');
$ziel = $url('/chat/delete-bot');
?>
<div class="head-row">
    <h1><?= $e(translate('delete_bot.name')) ?></h1>

    <?php if (permission('DeleteBot.Global.Toggle')): ?>
        <form method="post" action="<?= $e($url('/chat/delete-bot/toggle')) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="toggle">
            <button class="switch<?= $enabled ? ' is-on' : '' ?>" type="submit"
                    title="<?= $e(translate('delete_bot.toggle_hint')) ?>"
                    aria-label="<?= $e(translate('delete_bot.toggle_hint')) ?>">
                <span class="switch-track"><span class="switch-knob"></span></span>
            </button>
        </form>
    <?php else: ?>
        <span class="badge <?= $enabled ? 'badge-ok' : 'badge-off' ?>">
            <?= $e($enabled ? translate('delete_bot.on') : translate('delete_bot.off')) ?>
        </span>
    <?php endif ?>
</div>

<p class="lead"><?= $e(translate('delete_bot.lead')) ?></p>

<?php if (!$enabled): ?>
    <div class="note note-warn"><?= $e(translate('delete_bot.all_off_hint')) ?></div>
<?php endif ?>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php /*
    Kaputte Muster stehen oben, nicht versteckt: das alte System hat
    den Fehler verschluckt, und ein Muster mit Tippfehler traf dann
    einfach nie - ohne dass es jemand merkte.
*/ ?>
<?php if ($invalid !== []): ?>
    <div class="note note-error">
        <?= $e(translate('delete_bot.invalid_hint')) ?>
        <?php foreach ($invalid as $eines): ?>
            <br><span class="mono"><?= $e($eines) ?></span>
        <?php endforeach ?>
    </div>
<?php endif ?>

<div class="card">
    <h2><?= $e(translate('delete_bot.list')) ?></h2>
    <p class="hint"><?= $e(translate('delete_bot.list_hint')) ?></p>

    <?php /*
        Eine Eingabe je Muster, wie im alten System - kein Textfeld.
        Nur so gibt es ein "Loeschen" fuer die einzelne Zeile, und man
        muss nicht zaehlen, in welcher Zeile das kaputte Muster steht.

        Hinzufuegen und Loeschen sind Absende-Knoepfe im selben
        Formular: die uebrigen Eingaben gehen dabei nicht verloren, und
        es braucht kein JavaScript.
    */ ?>
    <form method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">

        <div class="delete-bot-list">
            <?php foreach ($words as $i => $wort): ?>
                <div class="row delete-bot-row">
                    <input class="input delete-bot-word grow" type="text" spellcheck="false"
                           name="words[]" value="<?= $e($wort) ?>"
                           maxlength="200"
                           placeholder="<?= $e(translate('delete_bot.word_example')) ?>"
                           <?= $darfAendern ? '' : 'disabled' ?>>

                    <?php if ($wort !== '' && in_array($wort, $invalid, true)): ?>
                        <span class="badge badge-off" title="<?= $e(translate('delete_bot.invalid_row')) ?>">
                            <?= $e(translate('delete_bot.invalid_short')) ?>
                        </span>
                    <?php endif ?>

                    <?php if ($darfAendern): ?>
                        <button class="btn btn-ghost btn-small" type="submit"
                                name="remove" value="<?= $e((string) $i) ?>">
                            <?= $e(translate('delete_bot.remove_row')) ?>
                        </button>
                    <?php endif ?>
                </div>
            <?php endforeach ?>

            <?php if ($words === []): ?>
                <p class="hint"><?= $e(translate('delete_bot.no_words')) ?></p>
            <?php endif ?>
        </div>

        <div class="row">
            <?php if ($darfAendern): ?>
                <button class="btn btn-ghost btn-small" type="submit" name="add" value="1">
                    <?= $e(translate('delete_bot.add_row')) ?>
                </button>
                <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
            <?php endif ?>
            <span class="hint"><?= $e(translate('delete_bot.count', ['count' => (string) count($usable)])) ?></span>
        </div>
    </form>
</div>

<?php /*
    Das Testfeld. Es ruft dieselbe Methode auf wie der Betrieb - ein
    Testfeld, das etwas anderes prueft als der Bot tut, waere schlimmer
    als gar keines.
*/ ?>
<?php if ($darfTesten): ?>
    <div class="card">
        <h2><?= $e(translate('delete_bot.test')) ?></h2>
        <p class="hint"><?= $e(translate('delete_bot.test_hint')) ?></p>

        <form method="post" action="<?= $e($ziel) ?>">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="test">

            <div class="row">
                <label class="field grow">
                    <span class="hint"><?= $e(translate('delete_bot.field.probe')) ?></span>
                    <input class="input" type="text" name="probe" maxlength="500"
                           value="<?= $e($probe) ?>"
                           placeholder="<?= $e(translate('delete_bot.probe_example')) ?>">
                </label>
            </div>

            <div class="row">
                <button class="btn" type="submit"><?= $e(translate('delete_bot.check')) ?></button>
            </div>
        </form>

        <?php if ($result !== null): ?>
            <?php if ($result['blocked']): ?>
                <div class="note note-error">
                    <strong><?= $e(translate('delete_bot.result.blocked')) ?></strong><br>
                    <?= $e(translate('delete_bot.result.pattern')) ?>
                    <span class="mono"><?= $e($result['pattern']) ?></span>
                </div>
            <?php else: ?>
                <div class="note note-ok">
                    <strong><?= $e(translate('delete_bot.result.kept')) ?></strong>
                    <?php if ($words === []): ?>
                        <br><?= $e(translate('delete_bot.result.empty_list')) ?>
                    <?php endif ?>
                </div>
            <?php endif ?>

            <?php /*
                Der normalisierte Text steht mit dabei, weil er die
                haeufigste Ueberraschung erklaert: Akzente und Umlaute
                werden vorher abgeflacht, "schön" wird zu "schon".
            */ ?>
            <?php if ($result['normalized'] !== $probe): ?>
                <p class="hint">
                    <?= $e(translate('delete_bot.result.normalized')) ?>
                    <span class="mono"><?= $e($result['normalized']) ?></span>
                </p>
            <?php endif ?>

            <?php if ($result['invalid'] !== []): ?>
                <p class="hint">
                    <?= $e(translate('delete_bot.result.skipped', [
                        'count' => (string) count($result['invalid']),
                    ])) ?>
                </p>
            <?php endif ?>

            <?php if ($enabled): ?>
                <p class="hint"><?= $e(translate('delete_bot.result.owner_note')) ?></p>
            <?php else: ?>
                <p class="hint"><?= $e(translate('delete_bot.result.while_off')) ?></p>
            <?php endif ?>
        <?php endif ?>
    </div>
<?php endif ?>
