<?php
/**
 * Die Spendenseite: /tips
 *
 * Aussehen und Aufbau kommen aus dem alten spenden.talutah.de -
 * Startkarte, Karussell fuer die Ziele, Betragsknoepfe, Schalter,
 * Nettoanzeige. Wer die alte Seite kannte, findet dieselbe wieder.
 *
 * EINE Seite statt zweier wie damals: dort kam erst eine Startseite
 * und nach einem Klick /donate. Hier steht das Formular gleich da,
 * sobald man angemeldet ist - ein Zwischenschritt, der nur "weiter"
 * sagt, ist ein Zwischenschritt zu viel. Beide Ansichten von damals
 * gibt es trotzdem: die Startkarte fuer den, der noch nicht angemeldet
 * ist, das Formular fuer den, der es ist.
 *
 * Die Zielauswahl ist der Kern: der Spender bestimmt, worauf sein Geld
 * laeuft. Genau darin unterscheidet sich diese Seite von den Tip-Goals
 * fuer StreamElements und Streamlabs, wo eine Spende aus einer
 * Schnittstelle kommt und nichts von Zielen weiss.
 *
 * @var callable $e
 * @var callable $url
 * @var callable $asset
 * @var callable $view
 * @var string $brand
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var list<array{id: int, position: int, title: string, current: float, target: float, percent: float}> $goals
 * @var bool $canPay
 * @var float $minimum
 * @var float $vorgabe
 * @var list<float> $presets
 * @var float $feePercent
 * @var float $feeFixed
 * @var bool $terms
 * @var string $csrf
 * @var list<string> $legal
 * @var string $notice
 * @var string $error
 */

use TwitchController\Plugin\PaypalTipGoals\Legal;

/** Ein Betrag, wie ihn die Seite zeigt: 12,50 €. */
$euro = static fn (float $wert): string => number_format($wert, 2, ',', '.') . ' €';

/** Derselbe Betrag fuer ein Zahlenfeld: 12.50 - Punkt, keine Tausender. */
$zahl = static fn (float $wert): string => number_format($wert, 2, '.', '');

$heading = translate('pp_tip.public.heading', ['name' => $brand]);
echo $view->render('public/_head', compact('brand', 'heading', 'identity'), null);
?>

<?php if ($notice !== ''): ?>
    <div class="flash flash-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="flash flash-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if ($identity === null): ?>
    <?php /*
        Nicht angemeldet: die Startkarte von damals. Der Satz sagt,
        WOZU die Anmeldung dient - "mit Twitch anmelden" allein liest
        sich wie ein Konto, das man hier anlegt, und das gibt es nicht.
    */ ?>
    <section class="hero">
        <p class="lead"><?= $e(translate('pp_tip.public.lead')) ?></p>

        <?php $laufend = $goals[0] ?? null; ?>
        <?php if ($laufend !== null): ?>
            <section class="tip-goal">
                <h2><?= $e($laufend['title'] !== '' ? $laufend['title'] : translate('pp_tip.unnamed')) ?></h2>

                <div class="goal-bar">
                    <span class="goal-fill" style="width: <?= $e($zahl($laufend['percent'])) ?>%"></span>
                </div>

                <p class="goal-figures">
                    <strong><?= $e($euro($laufend['current'])) ?></strong>
                    <span><?= $e(translate('pp_tip.public.of', ['target' => $euro($laufend['target'])])) ?></span>
                </p>
            </section>
        <?php endif ?>

        <p class="muted small"><?= $e(translate('pp_tip.public.why_login')) ?></p>

        <a class="primary-button" href="<?= $e($url('/tips/login')) ?>">
            <?= $e(translate('pp_tip.public.login')) ?>
        </a>
    </section>

<?php elseif (!$canPay): ?>
    <?php /*
        Angemeldet, aber PayPal ist nicht hinterlegt. Kein Formular,
        das am Ende nur eine Fehlermeldung ergibt.
    */ ?>
    <div class="flash"><?= $e(translate('pp_tip.public.not_ready')) ?></div>

<?php else: ?>
    <?php
        // Die Karten des Karussells: erst die Ziele, dann "einfach so".
        // Die letzte Karte ist immer dabei - wer nur etwas dalassen
        // will, soll sich nicht fuer ein Ziel entscheiden muessen.
        $karten = [];

        foreach ($goals as $ziel) {
            $karten[] = [
                'value'   => (string) $ziel['id'],
                'title'   => $ziel['title'] !== '' ? $ziel['title'] : translate('pp_tip.unnamed'),
                'current' => $ziel['current'],
                'target'  => $ziel['target'],
                'percent' => $ziel['percent'],
                // Im Overlay laeuft das oberste Ziel - und nur das.
                'live'    => $ziel === ($goals[0] ?? null),
                'none'    => false,
            ];
        }

        $karten[] = [
            'value'   => 'none',
            'title'   => translate('pp_tip.public.no_goal'),
            'current' => 0.0,
            'target'  => 0.0,
            'percent' => 0.0,
            'live'    => false,
            'none'    => true,
        ];

        // Vorgewaehlt ist das laufende Ziel, sonst "einfach so".
        $start = $goals === [] ? count($karten) - 1 : 0;
    ?>

    <form class="donate-form" method="post" action="<?= $e($url('/tips')) ?>" novalidate>
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php if ($goals !== []): ?>
            <?php /*
                Das Karussell aus dem alten System: eine Karte zur
                Zeit, Pfeile und Punkte daneben.

                Die Wahl steckt aber in einem echten Radioknopf je
                Karte und nicht - wie damals - in einem versteckten
                Feld, das nur JavaScript beschreibt. Sichtbar ist er
                nicht, denn sichtbar ist immer genau eine Karte. Ohne
                JavaScript legt der noscript-Block im Kopf alle Karten
                untereinander und zeigt die Knoepfe: dann sieht es
                anders aus, funktioniert aber.
            */ ?>
            <fieldset class="goal-picker">
                <legend><?= $e(translate('pp_tip.public.pick_goal')) ?></legend>

                <div class="goal-carousel" data-start-index="<?= (int) $start ?>">
                    <button type="button" class="goal-carousel-nav goal-carousel-prev"
                            aria-label="<?= $e(translate('pp_tip.public.goal_prev')) ?>">&#10094;</button>

                    <div class="goal-carousel-viewport">
                        <div class="goal-carousel-track">
                            <?php foreach ($karten as $i => $karte): ?>
                                <label class="goal-slide<?= $i === $start ? ' is-current' : '' ?><?= $karte['none'] ? ' is-none' : '' ?>"
                                       data-index="<?= (int) $i ?>">
                                    <input type="radio" name="goal"
                                           value="<?= $e($karte['value']) ?>"
                                           <?= $i === $start ? 'checked' : '' ?>>

                                    <span class="goal-slide-body">
                                        <span class="goal-slide-head">
                                            <span class="goal-slide-title"><?= $e($karte['title']) ?></span>

                                            <?php if ($karte['live']): ?>
                                                <span class="goal-slide-active-tag">
                                                    <?= $e(translate('pp_tip.public.goal_live')) ?>
                                                </span>
                                            <?php endif ?>
                                        </span>

                                        <?php if ($karte['none']): ?>
                                            <span class="muted small"><?= $e(translate('pp_tip.public.no_goal_hint')) ?></span>
                                        <?php else: ?>
                                            <span class="goal-bar">
                                                <span class="goal-fill" style="width: <?= $e($zahl($karte['percent'])) ?>%"></span>
                                            </span>

                                            <span class="goal-figures">
                                                <strong><?= $e($euro($karte['current'])) ?></strong>
                                                <span><?= $e(translate('pp_tip.public.of', [
                                                    'target' => $euro($karte['target']),
                                                ])) ?></span>
                                            </span>
                                        <?php endif ?>
                                    </span>
                                </label>
                            <?php endforeach ?>
                        </div>
                    </div>

                    <button type="button" class="goal-carousel-nav goal-carousel-next"
                            aria-label="<?= $e(translate('pp_tip.public.goal_next')) ?>">&#10095;</button>
                </div>

                <div class="goal-carousel-dots">
                    <?php foreach ($karten as $i => $karte): ?>
                        <button type="button"
                                class="goal-carousel-dot<?= $i === $start ? ' is-current' : '' ?>"
                                data-index="<?= (int) $i ?>"
                                aria-label="<?= $e($karte['title']) ?>"></button>
                    <?php endforeach ?>
                </div>
            </fieldset>
        <?php endif ?>

        <fieldset>
            <legend><?= $e(translate('pp_tip.public.amount_legend')) ?></legend>

            <?php if ($presets !== []): ?>
                <div class="amount-presets">
                    <?php foreach ($presets as $betrag): ?>
                        <button type="button" class="preset-button" data-amount="<?= $e($zahl($betrag)) ?>">
                            <?= $e($euro($betrag)) ?>
                        </button>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <div class="amount-row">
                <label class="amount-label">
                    <span><?= $e(translate('pp_tip.public.amount')) ?></span>
                    <input type="number" name="amount" id="amount-input"
                           step="0.01"
                           min="<?= $e($zahl($minimum)) ?>"
                           max="10000"
                           value="<?= $e($zahl($vorgabe)) ?>"
                           inputmode="decimal" autocomplete="off" required>
                </label>

                <?php /*
                    Die Gebuehr uebernehmen. Was hier gerechnet wird,
                    ist ein VORSCHLAG aus den Einstellungen - was
                    PayPal wirklich abzieht, sagt erst die Abrechnung.
                */ ?>
                <div class="toggle-wrap cover-fees-row">
                    <span><?= $e(translate('pp_tip.public.cover_fees_short')) ?></span>
                    <label class="toggle-switch">
                        <input type="checkbox" name="cover_fees" id="cover-fees-toggle" value="1">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <p class="muted small"><?= $e(translate('pp_tip.public.minimum', ['min' => $euro($minimum)])) ?></p>

            <p class="net-warn" id="net-warn-block"><?= $e(translate('pp_tip.public.net_warn')) ?></p>

            <p class="net-info" id="net-info-block"
               data-fee-percent="<?= $e(number_format($feePercent, 4, '.', '')) ?>"
               data-fee-fixed="<?= $e($zahl($feeFixed)) ?>"
               data-label-net="<?= $e(translate('pp_tip.public.net_label', ['name' => $brand])) ?>"
               data-label-gross="<?= $e(translate('pp_tip.public.gross_label')) ?>"
               data-formula-net="<?= $e(translate('pp_tip.public.net_formula')) ?>"
               data-formula-gross="<?= $e(translate('pp_tip.public.gross_formula')) ?>">
                <span id="net-label"><?= $e(translate('pp_tip.public.net_label', ['name' => $brand])) ?></span>
                <strong id="net-amount">–</strong>
                <span class="net-formula" id="net-formula"></span>
            </p>
        </fieldset>

        <fieldset>
            <legend><?= $e(translate('pp_tip.public.message')) ?></legend>

            <label class="message-label">
                <textarea name="message" rows="3" maxlength="280"
                          placeholder="<?= $e(translate('pp_tip.public.message_placeholder')) ?>"></textarea>
            </label>

            <p class="muted small"><?= $e(translate('pp_tip.public.message_hint')) ?></p>
        </fieldset>

        <fieldset>
            <div class="toggle-wrap">
                <span><?= $e(translate('pp_tip.public.anonymous_short')) ?></span>
                <label class="toggle-switch">
                    <input type="checkbox" name="anonymous" value="1">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <?php /*
                Der Satz stand im alten System nicht da - der Schalter
                hiess nur "Anonym spenden". Anonym heisst hier aber
                wirklich anonym, und das ist eine Zusage, die man
                lesen koennen soll, bevor man sie in Anspruch nimmt.
            */ ?>
            <p class="muted small"><?= $e(translate('pp_tip.public.anonymous')) ?></p>
        </fieldset>

        <?php /*
            Das Haekchen gibt es nur, wenn AGB und Datenschutz auch
            wirklich geschrieben sind. Sonst stuende hier ein Link auf
            eine Seite, die es nicht gibt - und eine Zustimmung zu
            nichts ist keine.
        */ ?>
        <?php if ($terms): ?>
            <fieldset>
                <label class="checkbox-label">
                    <input type="checkbox" name="accept_terms" value="1" required>
                    <span><?= translate('pp_tip.public.terms', [
                        'terms' => '<a href="' . $e($url('/tips/agb')) . '" target="_blank" rel="noopener">'
                            . $e(Legal::title('agb')) . '</a>',
                        'privacy' => '<a href="' . $e($url('/tips/datenschutz')) . '" target="_blank" rel="noopener">'
                            . $e(Legal::title('datenschutz')) . '</a>',
                    ]) ?></span>
                </label>
            </fieldset>
        <?php endif ?>

        <button class="primary-button" type="submit"><?= $e(translate('pp_tip.public.submit')) ?></button>

        <p class="muted small"><?= $e(translate('pp_tip.public.paypal_hint')) ?></p>
    </form>

    <script src="<?= $e($asset('/plugin/paypal-tip-goals/assets/tips.js')) ?>" defer></script>
<?php endif ?>

<?= $view->render('public/_foot', compact('brand', 'legal'), null) ?>
