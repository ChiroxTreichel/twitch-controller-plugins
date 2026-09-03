<?php

declare(strict_types=1);

/**
 * Eigene Befehle: Name und Antworttext, je Befehl ein Klappfeld.
 *
 * Unten steht immer ein leeres Feld zum Anlegen - offen, weil das der
 * Grund ist, weshalb man auf diesen Reiter geht, wenn noch nichts da
 * ist.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, string> $commands Name ohne "!" => Antworttext
 * @var string $csrf
 * @var int $maxLength
 */

$darfAendern = permission('ChatCommands.Custom.Edit');
$ziel = $url('/chat/commands/custom');
?>
<div class="card">
    <h2><?= $e(translate('chat_commands.tab.custom')) ?></h2>

    <p class="hint placeholders">
        <?= $e(translate('chat_commands.placeholder_label')) ?>
        <code>{USER}</code>
    </p>

    <?php foreach ($commands as $name => $antwort): ?>
        <details class="case">
            <summary>!<?= $e((string) $name) ?></summary>

            <div class="case-body">
                <form method="post" action="<?= $e($ziel) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="previous" value="<?= $e((string) $name) ?>">

                    <div class="row">
                        <label class="field">
                            <span class="hint"><?= $e(translate('chat_commands.field.name')) ?></span>
                            <input class="input" type="text" name="command"
                                   value="<?= $e((string) $name) ?>"
                                   pattern="[A-Za-z0-9_-]+"
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                        </label>

                        <label class="field grow">
                            <span class="hint"><?= $e(translate('chat_commands.field.response')) ?></span>
                            <input class="input" type="text" name="response"
                                   maxlength="<?= $e((string) $maxLength) ?>"
                                   value="<?= $e($antwort) ?>"
                                   <?= $darfAendern ? '' : 'disabled' ?>>
                        </label>
                    </div>

                    <?php if ($darfAendern): ?>
                        <div class="row">
                            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                        </div>
                    <?php endif ?>
                </form>

                <?php if ($darfAendern): ?>
                    <?= $view->render('_confirm', [
                        'label'    => translate('chat_commands.delete'),
                        'question' => translate('chat_commands.delete_question', ['name' => (string) $name]),
                        'confirm'  => translate('chat_commands.delete'),
                        'action'   => $ziel,
                        'fields'   => [
                            'csrf'     => $csrf,
                            'action'   => 'delete',
                            'previous' => (string) $name,
                        ],
                        'danger'   => true,
                        'small'    => true,
                    ], null) ?>
                <?php endif ?>
            </div>
        </details>
    <?php endforeach ?>

    <?php if ($darfAendern): ?>
        <details class="case" open>
            <summary><?= $e(translate('chat_commands.new')) ?></summary>

            <div class="case-body">
                <form method="post" action="<?= $e($ziel) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="row">
                        <label class="field">
                            <span class="hint"><?= $e(translate('chat_commands.field.name')) ?></span>
                            <input class="input" type="text" name="command"
                                   placeholder="<?= $e(translate('chat_commands.name_example')) ?>"
                                   pattern="[A-Za-z0-9_-]+">
                        </label>

                        <label class="field grow">
                            <span class="hint"><?= $e(translate('chat_commands.field.response')) ?></span>
                            <input class="input" type="text" name="response"
                                   maxlength="<?= $e((string) $maxLength) ?>"
                                   placeholder="<?= $e(translate('chat_commands.response_example')) ?>">
                        </label>
                    </div>

                    <div class="row">
                        <button class="btn" type="submit"><?= $e(translate('chat_commands.create')) ?></button>
                    </div>
                </form>
            </div>
        </details>
    <?php elseif ($commands === []): ?>
        <div class="empty"><?= $e(translate('chat_commands.none')) ?></div>
    <?php endif ?>
</div>
