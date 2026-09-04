<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Loeschbot
 * ===================================================================
 *
 * Eine Liste regulaerer Ausdruecke. Passt eine Chatnachricht auf einen
 * davon, wird sie geloescht.
 *
 * Neu gegenueber dem alten System ist das Testfeld: dort tippt man
 * eine Nachricht ein und sieht, ob sie fallen wuerde, welches Muster
 * greift und wie der Text nach der Normalisierung aussieht. Ohne das
 * richtet man eine Sperre ein und weiss nicht, ob sie taugt - und ein
 * Tippfehler im Muster fiel frueher gar nicht auf, weil der Fehler
 * verschluckt wurde.
 *
 * Geloescht wird ueber die Kernfaehigkeit Chat.
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\DeleteBot\Moderator;
use TwitchController\Plugin\DeleteBot\Words;

/** @var \TwitchController\Core\App $app */
/** @var \TwitchController\Core\Plugin\Manifest $plugin */
/** @var \TwitchController\Core\Hook\Hooks $hooks */
/** @var \TwitchController\Core\Http\Router $router */

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['DeleteBot'] = [
        'label'       => translate('delete_bot.name'),
        'permissions' => [
            'DeleteBot.Global.View'   => translate('delete_bot.perm.view'),
            'DeleteBot.Global.Edit'   => translate('delete_bot.perm.edit'),
            'DeleteBot.Global.Toggle' => translate('delete_bot.perm.toggle'),
            'DeleteBot.Global.Test'   => translate('delete_bot.perm.test'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Menue
// -------------------------------------------------------------------
$hooks->on('admin.nav', static function (array $nav) use ($app): array {
    // Anhaengen, nicht setzen: in der Gruppe "Chat" haengen mehrere
    // Plugins, und wer sie setzt, laesst je nach Ladereihenfolge die
    // anderen Menuepunkte verschwinden.
    $nav['chat']['label'] = translate('delete_bot.nav.chat');
    $nav['chat']['order'] = 20;
    $nav['chat']['items'][] = [
        'label'      => translate('delete_bot.name'),
        'href'       => '/chat/delete-bot',
        'permission' => 'DeleteBot.Global.View',
        'toggle'     => [
            'on'         => Words::enabled($app),
            'action'     => '/chat/delete-bot/toggle',
            'value'      => 'toggle',
            'permission' => 'DeleteBot.Global.Toggle',
            'title'      => translate('delete_bot.toggle_hint'),
        ],
    ];

    return $nav;
});

// -------------------------------------------------------------------
//  Eigenes CSS fuers Musterfeld
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/delete-bot/assets/delete-bot.css');

    return $assets;
});

// -------------------------------------------------------------------
//  Jede Chatnachricht pruefen
// -------------------------------------------------------------------
$hooks->on('core.chat.message', static function (array $message) use ($app): void {
    (new Moderator($app))->handle($message);
});

// -------------------------------------------------------------------
//  Die Seite
// -------------------------------------------------------------------
$seite = static function (Request $request) use ($app, $plugin): Response {
    // all() fuer die Oberflaeche - mit den leeren Zeilen, die der
    // Knopf "Muster hinzufuegen" angelegt hat. Geprueft wird mit
    // active().
    $muster = Words::all($app);
    $aktive = Words::active($app);

    // Das Testergebnis kommt aus der Adresse zurueck, damit ein
    // Neuladen nichts erneut abschickt. Geprueft wird hier und nicht
    // im Browser: die Regeln gelten nur, wenn sie aus derselben
    // Quelle kommen wie im Betrieb.
    $probe = trim((string) $request->get('probe'));
    $ergebnis = null;

    if ($probe !== '' && permission('DeleteBot.Global.Test')) {
        $ergebnis = Words::check($probe, $aktive);
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('page', [
        'title'    => translate('delete_bot.name'),
        'active'   => 'chat/delete-bot',
        'enabled'  => Words::enabled($app),
        'words'    => $muster,
        // NICHT 'active' nennen: so heisst der Menueschluessel fuer die
        // Markierung in der Seitenleiste, und der stand hier zweimal
        // im selben Array - der zweite gewinnt, ohne dass PHP etwas
        // sagt. Der Menuepunkt blieb damit unmarkiert.
        'usable'   => $aktive,
        'invalid'  => Words::invalid($aktive),
        'probe'    => $probe,
        'result'   => $ergebnis,
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => (string) $request->get('notice'),
        'error'    => (string) $request->get('error'),
    ]));
};

$router->get('/chat/delete-bot', $seite, [
    'auth'       => true,
    'permission' => 'DeleteBot.Global.View',
]);

// -------------------------------------------------------------------
//  Speichern, testen, schalten
// -------------------------------------------------------------------
$zurueck = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/chat/delete-bot') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/chat/delete-bot/toggle', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if ($request->input('action') !== 'toggle') {
        return $zurueck(['error' => translate('common.error.unknown_action')]);
    }

    if (!permission('DeleteBot.Global.Toggle')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $an = !Words::enabled($app);
    Words::setEnabled($app, $an);

    return $zurueck([
        'notice' => $an ? translate('delete_bot.turned_on') : translate('delete_bot.turned_off'),
    ]);
}, ['auth' => true, 'permission' => 'DeleteBot.Global.View']);

$router->post('/chat/delete-bot', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    $aktion = (string) $request->input('action');

    // -- Testen ------------------------------------------------------
    // Aendert nichts, deshalb ein eigenes Recht: wer die Liste nicht
    // pflegen darf, soll trotzdem nachsehen koennen, warum eine
    // Nachricht verschwunden ist.
    if ($aktion === 'test') {
        if (!permission('DeleteBot.Global.Test')) {
            return $zurueck(['error' => translate('common.error.no_permission')]);
        }

        $probe = trim((string) $request->input('probe'));

        return $zurueck($probe === '' ? [] : ['probe' => $probe]);
    }

    if ($aktion !== 'save') {
        return $zurueck(['error' => translate('common.error.unknown_action')]);
    }

    if (!permission('DeleteBot.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    // Die Zeilen kommen als Feld an: eine Eingabe je Muster, wie im
    // alten System. Ein Textfeld waere weniger Arbeit gewesen, aber
    // dann gaebe es kein "Loeschen" je Zeile - und man muesste zaehlen,
    // in welcher Zeile das kaputte Muster steht.
    $zeilen = $request->post['words'] ?? [];
    $muster = Words::normalize(is_array($zeilen) ? array_map('strval', array_values($zeilen)) : []);

    // Hinzufuegen und Loeschen laufen ueber dasselbe Formular: so
    // gehen die uebrigen Eingaben nicht verloren, und es braucht kein
    // JavaScript.
    $stelle = $request->input('remove');
    if ($stelle !== '' && is_numeric($stelle)) {
        $muster = Words::without($muster, (int) $stelle);
    } elseif ($request->input('add') !== '') {
        $muster = Words::withEmptyRow($muster);
    }

    Words::save($app, $muster);

    $kaputt = Words::invalid($muster);

    // Gespeichert wird trotzdem - ein halb fertiges Muster soll man
    // stehen lassen und weiterschreiben koennen. Gemeldet wird es aber
    // sofort, denn ein kaputtes Muster trifft nie.
    if ($kaputt !== []) {
        return $zurueck([
            'notice' => translate('delete_bot.saved', ['count' => (string) count(Words::active($app))]),
            'error'  => translate('delete_bot.error.invalid', [
                'patterns' => implode(', ', $kaputt),
            ]),
        ]);
    }

    return $zurueck([
        'notice' => translate('delete_bot.saved', [
            'count' => (string) count(Words::active($app)),
        ]),
    ]);
}, ['auth' => true, 'permission' => 'DeleteBot.Global.View']);
