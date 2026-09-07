<?php
/**
 * Die oeffentliche Seite: /raidme
 *
 * Eine ganze Seite und kein Ausschnitt - hier gibt es keine
 * Navigation, keine Seitenleiste und keinen angemeldeten Benutzer. Wer
 * sie oeffnet, ist ein fremder Streamer, der ein einziges Anliegen hat:
 * auf die Liste.
 *
 * Darum eine Karte in der Mitte und darin genau ein Knopf. Alles
 * andere - Bild, Name, Zustand - ist Antwort auf die Frage "hat es
 * geklappt".
 *
 * Kein Stylesheet der Verwaltung: admin.css bringt eine Oberflaeche
 * mit, die hier nichts zu suchen hat. Eigene Datei, eigene Farben,
 * gleiche Handschrift.
 *
 * @var callable $e
 * @var callable $url
 * @var callable $asset
 * @var string $channel   Der eigene Kanal - fuer wen man sich anmeldet
 * @var array{login: string, display_name: string, user_id: string}|null $identity
 * @var bool $ownChannel
 * @var array{login: string, display_name: string, status: string}|null $request
 * @var bool $open
 * @var string $notice
 * @var string $error
 * @var string $csrf
 */
?>
<!doctype html>
<html lang="<?= $e($language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e(translate('raids_req.public_title')) ?></title>

    <?php /*
        noindex: die Seite ist oeffentlich, weil sie ohne Anmeldung
        erreichbar sein muss - nicht, weil sie in einer Suchmaschine
        stehen soll. Wer den Link hat, hat ihn bekommen.
    */ ?>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= $e($asset('/plugin/raids-requests/assets/raidme.css')) ?>">
</head>
<body>
<main class="rm-card">
    <?php if ($channel !== ''): ?>
        <div class="rm-brand"><?= $e($channel) ?></div>
    <?php endif ?>
    <div class="rm-sub"><?= $e(translate('raids_req.public_title')) ?></div>

    <?php if ($error !== ''): ?>
        <div class="rm-box rm-error"><?= $e($error) ?></div>
    <?php endif ?>
    <?php if ($notice !== ''): ?>
        <div class="rm-box rm-ok"><?= $e($notice) ?></div>
    <?php endif ?>

    <?php if ($identity === null): ?>
        <?php /*
            Nicht angemeldet. Der Satz sagt, WOZU die Anmeldung dient -
            "mit Twitch anmelden" allein liest sich wie ein Konto, das
            man hier anlegt, und das gibt es nicht.
        */ ?>
        <p class="rm-lead"><?= $e(translate('raids_req.public_intro')) ?></p>

        <?php if ($open): ?>
            <a class="rm-btn" href="<?= $e($url('/raidme/login')) ?>">
                <?= $e(translate('raids_req.public_login')) ?>
            </a>
        <?php else: ?>
            <div class="rm-box rm-warn"><?= $e(translate('raids_req.public_closed')) ?></div>
        <?php endif ?>

    <?php else: ?>
        <?php /*
            Kein Bild. Um es zu zeigen, muesste es gespeichert oder bei
            jedem Aufruf geholt werden - und wer diese Seite ansieht,
            weiss, wie er aussieht. Der Name genuegt fuer die einzige
            Frage, die er hat: bin ich als der Richtige angemeldet?
        */ ?>
        <div class="rm-name"><?= $e($identity['display_name']) ?></div>

        <?php if ($ownChannel): ?>
            <div class="rm-box rm-warn"><?= $e(translate('raids_req.error.self')) ?></div>

        <?php elseif ($request !== null && $request['status'] === 'accepted'): ?>
            <div class="rm-box rm-ok"><?= $e(translate('raids_req.public_accepted')) ?></div>

        <?php elseif ($request !== null && $request['status'] === 'pending'): ?>
            <div class="rm-box rm-ok"><?= $e(translate('raids_req.public_pending')) ?></div>

        <?php elseif (!$open): ?>
            <div class="rm-box rm-warn"><?= $e(translate('raids_req.public_closed')) ?></div>

        <?php else: ?>
            <?php if ($request !== null && $request['status'] === 'declined'): ?>
                <?php /*
                    Abgelehnt - und trotzdem ein Knopf. Eine Ablehnung
                    gilt fuer den Abend, an dem sie ausgesprochen wurde,
                    nicht fuer immer.
                */ ?>
                <div class="rm-box rm-warn"><?= $e(translate('raids_req.public_declined')) ?></div>
            <?php endif ?>

            <form method="post" action="<?= $e($url('/raidme')) ?>">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <button class="rm-btn" type="submit">
                    <?= $e($request === null
                        ? translate('raids_req.public_submit')
                        : translate('raids_req.public_again')) ?>
                </button>
            </form>
        <?php endif ?>

        <a class="rm-foot" href="<?= $e($url('/raidme/logout')) ?>">
            <?= $e(translate('raids_req.public_logout')) ?>
        </a>
    <?php endif ?>
</main>
</body>
</html>
