<?php
/**
 * Die Spendenseite: /tips
 *
 * EINE Seite statt zweier wie im alten System. Dort gab es erst eine
 * Startseite und dann /donate; hier steht das Formular gleich da,
 * sobald man angemeldet ist. Ein Zwischenschritt, der nur "weiter"
 * sagt, ist ein Zwischenschritt zu viel.
 *
 * Die Zielauswahl ist der Kern: der Spender bestimmt, worauf sein Geld
 * laeuft. Genau darin unterscheidet sich diese Seite von den Tip-Goals
 * fuer StreamElements und Streamlabs, wo eine Spende aus einer
 * Schnittstelle kommt und nichts von Zielen weiss.
 *
 * Als Radiogruppe und nicht als Karussell wie frueher: ein Karussell
 * versteckt die Auswahl hinter einer Bewegung und braucht JavaScript.
 * Hier liegt alles offen, und ohne JavaScript funktioniert es genauso.
 *
 * @var callable $e
 * @var callable $url
 * @var callable $view
 * @var string $brand
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var list<array{id: int, title: string, current: float, target: float, percent: float}> $goals
 * @var bool $canPay
 * @var float $minimum
 * @var float $vorgabe
 * @var string $csrf
 * @var list<string> $legal
 * @var string $notice
 * @var string $error
 */

$heading = '';
echo $view->render('public/_head', compact('brand', 'heading', 'identity'), null);
?>

<?php if ($notice !== ''): ?>
    <div class="tp-note tp-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="tp-note tp-error"><?= $e($error) ?></div>
<?php endif ?>

<h1><?= $e(translate('pp_tip.public.heading', ['name' => $brand])) ?></h1>
<p class="tp-lead"><?= $e(translate('pp_tip.public.lead')) ?></p>

<?php if ($identity === null): ?>
    <?php /*
        Nicht angemeldet. Der Satz sagt, WOZU die Anmeldung dient -
        "mit Twitch anmelden" allein liest sich wie ein Konto, das man
        hier anlegt, und das gibt es nicht.
    */ ?>
    <p class="tp-lead"><?= $e(translate('pp_tip.public.why_login')) ?></p>
    <a class="tp-button" href="<?= $e($url('/tips/login')) ?>">
        <?= $e(translate('pp_tip.public.login')) ?>
    </a>

<?php elseif (!$canPay): ?>
    <?php /*
        Angemeldet, aber PayPal ist nicht hinterlegt. Kein Formular,
        das am Ende nur eine Fehlermeldung ergibt.
    */ ?>
    <div class="tp-note tp-warn"><?= $e(translate('pp_tip.public.not_ready')) ?></div>

<?php else: ?>
    <form class="tp-form" method="post" action="<?= $e($url('/tips')) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php if ($goals !== []): ?>
            <fieldset class="tp-goals">
                <legend><?= $e(translate('pp_tip.public.pick_goal')) ?></legend>

                <?php foreach ($goals as $i => $ziel): ?>
                    <label class="tp-goal">
                        <input type="radio" name="goal" value="<?= (int) $ziel['id'] ?>"
                               <?= $i === 0 ? 'checked' : '' ?>>

                        <span class="tp-goal-body">
                            <span class="tp-goal-title">
                                <?= $e($ziel['title'] !== '' ? $ziel['title'] : translate('pp_tip.unnamed')) ?>
                            </span>

                            <span class="tp-goal-bar" aria-hidden="true">
                                <span style="width: <?= $e(number_format($ziel['percent'], 2, '.', '')) ?>%"></span>
                            </span>

                            <span class="tp-goal-figures">
                                <?= $e(number_format($ziel['current'], 2, ',', '.')) ?>&nbsp;€
                                <span class="tp-quiet">
                                    <?= $e(translate('pp_tip.public.of', [
                                        'target' => number_format($ziel['target'], 2, ',', '.') . ' €',
                                    ])) ?>
                                </span>
                            </span>
                        </span>
                    </label>
                <?php endforeach ?>

                <?php /*
                    "Einfach so" gehoert dazu. Wer nur etwas dalassen
                    will, soll nicht gezwungen sein, sich fuer ein Ziel
                    zu entscheiden - und der Satz sagt ehrlich, was
                    dann passiert.
                */ ?>
                <label class="tp-goal tp-goal-none">
                    <input type="radio" name="goal" value="none" <?= $goals === [] ? 'checked' : '' ?>>
                    <span class="tp-goal-body">
                        <span class="tp-goal-title"><?= $e(translate('pp_tip.public.no_goal')) ?></span>
                        <span class="tp-quiet"><?= $e(translate('pp_tip.public.no_goal_hint')) ?></span>
                    </span>
                </label>
            </fieldset>
        <?php endif ?>

        <div class="tp-row">
            <label class="tp-field">
                <span><?= $e(translate('pp_tip.public.amount')) ?></span>
                <input class="tp-input" type="number" name="amount"
                       step="0.01"
                       min="<?= $e(number_format($minimum, 2, '.', '')) ?>"
                       value="<?= $e(number_format($vorgabe, 2, '.', '')) ?>"
                       inputmode="decimal" required>
            </label>
        </div>

        <?php /*
            Die Gebuehr uebernehmen. Was hier steht, ist ein VORSCHLAG
            aus den Einstellungen - was PayPal wirklich abzieht, sagt
            erst die Abrechnung. Der Satz daneben verspricht darum
            nicht mehr, als er halten kann.
        */ ?>
        <label class="tp-check">
            <input type="checkbox" name="cover_fees" value="1">
            <span><?= $e(translate('pp_tip.public.cover_fees')) ?></span>
        </label>

        <label class="tp-check">
            <input type="checkbox" name="anonymous" value="1">
            <span><?= $e(translate('pp_tip.public.anonymous')) ?></span>
        </label>

        <label class="tp-field">
            <span><?= $e(translate('pp_tip.public.message')) ?></span>
            <textarea class="tp-input" name="message" rows="3" maxlength="280"></textarea>
        </label>

        <button class="tp-button" type="submit">
            <?= $e(translate('pp_tip.public.submit')) ?>
        </button>

        <p class="tp-quiet tp-small"><?= $e(translate('pp_tip.public.paypal_hint')) ?></p>
    </form>
<?php endif ?>

<?= $view->render('public/_foot', compact('legal'), null) ?>
