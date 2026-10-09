<?php
/**
 * Eine Umfrage fuer die Zuschauer.
 *
 * Aufbau wie im alten umfragen.talutah.de: Ueberschrift, Ende mit
 * Countdown, Beschreibung, eigener Vorschlag, die Eintraege zum
 * Ankreuzen mit der Leiste "x von n gewaehlt" - und nach dem Ende das
 * Ergebnis mit Balken.
 *
 * Neu: ohne Anmeldung sieht man die Frage und die Eintraege schon,
 * bevor man sich anmeldet. Frueher stand dort nur ein Knopf - und man
 * meldete sich an, ohne zu wissen, wofuer.
 *
 * Bei "einen waehlen" sind es Runde statt Kaestchen: dann kann man gar
 * nicht erst zu viele ankreuzen.
 *
 * @var callable $e
 * @var callable $url
 * @var array<string, mixed> $poll
 * @var bool $open
 * @var list<array{id: int, label: string, suggested_by: string}> $options
 * @var array{rows: list<array{label: string, votes: int, share: float}>, participants: int, votes: int}|null $results
 * @var list<int> $chosen
 * @var string $csrf
 * @var string $notice
 * @var string $error
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 */

$ziel = $url('/polls/' . $poll['id']);
$anzahl = (int) $poll['max_choices'];
$einer = $anzahl === 1;
?>
<h1><?= $e($poll['title']) ?></h1>
<div class="pp-sub"><?= $e(translate('polls.public.number', ['id' => (string) $poll['id']])) ?></div>

<div class="pp-timer">
    <?= $e(translate('polls.public.ends', ['end' => date('d.m.Y H:i', (int) $poll['ends_ts'])])) ?>
    —
    <span data-pp-countdown
          data-end="<?= $e((string) $poll['ends_ts']) ?>"
          data-now="<?= $e((string) time()) ?>"
          data-left="<?= $e(translate('polls.public.left')) ?>"
          data-expired="<?= $e(translate('polls.public.expired')) ?>"><?= $open ? '' : $e(translate('polls.public.expired')) ?></span>
</div>

<?php if ($notice !== ''): ?>
    <div class="pp-panel pp-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="pp-panel pp-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if ($poll['description'] !== ''): ?>
    <div class="pp-panel pp-desc"><?= nl2br($e($poll['description'])) ?></div>
<?php endif ?>

<?php if ($open && $identity === null): ?>
    <div class="pp-panel pp-login">
        <p><?= $e(translate('polls.public.login_hint')) ?></p>
        <a class="pp-button pp-twitch" href="<?= $e($url('/polls/' . $poll['id'] . '/login')) ?>"><?= $e(translate('polls.public.login')) ?></a>
    </div>

    <div class="pp-panel">
        <?php foreach ($options as $eintrag): ?>
            <div class="pp-option is-preview"><span><?= $e($eintrag['label']) ?></span></div>
        <?php endforeach ?>
    </div>

<?php elseif ($open): ?>
    <?php if ($poll['suggestions']): ?>
        <form method="post" action="<?= $e($ziel) ?>" class="pp-panel pp-suggest" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="suggest">
            <label for="pp-suggestion"><?= $e(translate('polls.public.suggest_label')) ?></label>
            <div class="pp-row">
                <input id="pp-suggestion" type="text" name="suggestion" maxlength="100"
                       placeholder="<?= $e(translate('polls.public.suggest_placeholder')) ?>" required>
                <button class="pp-button" type="submit"><?= $e(translate('polls.public.suggest')) ?></button>
            </div>
        </form>
    <?php endif ?>

    <form method="post" action="<?= $e($ziel) ?>" class="pp-panel" autocomplete="off" data-pp-vote
          data-required="<?= $e((string) $anzahl) ?>"
          data-picked="<?= $e(translate('polls.public.picked')) ?>"
          data-missing="<?= $e(translate('polls.public.missing_count')) ?>"
          data-ok="<?= $e(translate('polls.public.ok')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="vote">

        <div class="pp-status" data-pp-status>
            <span class="pp-status-left" data-pp-picked><?= $e(translate('polls.public.choose', ['count' => (string) $anzahl])) ?></span>
            <span class="pp-status-right" data-pp-hint></span>
        </div>

        <div class="pp-options">
            <?php foreach ($options as $eintrag): ?>
                <?php $gewaehlt = in_array($eintrag['id'], $chosen, true); ?>
                <label class="pp-option<?= $gewaehlt ? ' is-selected' : '' ?>">
                    <input type="<?= $einer ? 'radio' : 'checkbox' ?>" name="choice[]"
                           value="<?= $e((string) $eintrag['id']) ?>" <?= $gewaehlt ? 'checked' : '' ?>>
                    <span><?= $e($eintrag['label']) ?></span>
                </label>
            <?php endforeach ?>
        </div>

        <div class="pp-actions">
            <?php if ($chosen !== []): ?>
                <span class="pp-note"><?= $e(translate('polls.public.change_hint')) ?></span>
            <?php endif ?>
            <span class="pp-spacer"></span>
            <button class="pp-button" type="submit"><?= $e(translate('polls.public.vote')) ?></button>
        </div>
    </form>

<?php else: ?>
    <?php $stand = $results ?? ['rows' => [], 'participants' => 0, 'votes' => 0]; ?>
    <div class="pp-panel">
        <div class="pp-status">
            <span class="pp-status-left">
                <?= $e($stand['votes'] === 1 ? translate('polls.public.votes_one') : translate('polls.public.votes', ['count' => (string) $stand['votes']])) ?>
            </span>
            <span class="pp-status-right"><?= $e(translate('polls.public.closed')) ?></span>
        </div>

        <?php foreach ($stand['rows'] as $zeile): ?>
            <div class="pp-result">
                <strong><?= $e($zeile['label']) ?></strong>
                <span class="pp-badge"><?= $e((string) $zeile['votes']) ?></span>
                <span class="pp-badge"><?= $e(number_format($zeile['share'], 1, ',', '')) ?>%</span>
                <span class="pp-bar"><span style="width: <?= $e((string) $zeile['share']) ?>%"></span></span>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php if ($identity !== null): ?>
    <p class="pp-foot">
        <?= $e(translate('polls.public.signed_in', ['name' => $identity['display_name']])) ?>
        · <a href="<?= $e($url('/polls/' . $poll['id'] . '/logout')) ?>"><?= $e(translate('polls.public.logout')) ?></a>
    </p>
<?php endif ?>
