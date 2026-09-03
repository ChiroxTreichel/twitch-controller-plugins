<?php

declare(strict_types=1);

/**
 * Grundbefehle: einer je Klappfeld.
 *
 * !befehle hat keine Felder - dort steht nur, was der Befehl tut. Das
 * Klappfeld bleibt trotzdem, damit die Liste einheitlich aussieht und
 * man nachlesen kann, was es gibt.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, array{label: string, description: string, hint: string, fields: array<string, array{label: string, type: string, min?: int, max_length?: int}>}> $builtin
 * @var array<string, array<string, mixed>> $settings
 * @var string $csrf
 */

$darfAendern = permission('ChatCommands.Basic.Edit');
?>
<div class="card">
    <h2><?= $e(translate('chat_commands.tab.basic')) ?></h2>

    <?php foreach ($builtin as $name => $definition): ?>
        <details class="case">
            <summary>!<?= $e((string) $name) ?></summary>

            <div class="case-body">
                <p class="hint"><?= $e($definition['description']) ?></p>

                <?php if ($definition['fields'] === []): ?>
                    <?php /* Nichts einzustellen - siehe Kopf der Datei. */ ?>
                <?php else: ?>
                    <form method="post" action="<?= $e($url('/chat/commands/basic')) ?>">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="command" value="<?= $e((string) $name) ?>">

                        <?php foreach ($definition['fields'] as $key => $field): ?>
                            <label class="field">
                                <span class="hint"><?= $e($field['label']) ?></span>

                                <?php if (($field['type'] ?? 'text') === 'number'): ?>
                                    <input class="input" type="number" step="1"
                                           min="<?= $e((string) ($field['min'] ?? 0)) ?>"
                                           name="fields[<?= $e((string) $key) ?>]"
                                           value="<?= $e((string) ($settings[$name][$key] ?? 0)) ?>"
                                           <?= $darfAendern ? '' : 'disabled' ?>>
                                <?php else: ?>
                                    <input class="input" type="text"
                                           maxlength="<?= $e((string) ($field['max_length'] ?? 400)) ?>"
                                           name="fields[<?= $e((string) $key) ?>]"
                                           value="<?= $e((string) ($settings[$name][$key] ?? '')) ?>"
                                           <?= $darfAendern ? '' : 'disabled' ?>>
                                <?php endif ?>
                            </label>
                        <?php endforeach ?>

                        <?php if ($darfAendern): ?>
                            <button class="btn" type="submit"><?= $e(translate('common.save')) ?></button>
                        <?php endif ?>
                    </form>
                <?php endif ?>

                <?php if ($definition['hint'] !== ''): ?>
                    <p class="hint"><?= $e($definition['hint']) ?></p>
                <?php endif ?>
            </div>
        </details>
    <?php endforeach ?>
</div>
