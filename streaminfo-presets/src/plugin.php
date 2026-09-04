<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Streaminfo - Vorlagen
 * ===================================================================
 *
 * Gespeicherte Stream-Titel zum Auswaehlen. Auf der Streaminfo-Seite
 * steht dann eine Liste ueber dem Titelfeld: im Stream sucht niemand
 * nach der richtigen Schreibweise von "FFXIV - FATEs".
 *
 * Das Plugin haengt sich in streaminfo.fields ein und liefert einen
 * Block HTML. Der Kern wird dafuer nicht angefasst, und Streaminfo
 * selbst auch nicht - es fragt nur, wer etwas beizutragen hat.
 *
 * Was die Auswahl NICHT tut: sie schickt nichts ab und speichert
 * nichts. Sie schreibt ihren Wert in das Titelfeld daneben, und von
 * dort geht er wie jeder getippte Titel hinaus. Ohne JavaScript ist sie
 * eine Liste, die nichts tut - die Seite bleibt bedienbar.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\StreaminfoPresets\Presets;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
// Nur eines: die Liste pflegen. Wer sie BENUTZEN darf, entscheidet
// Streaminfo mit Streaminfo.Title.Edit - eine eigene Erlaubnis dafuer
// waere ein zweites Schloss an derselben Tuer.
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['StreaminfoPresets'] = [
        'label'       => translate('si_presets.name'),
        'permissions' => [
            'StreaminfoPresets.Global.Edit' => translate('si_presets.perm.edit'),
        ],
    ];

    return $catalog;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['js'][] = $app->asset('/plugin/streaminfo-presets/assets/presets.js');
    $assets['js'][] = $app->asset('/plugin/streaminfo-presets/assets/rows.js');

    return $assets;
});

// Der Eintrag in der Plugin-Liste zeigt auf denselben Reiter. Zwei
// Wege zu einer Seite sind kein Widerspruch: in der Plugin-Liste
// sucht man nach dem Plugin, auf der Streaminfo-Seite nach der
// Arbeit.
$hooks->on('plugin.settings', static function (array $links): array {
    $links[Presets::SLUG] = [
        'label' => translate('si_presets.name'),
        'href'  => '/stream/info/presets',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Block auf der Streaminfo-Seite
// -------------------------------------------------------------------
$hooks->on('streaminfo.fields', static function (array $felder, array $kontext) use ($app, $plugin): array {
    // Die benutzbare Liste: eine leere Vorlage waere in der Auswahl ein
    // Eintrag, der den Titel loescht.
    $liste = Presets::usable($app);

    // Keine Vorlagen, keine Auswahl. Ein leeres Auswahlfeld waere ein
    // Bedienelement, das nichts anbietet - schlimmer als keines.
    if ($liste === []) {
        return $felder;
    }

    $felder[Presets::SLUG] = [
        // Ueber die Tags: zuerst waehlt man den Titel, dann schmueckt
        // man ihn aus.
        'order' => 10,
        'html'  => $app->view->from($plugin->directory . '/views')->render('field', [
            'presets' => $liste,
            // Steht der aktuelle Titel in der Liste? Dann ist die
            // Auswahl darauf gestellt, und man sieht auf einen Blick,
            // dass gerade eine Vorlage laeuft.
            'current' => (string) ($kontext['bare'] ?? ''),
            'canEdit' => (bool) ($kontext['canEdit'] ?? false),
        ], null),
    ];

    return $felder;
});

// -------------------------------------------------------------------
//  Die Liste pflegen
// -------------------------------------------------------------------
$zurueck = static function (string $pfad, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

// Ein Reiter auf der Streaminfo-Seite und keine eigene Seite: die
// Vorlagen pflegt man oefter, als "einmal im Monat" es vermuten laesst,
// und der Weg ueber Plugins > Einstellungen ist dann einer zu viel.
//
// Der Reiter heisst "presets", die Adresse bleibt also /stream/info/presets
// - dieselbe wie vorher. Wer sie sich gemerkt hat, landet weiter richtig.
$hooks->on('streaminfo.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('StreaminfoPresets.Global.Edit')) {
        return $tabs;
    }

    $tabs['presets'] = [
        'label' => translate('si_presets.tab'),
        'order' => 20,
        // Wird nur fuer den offenen Reiter aufgerufen.
        'render' => static fn (): string => $app->view
            ->from($plugin->directory . '/views')
            ->render('tab', [
                'presets'    => Presets::all($app),
                'usable'     => Presets::usable($app),
                'maxTitle'   => Presets::MAX_TITLE,
                'maxPresets' => Presets::MAX_PRESETS,
                'canEdit'    => permission('StreaminfoPresets.Global.Edit'),
                'csrf'       => $app->auth->csrfToken(),
            ], null),
    ];

    return $tabs;
});

$router->post('/stream/info/presets', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/stream/info/presets';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('StreaminfoPresets.Global.Edit')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    // Ueber ->post und nicht ueber ->input(): input() gibt immer eine
    // Zeichenkette zurueck, fuer ein Feld wie "presets[]" also den
    // Vorgabewert. is_array() darauf ist damit nie wahr - und das
    // Speichern schrieb eine leere Liste, ohne jede Fehlermeldung.
    $zeilen = $request->post['presets'] ?? [];
    $zeilen = is_array($zeilen) ? array_values($zeilen) : [];

    // Eine Zeile anhaengen oder entfernen, ohne JavaScript: der Knopf
    // schickt das Formular mit, die Liste kommt also vollstaendig an
    // und wird hier veraendert. So bleibt jede Zeile ein eigenes Feld,
    // das man anklicken und tippen kann - wie beim Loeschbot und bei
    // den Timern.
    if ($request->input('add') !== '') {
        $zeilen[] = '';
    }

    $weg = $request->input('remove');
    if ($weg !== '' && array_key_exists((int) $weg, $zeilen)) {
        unset($zeilen[(int) $weg]);
        $zeilen = array_values($zeilen);
    }

    Presets::save($app, array_map('strval', $zeilen));

    return $zurueck($ziel, ['notice' => translate('si_presets.saved')]);
}, ['auth' => true]);
