<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Streaminfo - Tags
 * ===================================================================
 *
 * Eigene Tags festlegen, auf der Streaminfo-Seite einzeln anhaken, und
 * die angehakten stehen in eckigen Klammern vor dem Titel:
 *
 *     [x] VTuber        ->  [VTuber][German] Das ist mein Titel
 *     [x] German
 *     [ ] Langeweile
 *
 * Drei Einhaengepunkte von Streaminfo:
 *
 *   streaminfo.fields         die Haken ueber dem Titelfeld
 *   streaminfo.title_bare     beim Anzeigen die Vorsaetze abnehmen
 *   streaminfo.title_compose  beim Speichern die Vorsaetze anbauen
 *
 * Die letzten zwei muessen zueinander passen. Bliebe beim Anzeigen ein
 * Vorsatz stehen, wuerde ihn das Speichern ein zweites Mal davorsetzen -
 * und beim naechsten Mal wieder eins mehr.
 *
 * Welche Tags an sind, wird NICHT gespeichert: das steht im Titel bei
 * Twitch, und der ist die einzige Wahrheit. Ein zweiter Ort dafuer waere
 * sofort falsch, sobald jemand den Titel anderswo aendert.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\StreaminfoTags\Tags;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
// Nur eines: die Liste pflegen. Wer die Haken SETZEN darf, entscheidet
// Streaminfo mit Streaminfo.Title.Edit - die Haken aendern den Titel,
// also gilt dasselbe Recht wie fuer den Titel.
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['StreaminfoTags'] = [
        'label'       => translate('si_tags.name'),
        'permissions' => [
            'StreaminfoTags.Global.Edit' => translate('si_tags.perm.edit'),
        ],
    ];

    return $catalog;
});

$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/streaminfo-tags/assets/tags.css');
    $assets['js'][] = $app->asset('/plugin/streaminfo-tags/assets/tags.js');

    return $assets;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[Tags::SLUG] = [
        'label' => translate('si_tags.name'),
        'href'  => '/stream/info/tags',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Die Haken auf der Streaminfo-Seite
// -------------------------------------------------------------------
$hooks->on('streaminfo.fields', static function (array $felder, array $kontext) use ($app, $plugin): array {
    // Die benutzbare Liste: ein leerer Tag ergaebe den Vorsatz "[]".
    $liste = Tags::usable($app);

    // Keine Tags, keine Haken. Ein leerer Kasten waere eine Flaeche,
    // die nichts anbietet.
    if ($liste === []) {
        return $felder;
    }

    $felder[Tags::SLUG] = [
        // Unter der Vorlagen-Auswahl: zuerst waehlt man den Titel, dann
        // schmueckt man ihn aus.
        'order' => 20,
        'html'  => $app->view->from($plugin->directory . '/views')->render('field', [
            'tags'   => $liste,
            // Welche gerade an sind, steht im Titel - nicht in einer
            // Einstellung.
            'active' => Tags::active($app, (string) ($kontext['title'] ?? '')),
            'canEdit' => (bool) ($kontext['canEdit'] ?? false),
        ], null),
    ];

    return $felder;
});

// -------------------------------------------------------------------
//  Abnehmen und anbauen
// -------------------------------------------------------------------
$hooks->on('streaminfo.title_bare', static function (mixed $titel) use ($app): string {
    return Tags::strip($app, is_string($titel) ? $titel : '');
});

$hooks->on('streaminfo.title_compose', static function (mixed $blank, Request $request) use ($app): string {
    $gewaehlt = $request->input('si_tags');

    return Tags::prefix(
        $app,
        is_string($blank) ? $blank : '',
        is_array($gewaehlt) ? $gewaehlt : []
    );
});

// -------------------------------------------------------------------
//  Die Liste pflegen
// -------------------------------------------------------------------
$zurueck = static function (string $pfad, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url($pfad) . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

// Ein Reiter auf der Streaminfo-Seite und keine eigene Seite: die Tags
// aendert man oft, und der Weg ueber Plugins > Einstellungen ist dann
// einer zu viel.
//
// Der Reiter heisst "tags", die Adresse bleibt also /stream/info/tags -
// dieselbe wie vorher.
$hooks->on('streaminfo.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('StreaminfoTags.Global.Edit')) {
        return $tabs;
    }

    $tabs['tags'] = [
        'label' => translate('si_tags.tab'),
        'order' => 30,
        // Wird nur fuer den offenen Reiter aufgerufen.
        'render' => static fn (): string => $app->view
            ->from($plugin->directory . '/views')
            ->render('tab', [
                'tags'    => Tags::all($app),
                'usable'  => Tags::usable($app),
                'maxTag'  => Tags::MAX_TAG,
                'maxTags' => Tags::MAX_TAGS,
                'canEdit' => permission('StreaminfoTags.Global.Edit'),
                'csrf'    => $app->auth->csrfToken(),
            ], null),
    ];

    return $tabs;
});

$router->post('/stream/info/tags', static function (Request $request) use ($app, $zurueck): Response {
    $ziel = '/stream/info/tags';

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck($ziel, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('StreaminfoTags.Global.Edit')) {
        return $zurueck($ziel, ['error' => translate('common.error.no_permission')]);
    }

    $zeilen = $request->input('tags');
    $zeilen = is_array($zeilen) ? array_values($zeilen) : [];

    // Anhaengen und Entfernen ohne JavaScript - wie beim Loeschbot und
    // bei den Timern: der Knopf schickt das Formular mit, die Liste
    // kommt vollstaendig an und wird hier veraendert.
    if ($request->input('add') !== '') {
        $zeilen[] = '';
    }

    $weg = $request->input('remove');
    if ($weg !== '' && array_key_exists((int) $weg, $zeilen)) {
        unset($zeilen[(int) $weg]);
        $zeilen = array_values($zeilen);
    }

    Tags::save($app, array_map('strval', $zeilen));

    // Kein Wort darueber, dass ein Titel jetzt anders aussehen koennte:
    // die Liste zu aendern beruehrt den laufenden Titel nicht. Erst wer
    // auf der Streaminfo-Seite speichert, schreibt zu Twitch.
    return $zurueck($ziel, ['notice' => translate('si_tags.saved')]);
}, ['auth' => true]);
