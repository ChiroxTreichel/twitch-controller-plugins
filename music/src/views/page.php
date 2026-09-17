<?php
/**
 * Anzeige > Musik: die Bannliste.
 *
 * Im alten System war das public/admin/ban.php - vier Kaesten
 * nebeneinander, darueber eine Suche. Dasselbe hier, nur mit einer
 * Suche, die in der ADRESSE steht: so laesst sich ein Suchergebnis
 * neu laden, ohne dass der Browser fragt, ob das Formular noch einmal
 * abgeschickt werden soll.
 *
 * @var \TwitchController\Core\Http\View $view
 * @var callable $e
 * @var callable $url
 * @var bool $enabled
 * @var bool $connected
 * @var array<string, list<array<string, mixed>>> $bans
 * @var list<array<string, mixed>> $wishes
 * @var string $kind
 * @var string $query
 * @var list<array<string, mixed>> $results
 * @var bool $canEdit
 * @var bool $canToggle
 * @var string $csrf
 * @var string $notice
 * @var string $error
 */

use TwitchController\Core\Support\Dates;

/** Ein Kasten mit einer Art von Sperren. */
$liste = static function (string $art, string $ueberschrift, array $eintraege) use ($e, $url, $canEdit, $csrf): void {
    ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e($ueberschrift) ?></h2>
            <span class="badge"><?= count($eintraege) ?></span>
        </div>

        <?php if ($eintraege === []): ?>
            <p class="hint"><?= $e(translate('music.ban.empty')) ?></p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($eintraege as $eintrag): ?>
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
                                    <input type="hidden" name="kind_add" value="<?= $e($art) ?>">
                                    <input type="hidden" name="key" value="<?= $e((string) $eintrag['key']) ?>">
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
            Genres und Zuschauer haben keine Spotify-Kennung - sie
            werden eingetippt. Titel und Interpreten kommen aus der
            Suche darueber, denn ihre Kennung tippt niemand von Hand.
        */ ?>
        <?php if ($canEdit && in_array($art, ['genre', 'twitch'], true)): ?>
            <form method="post" action="<?= $e($url('/display/music')) ?>" class="row" style="margin-top:12px;">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="kind_add" value="<?= $e($art) ?>">
                <input class="input grow" type="text" name="key" maxlength="80"
                       placeholder="<?= $e(\TwitchController\Plugin\Music\Texts::banPlaceholder($art)) ?>">
                <button class="btn btn-small" type="submit"><?= $e(translate('music.ban.add')) ?></button>
            </form>
        <?php endif ?>
    </div>
    <?php
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

<?php /* ---------------------------------------------------------- */ ?>
<?php if ($canEdit && $connected): ?>
    <div class="card">
        <div class="card-head">
            <h2><?= $e(translate('music.ban.search')) ?></h2>
        </div>

        <p class="hint"><?= $e(translate('music.ban.search_hint')) ?></p>

        <?php /*
            Ein GET-Formular: die Suche gehoert in die Adresse, nicht
            in ein Formularergebnis. Darum steht hier auch kein
            Formularmerkmal - es wird nichts geaendert.
        */ ?>
        <form method="get" action="<?= $e($url('/display/music')) ?>" class="row">
            <select class="input" name="kind">
                <option value="track" <?= $kind === 'track' ? 'selected' : '' ?>>
                    <?= $e(translate('music.ban.kind_track')) ?>
                </option>
                <option value="artist" <?= $kind === 'artist' ? 'selected' : '' ?>>
                    <?= $e(translate('music.ban.kind_artist')) ?>
                </option>
            </select>
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
            $zusatz = $kind === 'track'
                ? implode(', ', array_filter(array_map(
                    static fn (array $a): string => (string) ($a['name'] ?? ''),
                    (array) ($treffer['artists'] ?? [])
                )))
                : implode(', ', array_slice((array) ($treffer['genres'] ?? []), 0, 4));
            ?>
            <?php /*
                Eine Zeile je Treffer, jede ein eigenes Formular: der Knopf
                ist ein Absende-Knopf und kein Kaestchen, und ein
                Formular um alle Treffer herum haette nicht sagen
                koennen, WELCHEN man gerade sperrt.
            */ ?>
            <form method="post" action="<?= $e($url('/display/music')) ?>" class="row"
                  style="border-top:1px solid var(--line);padding-top:10px;margin-top:10px;">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="kind_add" value="<?= $e($kind) ?>">
                <input type="hidden" name="key" value="<?= $e((string) ($treffer['id'] ?? '')) ?>">
                <input type="hidden" name="name" value="<?= $e($name) ?>">
                <input type="hidden" name="detail" value="<?= $e($zusatz) ?>">
                <input type="hidden" name="q" value="<?= $e($query) ?>">
                <input type="hidden" name="kind" value="<?= $e($kind) ?>">

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

<?php
$liste('track', translate('music.ban.tracks'), $bans['track'] ?? []);
$liste('artist', translate('music.ban.artists'), $bans['artist'] ?? []);
$liste('genre', translate('music.ban.genres'), $bans['genre'] ?? []);
$liste('twitch', translate('music.ban.viewers'), $bans['twitch'] ?? []);
?>

<?php /* ---------------------------------------------------------- */ ?>
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
