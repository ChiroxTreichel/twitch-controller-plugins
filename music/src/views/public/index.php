<?php
/**
 * Die oeffentliche Musikseite.
 *
 * Dieselben Teile wie im alten System, in derselben Reihenfolge: der
 * Wunschkasten oben, die Warteschlange darunter, die Regeln in einem
 * Kasten, den man aufklappt.
 *
 * Was fehlt und mit Absicht fehlt: die Merkliste ("Favoriten") als
 * eigener Reiter. Sie kommt, wenn der Rest steht - siehe README.
 *
 * @var callable $e
 * @var callable $url
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var array{ok: bool, reason: string, until: int} $may
 * @var bool $enabled
 * @var bool $bypass
 * @var bool $connected
 * @var list<string> $rules
 * @var bool $accepted
 * @var int $cooldown
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */
?>
<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if (!$connected): ?>
    <?php /*
        Nicht verbunden heisst: hier geht gerade gar nichts. Das steht
        oben und allein - im alten System sah der Zuschauer ein
        Formular, das jeden Wunsch mit einem Serverfehler beantwortete.
    */ ?>
    <div class="card">
        <h1><?= $e(translate('music.public.title')) ?></h1>
        <p class="note note-warn"><?= $e(translate('music.public.not_connected')) ?></p>
    </div>
<?php else: ?>

    <div class="card">
        <h1><?= $e(translate('music.public.title')) ?></h1>

        <?php if ($bypass): ?>
            <?php /*
                Wer die Grenzen ignorieren darf, soll das auch sehen -
                sonst wundert er sich, warum bei ihm etwas geht, was
                laut Seite zu ist.
            */ ?>
            <p class="hint badge-line">
                <span class="badge badge-ok"><?= $e(translate('music.public.bypass')) ?></span>
            </p>
        <?php elseif (!$enabled): ?>
            <p class="note note-warn"><?= $e(translate('music.public.closed')) ?></p>
        <?php endif ?>

        <?php if ($identity === null): ?>
            <p class="lead"><?= $e(translate('music.public.login_lead')) ?></p>
            <p>
                <a class="btn" href="<?= $e($url('/music/login')) ?>">
                    <?= $e(translate('music.public.login')) ?>
                </a>
            </p>

        <?php elseif (!$accepted): ?>
            <p class="lead"><?= $e(translate('music.public.rules_lead')) ?></p>

            <ul class="rules">
                <?php foreach ($rules as $regel): ?>
                    <li><?= $e($regel) ?></li>
                <?php endforeach ?>
            </ul>

            <form method="post" action="<?= $e($url('/music')) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="accept_rules">
                <button class="btn" type="submit"><?= $e(translate('music.public.accept')) ?></button>
            </form>

        <?php else: ?>
            <?php /*
                Das Feld steht auch dann da, wenn gerade nicht gewuenscht
                werden darf - nur abgeschaltet. Ein Formular, das
                verschwindet, sieht aus wie ein Fehler; eines, das
                stumpf ist, sagt "gleich wieder".
            */ ?>
            <form method="post" action="<?= $e($url('/music')) ?>" class="wish">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="wish">

                <label class="field">
                    <span class="hint"><?= $e(translate('music.public.link')) ?></span>
                    <input class="input" type="text" name="link"
                           placeholder="https://open.spotify.com/track/…"
                           autocomplete="off" inputmode="url"
                        <?= $may['ok'] ? '' : 'disabled' ?>>
                </label>

                <button class="btn" type="submit" <?= $may['ok'] ? '' : 'disabled' ?>>
                    <?= $e(translate('music.public.submit')) ?>
                </button>
            </form>

            <?php if (!$may['ok']): ?>
                <p class="hint" id="deny"
                   <?= $may['reason'] === 'cooldown' ? 'data-until="' . (int) $may['until'] . '"' : '' ?>>
                    <?= $e(\TwitchController\Plugin\Music\Texts::denied($may['reason'])) ?>
                </p>
            <?php else: ?>
                <p class="hint"><?= $e(translate('music.public.cooldown_hint', ['minutes' => (string) $cooldown])) ?></p>
            <?php endif ?>
        <?php endif ?>

        <details class="rules-box">
            <summary><?= $e(translate('music.public.rules_title')) ?></summary>
            <ul class="rules">
                <?php foreach ($rules as $regel): ?>
                    <li><?= $e($regel) ?></li>
                <?php endforeach ?>
            </ul>
        </details>
    </div>

    <?php /*
        Die Warteschlange holt music.js im Takt nach. Sie steht nicht
        im HTML, weil sie sich aendert, waehrend die Seite offen ist -
        im alten System war es dasselbe, nur mit einem Aufruf je Klick
        statt von selbst.
    */ ?>
    <?php /*
        Die Beschriftungen stehen am Kasten und nicht im Skript: dort
        waeren sie deutscher Text in einer JS-Datei, und bin/lang.php
        haette recht, wenn es sich beschwert.
    */ ?>
    <div class="card" id="queue"
         data-src="<?= $e($url('/music/queue')) ?>"
         data-label-current="<?= $e(translate('music.public.now')) ?>"
         data-label-recent="<?= $e(translate('music.public.recent')) ?>"
         data-label-next="<?= $e(translate('music.public.next')) ?>"
         data-label-wish="<?= $e(translate('music.public.wished_by')) ?>"
         data-label-empty="<?= $e(translate('music.public.queue_empty')) ?>">
        <h2><?= $e(translate('music.public.queue')) ?></h2>
        <p class="hint" data-empty><?= $e(translate('music.public.loading')) ?></p>

        <?php /*
            Drei Abschnitte in der Reihenfolge des alten Systems:
            was laeuft, was eben lief, was kommt. "Zuletzt gespielt"
            stand dort dazwischen - es beantwortet "wie hiess das eben
            nochmal?", und diese Frage kommt sonst im Chat.
        */ ?>
        <div data-current></div>
        <div data-recent></div>
        <div data-items></div>
    </div>
<?php endif ?>
