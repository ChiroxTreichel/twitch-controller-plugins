<?php
/**
 * Die Einstellungen: Webhook-Adresse und Nachrichtenvorlage.
 *
 * Unter Plugins > Einstellungen und nicht auf der Kanalseite: dort
 * arbeitet man, und eine Adresse, die man einmal eintraegt, hat dort
 * nichts zu suchen.
 *
 * Die Adresse ist ein Geheimnis und wird nie wieder angezeigt, nur als
 * „gesetzt". Wer sie hat, kann in diesen Kanal schreiben, so oft er
 * will.
 *
 * @var \TwitchController\Core\Http\View $view
 * @var callable $e
 * @var callable $url
 * @var bool $hasWebhook
 * @var string $message
 * @var string $defaultText
 * @var int $maxMessage
 * @var list<string> $placeholders
 * @var bool $canEdit
 * @var bool $canTest
 * @var string $notice
 * @var string $error
 * @var string $csrf
 */

$ziel = $url('/networking/live/settings');
?>
<h1><?= $e(translate('live_notify.settings')) ?></h1>
<p class="lead"><?= $e(translate('live_notify.settings_lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<div class="card">
    <div class="card-head">
        <h2><?= $e(translate('live_notify.target.discord')) ?></h2>

        <?php if ($canTest): ?>
            <?php /*
                Ausprobiert wird mit derselben Vorlage und demselben
                Weg wie im Betrieb, nur mit erfundenen Werten - ein
                Test, der einen anderen Weg nimmt, ist schlimmer als
                keiner.

                Eigenes Formular: der Knopf soll nicht speichern, was
                gerade in den Feldern steht.
            */ ?>
            <form method="post" action="<?= $e($ziel) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="test">
                <button class="btn btn-ghost btn-small" type="submit"
                        <?= $hasWebhook ? '' : 'disabled' ?>>
                    <?= $e(translate('live_notify.test')) ?>
                </button>
            </form>
        <?php endif ?>
    </div>

    <?php /*
        Das Formular traegt eine id, und der Speichern-Knopf steht
        DRAUSSEN und gehoert ueber form="..." dazu.

        Grund: der Knopf "Adresse loeschen" ist eine Rueckfrage, und
        die bringt ihr eigenes Formular mit. Beide standen in einer
        Zeile, also stand ein Formular im anderen - ungueltiges HTML.
        Der Browser wirft das innere Start-Tag weg und haengt dessen
        Felder an das aeussere; damit gab es zwei "action"-Felder, und
        PHP nimmt das letzte. Ein Klick auf "Speichern" loeschte also
        die Adresse und speicherte die Nachricht nicht.

        So bleibt die Zeile, wie sie aussieht, und die beiden
        Formulare bleiben getrennt.
    */ ?>
    <form id="live-discord" method="post" action="<?= $e($ziel) ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">

        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('live_notify.webhook')) ?></span>
                <?php /*
                    Das Feld steht leer da, auch wenn eine Adresse
                    hinterlegt ist: ein Geheimnis wird nicht
                    zurueckgeschrieben. Leer heisst darum
                    "unveraendert" - zum Loeschen gibt es den eigenen
                    Knopf.
                */ ?>
                <input class="input" type="password" name="webhook_url"
                       autocomplete="off" spellcheck="false"
                       placeholder="<?= $e($hasWebhook
                           ? translate('live_notify.webhook_set')
                           : translate('live_notify.webhook_example')) ?>"
                       <?= $canEdit ? '' : 'disabled' ?>>
            </label>
        </div>

        <p class="hint"><?= $e(translate('live_notify.webhook_hint')) ?></p>

        <div class="row">
            <label class="field grow">
                <span class="hint"><?= $e(translate('live_notify.message')) ?></span>
                <textarea class="input" name="message" rows="3"
                          maxlength="<?= $e((string) $maxMessage) ?>"
                          placeholder="<?= $e($defaultText) ?>"
                          <?= $canEdit ? '' : 'disabled' ?>><?= $e($message) ?></textarea>
            </label>
        </div>

        <?php /*
            Die Platzhalter ausgeschrieben. Sie kommen aus
            LiveNotify::PLACEHOLDERS und nicht aus dieser Vorlage: was
            hier steht, muss auch ersetzt werden.
        */ ?>
        <p class="hint">
            <?= $e(translate('live_notify.placeholders')) ?>
            <?php foreach ($placeholders as $platzhalter): ?>
                <span class="mono">{{<?= $e($platzhalter) ?>}}</span>
            <?php endforeach ?>
        </p>

        <p class="hint"><?= $e(translate('live_notify.message_hint')) ?></p>

    </form>

    <?php if ($canEdit): ?>
        <div class="row">
            <button class="btn" type="submit" form="live-discord">
                <?= $e(translate('common.save')) ?>
            </button>

            <?php if ($hasWebhook): ?>
                <?= $view->render('_confirm', [
                    'label'    => translate('live_notify.forget_webhook'),
                    'question' => translate('live_notify.confirm_forget'),
                    'confirm'  => translate('live_notify.confirm_forget_yes'),
                    'action'   => $ziel,
                    'fields'   => ['csrf' => $csrf, 'action' => 'forget'],
                ], null) ?>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<div class="card">
    <div class="row">
        <a class="btn btn-ghost btn-small" href="<?= $e($url('/networking/live')) ?>">
            <?= $e(translate('live_notify.to_channels')) ?>
        </a>
    </div>
</div>
