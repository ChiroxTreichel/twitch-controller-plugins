<?php
/**
 * Anzeige > Musik: die Bannliste.
 *
 * Im alten System war das public/admin/ban.php - vier Kaesten
 * untereinander, darueber eine Suche. Untereinander war es eine lange
 * Rolle, in der man das Gesuchte erst suchen musste; hier ist jede Art
 * ein Reiter, wie bei den Einstellungen und bei den Alerts.
 *
 * Der Reiter IST dabei die Art: auf "Titel" sucht man Titel, auf
 * "Interpreten" Interpreten. Ein zweites Auswahlfeld daneben waere
 * eine zweite Stelle, an der dasselbe steht - und zwei Stellen fuer
 * eine Sache laufen auseinander.
 *
 * Die Suche steht in der ADRESSE und nicht in einem Formularergebnis:
 * so laesst sich ein Ergebnis neu laden, ohne dass der Browser fragt,
 * ob das Formular noch einmal abgeschickt werden soll.
 *
 * @var \TwitchController\Core\Http\View $view
 * @var callable $e
 * @var callable $url
 * @var bool $enabled
 * @var bool $connected
 * @var string $tab
 * @var list<array<string, mixed>> $entries
 * @var list<array<string, mixed>> $wishes
 * @var array<string, int> $counts
 * @var string $query
 * @var list<array<string, mixed>> $results
 * @var bool $canEdit
 * @var bool $canToggle
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Core\Support\Dates;
use TwitchController\Plugin\Music\Texts;

/** Die Adresse eines Reiters - die Suche bleibt dabei stehen. */
$reiterUrl = static function (string $art) use ($url, $query): string {
    return $url('/display/music') . '?' . http_build_query(array_filter([
        'tab' => $art,
        'q'   => $art === 'track' || $art === 'artist' ? $query : '',
    ]));
};
?>
<h1><?= $e(translate('music.name')) ?></h1>
<p class="lead"><?= $e(translate('music.lead')) ?></p>

<?php if ($notice !== ''): ?>
    <div class="note note-ok"><?= $e($notice) ?></div>
<?php endif ?>
<?php if ($error !== ''): ?>
    <div class="note note-error"><?= $e($error) ?></div>
<?php endif ?>

<?php if (!$connected): ?>
    <div class="note note-warn">
        <strong><?= $e(translate('music.not_connected')) ?></strong>
        <?php if ($canToggle): ?>
            <a href="<?= $e($url('/display/music/settings')) ?>"><?= $e(translate('music.to_settings')) ?></a>
        <?php endif ?>
    </div>
<?php endif ?>

<?php /*
    Die Zahl steht am Reiter. Ohne sie muesste man jeden aufmachen, um
    zu sehen, wo ueberhaupt etwas drinsteht.
*/ ?>
<div class="tabs">
    <?php foreach ([
        'track'  => translate('music.ban.tracks'),
        'artist' => translate('music.ban.artists'),
        'genre'  => translate('music.ban.genres'),
        'twitch' => translate('music.ban.viewers'),
    ] as $art => $beschriftung): ?>
        <a class="tab<?= $tab === $art ? ' is-active' : '' ?>" href="<?= $e($reiterUrl($art)) ?>">
            <?= $e($beschriftung) ?>
            <?php if (($counts[$art] ?? 0) > 0): ?>
                <span class="hint">(<?= (int) $counts[$art] ?>)</span>
            <?php endif ?>
        </a>
    <?php endforeach ?>

    <a class="tab<?= $tab === 'wishes' ? ' is-active' : '' ?>" href="<?= $e($reiterUrl('wishes')) ?>">
        <?= $e(translate('music.wishes')) ?>
    </a>
</div>

<?php /* ---------------------------------------------------------- */ ?>
<?php if ($tab === 'wishes'): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('music.wishes')) ?></h2>
        </div>

        <?php if ($wishes === []): ?>
            <p class="hint"><?= $e(translate('music.wishes_empty')) ?></p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($wishes as $wunsch): ?>
                    <tr>
                        <td>
                            <?= $e((string) $wunsch['track_name']) ?>
                            <br><span class="hint"><?= $e((string) $wunsch['artists']) ?></span>
                        </td>
                        <td class="actions"><?= $e((string) $wunsch['twitch_name']) ?></td>
                        <td class="actions hint"><?= $e(Dates::short((string) $wunsch['created_at'])) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>

<?php else: ?>

    <?php /* --- Suchen und sperren ------------------------------- */ ?>
    <?php if ($canEdit && $connected && in_array($tab, ['track', 'artist'], true)): ?>
        <div class="card">
            <div class="card-head">
                <h2><?= $e(translate('music.ban.search')) ?></h2>
            </div>

            <p class="hint"><?= $e(translate('music.ban.search_hint')) ?></p>

            <?php /*
                Ein GET-Formular: die Suche gehoert in die Adresse.
                Darum steht hier auch kein Formularmerkmal - es wird
                nichts geaendert.
            */ ?>
            <form method="get" action="<?= $e($url('/display/music')) ?>" class="row">
                <input type="hidden" name="tab" value="<?= $e($tab) ?>">
                <input class="input grow" type="search" name="q" value="<?= $e($query) ?>"
                       placeholder="<?= $e(translate('music.ban.search_placeholder')) ?>">
                <button class="btn btn-small" type="submit"><?= $e(translate('music.ban.search_go')) ?></button>
            </form>

            <?php if ($query !== '' && $results === []): ?>
                <p class="hint" style="margin-top:10px;"><?= $e(translate('music.ban.no_results')) ?></p>
            <?php endif ?>

            <?php foreach ($results as $treffer): ?>
                <?php
                $name = (string) ($treffer['name'] ?? '');
                $zusatz = $tab === 'track'
                    ? implode(', ', array_filter(array_map(
                        static fn (array $a): string => (string) ($a['name'] ?? ''),
                        (array) ($treffer['artists'] ?? [])
                    )))
                    : implode(', ', array_slice((array) ($treffer['genres'] ?? []), 0, 4));
                ?>
                <?php /*
                    Eine Zeile je Treffer, jede ein eigenes Formular:
                    der Knopf ist ein Absende-Knopf, und ein Formular um
                    alle Treffer herum haette nicht sagen koennen,
                    WELCHEN man gerade sperrt.
                */ ?>
                <form method="post" action="<?= $e($url('/display/music')) ?>" class="row"
                      style="border-top:1px solid var(--line);padding-top:10px;margin-top:10px;">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="action" value="ban">
                    <input type="hidden" name="kind_add" value="<?= $e($tab) ?>">
                    <input type="hidden" name="key" value="<?= $e((string) ($treffer['id'] ?? '')) ?>">
                    <input type="hidden" name="name" value="<?= $e($name) ?>">
                    <input type="hidden" name="detail" value="<?= $e($zusatz) ?>">
                    <input type="hidden" name="q" value="<?= $e($query) ?>">
                    <input type="hidden" name="tab" value="<?= $e($tab) ?>">

                    <span class="grow">
                        <strong><?= $e($name) ?></strong>
                        <?php if ($zusatz !== ''): ?>
                            <br><span class="hint"><?= $e($zusatz) ?></span>
                        <?php endif ?>
                    </span>

                    <button class="btn btn-ghost btn-small" type="submit">
                        <?= $e(translate('music.ban.add')) ?>
                    </button>
                </form>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php /* --- Die Liste ---------------------------------------- */ ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('music.ban.list')) ?></h2>
            <span class="badge"><?= count($entries) ?></span>
        </div>

        <?php if ($entries === []): ?>
            <p class="hint"><?= $e(translate('music.ban.empty')) ?></p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($entries as $eintrag): ?>
                    <tr>
                        <td>
                            <?= $e((string) $eintrag['name']) ?>
                            <?php if ((string) $eintrag['detail'] !== ''): ?>
                                <br><span class="hint"><?= $e((string) $eintrag['detail']) ?></span>
                            <?php endif ?>
                        </td>
                        <td class="actions hint">
                            <?= $e(Dates::short((string) $eintrag['created_at'])) ?>
                        </td>
                        <?php if ($canEdit): ?>
                            <td class="actions">
                                <form method="post" action="<?= $e($url('/display/music')) ?>">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="kind_add" value="<?= $e($tab) ?>">
                                    <input type="hidden" name="key" value="<?= $e((string) $eintrag['key']) ?>">
                                    <input type="hidden" name="tab" value="<?= $e($tab) ?>">
                                    <button class="btn btn-ghost btn-small" type="submit">
                                        <?= $e(translate('music.ban.remove')) ?>
                                    </button>
                                </form>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>

        <?php /*
            Ein Feld auf jedem Reiter, und zwar aus demselben Grund:
            man hat, was man sperren will, oft schon in der Hand.

            Bei Genres und Zuschauern ist das ein Name. Bei Titeln und
            Interpreten ein LINK - ihre Kennung tippt niemand ab, aber
            einen Link aus der Spotify-App hat man in zwei Sekunden,
            und genau den fuegen die Zuschauer auf /music den ganzen
            Tag ein. Den Namen holt der Server dann selbst.

            Die Suche darueber bleibt: wer den Link NICHT hat, sucht.
        */ ?>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= $e($url('/display/music')) ?>" class="row" style="margin-top:12px;">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="kind_add" value="<?= $e($tab) ?>">
                <input type="hidden" name="tab" value="<?= $e($tab) ?>">
                <input class="input grow" type="text" name="key" maxlength="200"
                       placeholder="<?= $e(Texts::banPlaceholder($tab)) ?>">
                <button class="btn btn-small" type="submit"><?= $e(translate('music.ban.add')) ?></button>
            </form>
        <?php endif ?>
    </div>
<?php endif ?>
