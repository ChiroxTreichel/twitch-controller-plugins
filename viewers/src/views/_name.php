<?php
/**
 * Name einer Zeile: der Anzeigename, darunter der Anmeldename, wenn er
 * anders lautet (etwa bei japanischen Anzeigenamen) - gesucht wird
 * nach beiden, also soll man auch beide sehen.
 *
 * @var callable $e
 * @var array<string, mixed> $zeile
 */

$anzeige = (string) ($zeile['name'] ?? '');
$login = (string) ($zeile['login'] ?? '');
?>
<strong><?= $e($anzeige !== '' ? $anzeige : $login) ?></strong>
<?php if ($login !== '' && mb_strtolower($anzeige) !== mb_strtolower($login)): ?>
    <div class="viewers-muted"><?= $e($login) ?></div>
<?php endif ?>
