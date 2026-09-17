<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Tip-Goals - PayPal
 * ===================================================================
 *
 * Eine Spendenseite unter /tips und die Ziele, auf die gespendet wird.
 *
 * Der Unterschied zu den Tip-Goals von StreamElements und Streamlabs:
 * dort kommt eine Spende aus einer Schnittstelle und weiss nichts von
 * Zielen, also landet sie auf dem obersten. Hier waehlt der SPENDER,
 * und das ist der Sinn der Seite. Darum vertragen sich die drei nicht -
 * nebeneinander zaehlte jede Spende doppelt.
 *
 * Die oeffentliche Seite legt KEIN Konto an: wer sich dort anmeldet,
 * bekommt ein signiertes Cookie mit seinem Twitch-Namen, und das Token
 * aus der Anmeldung wird weggeworfen. Wie bei /raidme.
 *
 * Ohne Impressum geht die Seite NICHT online. Eine oeffentliche Seite,
 * die Geld annimmt, braucht es - und ein Plugin aus dem Katalog darf
 * keines mitbringen, sonst betreibt der Naechste eine Spendenseite mit
 * fremden Angaben.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\Alerts\Alerts;
use TwitchController\Plugin\Goals\Goals;
use TwitchController\Plugin\PaypalTipGoals\Donations;
use TwitchController\Plugin\PaypalTipGoals\Legal;
use TwitchController\Plugin\PaypalTipGoals\PayPal;
use TwitchController\Plugin\PaypalTipGoals\TipAlert;
use TwitchController\Plugin\PaypalTipGoals\TipGoals;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['PaypalTipGoals'] = [
        'label'       => translate('pp_tip.name'),
        'permissions' => [
            'PaypalTipGoals.Global.View' => translate('pp_tip.perm.view'),
            'PaypalTipGoals.Global.Edit' => translate('pp_tip.perm.edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Dateien und Einstellungen
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/paypal-tip-goals/assets/tip-goals.css');

    return $assets;
});

/*
 * Im Overlay: durch die Ziele rotieren.
 *
 * Nur hier und nicht bei den anderen beiden Spendenquellen - auf
 * dieser Seite waehlt der Spender sein Ziel, also ist jedes in der
 * Liste eines, auf das gerade eingezahlt werden kann. Wo die Spende
 * aus einer Schnittstelle kommt und immer auf dem obersten landet,
 * zeigte ein rotierender Balken ein Ziel, auf das nichts einzahlen
 * kann.
 *
 * Nach Goals: das Skript braucht dessen GOALS.patch. Die Reihenfolge
 * ergibt sich von selbst, weil dieses Plugin Goals voraussetzt und
 * die Ladereihenfolge den Abhaengigkeiten folgt.
 */
$hooks->on('overlay.assets', static function (array $assets) use ($app): array {
    $assets['js'][] = $app->asset('/plugin/paypal-tip-goals/assets/tips-overlay.js');

    return $assets;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[TipGoals::SLUG] = [
        'label' => translate('pp_tip.settings'),
        'href'  => '/display/goals/tips/settings',
    ];

    $links[TipGoals::SLUG . ':appearance'] = [
        'label' => translate('pp_tip.appearance'),
        'href'  => '/display/goals/tips/appearance',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Reiter auf der Goals-Seite
// -------------------------------------------------------------------
$hooks->on('goals.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('PaypalTipGoals.Global.View')) {
        return $tabs;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    $tabs['tips'] = [
        'label' => translate('pp_tip.tab'),
        'order' => 20,
        'render' => static fn (): string => $vorlagen->render('tab', [
            'goals'     => TipGoals::all($app),
            'maxGoals'  => TipGoals::MAX_GOALS,
            'ready'     => Legal::pageReady($app),
            'lastError' => $app->settings->string('last_error', '', TipGoals::scope()),
            'publicUrl' => $app->url('/tips'),
            'recent'    => Donations::recent($app, 10),
            'canEdit'   => permission('PaypalTipGoals.Global.Edit'),
            'csrf'      => $app->auth->csrfToken(),
        ], null),
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Der Balken im Overlay
// -------------------------------------------------------------------
$hooks->on('goals.markup', static function (array $teile) use ($app): array {
    $teile['paypal-tip-goals'] = [
        'order' => 20,
        'html'  => TipGoals::html($app),
        'css'   => TipGoals::css($app),
    ];

    return $teile;
});

$hooks->on('goals.state', static function (array $zustand) use ($app): array {
    return array_merge($zustand, TipGoals::values($app));
});

$hooks->on('goals.stamp', static function (mixed $stempel) use ($app): int {
    return max((int) $stempel, TipGoals::stamp($app));
});

// -------------------------------------------------------------------
//  Aufraeumen
// -------------------------------------------------------------------
// Gedrosselt in Donations::cleanup(): hoechstens einmal am Tag.
$hooks->on('cron.tick', static function () use ($app): void {
    Donations::cleanup($app);
});

// -------------------------------------------------------------------
//  Die Zielliste
// -------------------------------------------------------------------
$zurueck = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/display/goals/tips', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('PaypalTipGoals.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'add') {
        if (!TipGoals::add($app)) {
            return $zurueck(['error' => translate('pp_tip.too_many', [
                'max' => (string) TipGoals::MAX_GOALS,
            ])]);
        }

        return $zurueck();
    }

    if ($aktion === 'remove') {
        TipGoals::remove($app, (int) $request->input('id'));
        TipGoals::push($app);

        return $zurueck(['notice' => translate('pp_tip.removed')]);
    }

    if ($aktion === 'up' || $aktion === 'down') {
        TipGoals::move($app, (int) $request->input('id'), $aktion === 'up' ? -1 : 1);
        TipGoals::push($app);

        return $zurueck();
    }

    $eingaben = $request->post['goals'] ?? [];
    TipGoals::save($app, is_array($eingaben) ? $eingaben : []);
    TipGoals::push($app);

    return $zurueck(['notice' => translate('pp_tip.saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Einstellungen: PayPal, Impressum, Datenschutz, AGB
// -------------------------------------------------------------------
//
//  Vier Reiter auf EINER Seite und nicht vier Eintraege in der
//  Plugin-Liste: es ist eine Einrichtung mit vier Abschnitten - und wer
//  das Impressum schreibt, will danach die AGB schreiben, ohne dafuer
//  zwei Ebenen hoch zu navigieren.
$zurueckEinst = static function (string $reiter, array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/settings/' . rawurlencode($reiter))
        . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$einstellungen = static function (Request $request, array $params = []) use ($app, $plugin): Response {
    $reiter = strtolower(trim((string) ($params['tab'] ?? 'paypal')));

    // Ein unbekannter Reitername fuehrt auf den ersten und nicht auf
    // eine Fehlerseite - die Adresse kann aus einem Lesezeichen kommen.
    if ($reiter !== 'paypal' && !Legal::isPage($reiter)) {
        $reiter = 'paypal';
    }

    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'     => translate('pp_tip.settings'),
        'active'    => 'display/goals',
        'tab'       => $reiter,
        'pages'     => Legal::PAGES,
        'text'      => $reiter === 'paypal' ? '' : Legal::text($app, $reiter),
        'maxLength' => Legal::MAX_LENGTH,

        'hasCredentials' => PayPal::hasCredentials($app),
        'live'           => PayPal::live($app),
        'brand'          => $app->settings->string('brand', '', TipGoals::scope()),
        'presets'        => $app->settings->string('presets', '1, 2, 5, 10', TipGoals::scope()),
        'minAmount'      => $app->settings->string('min_amount', '1', TipGoals::scope()),
        'defaultAmount'  => $app->settings->string('default_amount', '5', TipGoals::scope()),
        'feePercent'     => $app->settings->string('fee_percent', TipGoals::FEE_PERCENT, TipGoals::scope()),
        'feeFixed'       => $app->settings->string('fee_fixed', TipGoals::FEE_FIXED, TipGoals::scope()),

        'pageReady' => Legal::pageReady($app),
        'publicUrl' => $app->url('/tips'),
        'canEdit'   => permission('PaypalTipGoals.Global.Edit'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
};

// Ohne Reiter zuerst - sonst faengt {tab} den Aufruf.
$router->get('/display/goals/tips/settings', $einstellungen, [
    'auth' => true, 'permission' => 'PaypalTipGoals.Global.View',
]);
$router->get('/display/goals/tips/settings/{tab}', $einstellungen, [
    'auth' => true, 'permission' => 'PaypalTipGoals.Global.View',
]);

$router->post('/display/goals/tips/settings', static function (Request $request) use ($app, $zurueckEinst): Response {
    $reiter = strtolower(trim($request->input('tab')));
    if ($reiter !== 'paypal' && !Legal::isPage($reiter)) {
        $reiter = 'paypal';
    }

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckEinst($reiter, ['error' => translate('common.error.form_expired')]);
    }

    if (!permission('PaypalTipGoals.Global.Edit')) {
        return $zurueckEinst($reiter, ['error' => translate('common.error.no_permission')]);
    }

    if ($reiter !== 'paypal') {
        Legal::save($app, $reiter, $request->input('text'));

        return $zurueckEinst($reiter, ['notice' => translate('pp_tip.legal_saved')]);
    }

    if ($request->input('action') === 'forget') {
        PayPal::forget($app);

        return $zurueckEinst('paypal', ['notice' => translate('pp_tip.credentials_forgotten')]);
    }

    // Leere Felder loeschen die Zugangsdaten NICHT: sie werden nie
    // wieder angezeigt, und ein Formular, das man wegen der
    // Vorgabebetraege abschickt, darf nicht nebenbei den Zugang zum
    // Geldkonto wegwerfen. Zum Loeschen gibt es einen eigenen Knopf.
    PayPal::setCredentials($app, $request->input('client_id'), $request->input('secret'));
    PayPal::setLive($app, $request->input('live') === '1');

    $app->settings->setMany([
        'brand'          => trim($request->input('brand')),
        'presets'        => trim($request->input('presets')),
        'min_amount'     => TipGoals::money($request->input('min_amount')),
        'default_amount' => TipGoals::money($request->input('default_amount')),
        'fee_percent'    => TipGoals::money($request->input('fee_percent')),
        'fee_fixed'      => TipGoals::money($request->input('fee_fixed')),
        // Was zuletzt schiefging, gilt nach neuen Angaben nicht mehr.
        'last_error'     => '',
    ], TipGoals::scope());

    return $zurueckEinst('paypal', ['notice' => translate('pp_tip.settings_saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Das Aussehen des Balkens
// -------------------------------------------------------------------
$zurueckAussehen = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/appearance') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/tips/appearance', static function (Request $request) use ($app, $plugin): Response {
    $html = TipGoals::html($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('appearance', [
        'title'    => translate('pp_tip.appearance'),
        'active'   => 'display/goals',
        'html'     => $html,
        'css'      => TipGoals::css($app),
        'custom'   => TipGoals::isCustom($app),
        'missing'  => Goals::missing($html, TipGoals::REQUIRED_BINDINGS, TipGoals::REQUIRED_FILLS),
        'required' => [
            'tip_title'   => translate('pp_tip.bind.title'),
            'tip_current' => translate('pp_tip.bind.current'),
            'tip_goal'    => translate('pp_tip.bind.goal'),
        ],
        'fills'    => ['tip' => translate('pp_tip.bind.fill')],
        'canEdit'  => permission('PaypalTipGoals.Global.Edit'),
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => (string) $request->get('notice'),
        'error'    => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'PaypalTipGoals.Global.View']);

$router->post('/display/goals/tips/appearance', static function (Request $request) use ($app, $zurueckAussehen): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckAussehen(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('PaypalTipGoals.Global.Edit')) {
        return $zurueckAussehen(['error' => translate('common.error.no_permission')]);
    }

    if ($request->input('action') === 'reset') {
        TipGoals::resetAppearance($app);

        return $zurueckAussehen(['notice' => translate('pp_tip.reset_done')]);
    }

    $fehlend = TipGoals::saveAppearance($app, $request->input('html'), $request->input('css'));

    return $zurueckAussehen($fehlend === []
        ? ['notice' => translate('pp_tip.appearance_saved')]
        : ['error' => translate('pp_tip.missing_hint') . ' ' . implode(', ', $fehlend)]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die oeffentliche Seite: /tips
// -------------------------------------------------------------------
//
//  Ohne 'auth' - das ist der Sinn. Was hier passieren kann, ist genau
//  eines: sich selbst eine Spende vormerken und zu PayPal geschickt
//  werden. Kein Recht, keine Einstellung, keine Auskunft ueber andere.
//
//  Ohne Impressum bleibt die Seite zu. Eine Seite, die Geld annimmt,
//  ohne zu sagen, wer es bekommt, soll es hier nicht geben.

/** Eine Seite der oeffentlichen Spendenstrecke rendern. */
$oeffentlich = static function (string $vorlage, array $daten = [], int $status = 200) use ($app, $plugin): Response {
    $wer = Donations::identity($app);

    // Die Wurzel ist /views und NICHT /views/public - obwohl alle
    // Seiten hier darunter liegen.
    //
    // Denn die Seiten holen sich ihren Rahmen selbst, mit
    // $view->render('public/_head'). Waere die Wurzel schon
    // /views/public, suchte der das unter views/public/public/_head -
    // und genau das ist beim ersten Anlauf passiert.
    return Response::html(
        $app->view->from($plugin->directory . '/views')->render('public/' . $vorlage, $daten + [
            'brand'    => TipGoals::brand($app),
            'identity' => $wer,
            'legal'    => Legal::available($app),
            'notice'   => '',
            'error'    => '',
        ], null),
        $status
    );
};

$router->get('/tips', static function (Request $request) use ($app, $oeffentlich): Response {
    if (!Legal::pageReady($app)) {
        // 503 und nicht 404: die Seite gibt es, sie ist nur nicht
        // fertig eingerichtet. Ein Suchdienst soll sie nicht als
        // dauerhaft weg vermerken.
        return $oeffentlich('closed', [], 503);
    }

    // Die Werte kommen HIER aus den Einstellungen und nicht in der
    // Vorlage: eine Vorlage, die selbst in die Datenbank greift, laesst
    // sich nicht ohne eine pruefen - und genau daran ist der
    // Renderdurchlauf haengengeblieben.
    return $oeffentlich('landing', [
        'goals'      => TipGoals::all($app),
        'canPay'     => PayPal::hasCredentials($app),
        'minimum'    => TipGoals::minAmount($app),
        'vorgabe'    => TipGoals::defaultAmount($app),
        'presets'    => TipGoals::presets($app),
        'feePercent' => TipGoals::feePercent($app),
        'feeFixed'   => TipGoals::feeFixed($app),
        'csrf'       => Donations::csrfToken($app),
        'notice'     => (string) $request->get('notice'),
        'error'      => (string) $request->get('error'),
    ]);
});

// Die Anmeldung. Ohne Freigaben: gebraucht wird nur die Auskunft, wer
// da ist - und je weniger man verlangt, desto eher drueckt jemand auf
// "erlauben".
$router->get('/tips/login', static function () use ($app): Response {
    return Response::redirect($app->twitch->oauth()->authorizeUrl(Donations::PURPOSE, [], false));
});

$router->get('/tips/logout', static function () use ($app): Response {
    Donations::forget($app);

    return Response::redirect($app->url('/tips'));
});

/** Die Rechtstexte - jeder unter seinem eigenen Namen. */
$router->get('/tips/{page}', static function (Request $request, array $params) use ($app, $oeffentlich): Response {
    $schluessel = strtolower(trim((string) ($params['page'] ?? '')));

    if (!Legal::isPage($schluessel) || Legal::text($app, $schluessel) === '') {
        return $oeffentlich('closed', [], 404);
    }

    return $oeffentlich('legal', [
        'heading' => translate(Legal::PAGES[$schluessel]),
        'body'    => Legal::html($app, $schluessel),
    ]);
});

// -------------------------------------------------------------------
//  Spenden
// -------------------------------------------------------------------
$zurueckTips = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/tips') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->post('/tips', static function (Request $request) use ($app, $zurueckTips): Response {
    if (!Legal::pageReady($app)) {
        return $zurueckTips();
    }

    if (!Donations::checkCsrf($app, $request->input('csrf'))) {
        return $zurueckTips(['error' => translate('common.error.form_expired')]);
    }

    $wer = Donations::identity($app);
    if ($wer === null) {
        return $zurueckTips(['error' => translate('pp_tip.error.not_signed_in')]);
    }

    if (!PayPal::hasCredentials($app)) {
        return $zurueckTips(['error' => translate('pp_tip.error.no_credentials')]);
    }

    // Das Haekchen steht immer da - es traegt auch die
    // Altersbestaetigung. Geprueft wird es hier und nicht nur im
    // Browser: required im Formular ist eine Bequemlichkeit, keine
    // Bedingung.
    if ($request->input('accept_terms') !== '1') {
        return $zurueckTips(['error' => translate('pp_tip.error.terms')]);
    }

    // Das Ziel: leer oder "none" heisst "einfach so". Sonst muss es das
    // Ziel geben - ein Formular kann alt sein, und in der Zwischenzeit
    // wurde vielleicht geloescht.
    $zielRoh = trim($request->input('goal'));
    $zielId = null;

    if ($zielRoh !== '' && $zielRoh !== 'none') {
        if (!ctype_digit($zielRoh) || !TipGoals::exists($app, (int) $zielRoh)) {
            return $zurueckTips(['error' => translate('pp_tip.error.goal_gone')]);
        }

        $zielId = (int) $zielRoh;
    }

    $betrag = (float) TipGoals::money($request->input('amount'));

    // Wer die Gebuehren uebernimmt, hat den Betrag gemeint, der ANKOMMT.
    if ($request->input('cover_fees') === '1') {
        $betrag = TipGoals::gross($app, $betrag);
    }

    if ($betrag < TipGoals::minAmount($app)) {
        return $zurueckTips(['error' => translate('pp_tip.error.too_small', [
            'min' => number_format(TipGoals::minAmount($app), 2, ',', '.'),
        ])]);
    }

    if ($betrag > PayPal::MAX_AMOUNT) {
        return $zurueckTips(['error' => translate('pp_tip.error.too_large', [
            'max' => number_format(PayPal::MAX_AMOUNT, 2, ',', '.'),
        ])]);
    }

    $nachricht = trim($request->input('message'));
    if ($nachricht !== '' && preg_match('/^.{0,280}/us', $nachricht, $treffer) === 1) {
        $nachricht = $treffer[0];
    }

    $merker = Donations::create(
        $app,
        $wer,
        $betrag,
        $nachricht !== '' ? $nachricht : null,
        $request->input('anonymous') === '1',
        $zielId
    );

    try {
        $order = PayPal::createOrder($app, $merker, $betrag, translate('pp_tip.order_description', [
            'name' => $wer['login'],
        ]));
    } catch (Throwable $e) {
        Donations::markCancelled($app, $merker);
        $app->settings->set('last_error', $e->getMessage(), TipGoals::scope());
        $app->log(TipGoals::SLUG . ': Order fuer ' . $wer['login'] . ' gescheitert: ' . $e->getMessage());

        return $zurueckTips(['error' => $e->getMessage()]);
    }

    Donations::markApproved($app, $merker, $order['id']);

    // Ab hier ist der Spender bei PayPal.
    return Response::redirect($order['url']);
});

/**
 * Die Rueckkehr von PayPal. HIER fliesst Geld.
 *
 * PayPal haengt seine Order-Nummer als ?token an - nicht unseren
 * Merker. Nachgeschlagen wird also ueber die Order.
 */
$router->get('/tips/return', static function (Request $request) use ($app, $oeffentlich): Response {
    $orderId = trim($request->get('token'));
    $spende = Donations::byOrder($app, $orderId);

    if ($spende === null) {
        $app->log(TipGoals::SLUG . ': Rueckkehr ohne bekannte Order (' . $orderId . ').');

        return $oeffentlich('done', [
            'ok'      => false,
            'heading' => translate('pp_tip.done.unknown'),
            'body'    => translate('pp_tip.done.unknown_hint'),
        ]);
    }

    $merker = (string) $spende['token'];

    // Schon gebucht - etwa weil jemand die Seite neu laedt. Dann ist
    // alles in Ordnung, und es wird NICHT noch einmal eingezogen.
    if ((string) $spende['status'] === 'captured') {
        return $oeffentlich('done', [
            'ok'      => true,
            'heading' => translate('pp_tip.done.already'),
            'body'    => translate('pp_tip.done.already_hint'),
        ]);
    }

    $einzug = PayPal::capture($app, $orderId, $merker);

    if (!$einzug['ok']) {
        $app->settings->set('last_error', $einzug['error'], TipGoals::scope());
        $app->log(TipGoals::SLUG . ': Einzug fuer ' . $merker . ' gescheitert: '
            . ($einzug['error'] !== '' ? $einzug['error'] : $einzug['status']));

        return $oeffentlich('done', [
            'ok'      => false,
            'heading' => translate('pp_tip.done.failed'),
            'body'    => translate('pp_tip.done.failed_hint'),
        ]);
    }

    // Genau einmal buchen. Zwei gleichzeitige Rueckkehrer - Nachladen,
    // zwei Tabs - kommen hier nur einmal durch.
    if (Donations::markCaptured($app, $merker, $einzug['capture_id'])) {
        $netto = $einzug['net'] ?? $einzug['gross'];

        TipGoals::applyDonation($app, (float) $netto, $spende['goal_id'] === null ? null : (int) $spende['goal_id']);

        $hooks = $app->hooks;
        $hooks->dispatch('tips.donation', [
            'login'   => (string) $spende['twitch_login'],
            'name'    => ((bool) $spende['anonymous'])
                ? translate('pp_tip.anonymous')
                : (string) $spende['twitch_display_name'],
            'amount'  => (float) $einzug['gross'],
            'net'     => (float) $netto,
            'message' => (string) ($spende['message'] ?? ''),
            'goal_id' => $spende['goal_id'],
        ]);

        $app->log(TipGoals::SLUG . ': Spende ueber ' . number_format((float) $einzug['gross'], 2, '.', '')
            . ' von ' . $spende['twitch_login'] . ' gebucht.');
    }

    return $oeffentlich('done', [
        'ok'      => true,
        'heading' => translate('pp_tip.done.thanks'),
        'body'    => translate('pp_tip.done.thanks_hint', [
            'amount' => number_format((float) $einzug['gross'], 2, ',', '.'),
        ]),
    ]);
});

/** Bei PayPal abgebrochen. Nichts ist geflossen. */
$router->get('/tips/cancel', static function (Request $request) use ($app, $zurueckTips): Response {
    $merker = trim($request->get('merker'));

    if ($merker !== '') {
        Donations::markCancelled($app, $merker);
    }

    return $zurueckTips(['notice' => translate('pp_tip.cancelled')]);
});

// -------------------------------------------------------------------
//  Der Rueckweg von Twitch
// -------------------------------------------------------------------
// Das Token wird NICHT gespeichert: gebraucht wird die Auskunft, wer da
// ist, und ein Token eines fremden Kanals waere ein Schluessel, fuer
// den es kein Schloss gibt.
$hooks->on('core.oauth.callback', static function (
    mixed $behandelt,
    string $zweck,
    array $token,
    array $twitchUser
) use ($app): mixed {
    if ($behandelt instanceof Response || $zweck !== Donations::PURPOSE) {
        return $behandelt;
    }

    $login = strtolower(trim((string) ($twitchUser['login'] ?? '')));

    if ($login === '') {
        return Response::redirect(
            $app->url('/tips') . '?error=' . rawurlencode(translate('pp_tip.error.no_identity'))
        );
    }

    Donations::remember(
        $app,
        $login,
        (string) ($twitchUser['display_name'] ?? $login),
        (string) ($twitchUser['id'] ?? '')
    );

    return Response::redirect($app->url('/tips'));
});

// -------------------------------------------------------------------
//  Der Alert im Stream
// -------------------------------------------------------------------
//
//  Nur, wenn Alerts installiert ist - darum "optional" im Manifest und
//  eine Pruefung hier. Fehlt es, passiert nichts: die Spende ist
//  trotzdem gebucht, der Balken waechst, und im Stream bleibt es still.
//
//  Der eigene Hook tips.donation steht davor. So kann ein anderes
//  Plugin dasselbe Ereignis abgreifen - etwa fuer eine Chatnachricht -
//  ohne dass dieses hier davon wissen muss.
$hooks->on('tips.donation', static function (array $spende) use ($app): void {
    TipAlert::fire($app, $spende);
});

// -------------------------------------------------------------------
//  Der Reiter auf der Alerts-Seite
// -------------------------------------------------------------------
//
//  Er fehlte, und damit fehlte jede Einstellmoeglichkeit: der Alert
//  feuerte mit einem festen Vorgabetext, ohne Video, ohne Ton, ohne
//  Dauer. Im alten System hiess dieser Reiter "Spende".
$hooks->on('alerts.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('PaypalTipGoals.Global.View')) {
        return $tabs;
    }

    $tabs['tips-paypal'] = [
        'label' => translate('pp_tip.alert.tab'),
        'order' => 60,
        // Wird nur fuer den offenen Reiter aufgerufen.
        'render' => static fn (): string => $app->view
            ->from($plugin->directory . '/views')
            ->render('alert_tab', [
                'config'          => TipAlert::config($app),
                'placeholders'    => TipAlert::PLACEHOLDERS,
                'preview'         => TipAlert::values([
                    'name'    => 'Bukanier',
                    'amount'  => 5.0,
                    'message' => translate('pp_tip.alert.test_message'),
                ]),
                'target'          => $app->url('/display/alerts/tips-paypal'),
                'canEdit'         => permission('PaypalTipGoals.Global.Edit'),
                'canToggle'       => permission('PaypalTipGoals.Global.Edit'),
                'canTest'         => permission('PaypalTipGoals.Global.Edit'),
                'defaultDuration' => Alerts::DEFAULT_DURATION,
                'maxText'         => TipAlert::MAX_TEXT,
                'maxTiers'        => TipAlert::MAX_TIERS,
                'csrf'            => $app->auth->csrfToken(),
            ], null),
    ];

    return $tabs;
});

$router->post('/display/alerts/tips-paypal', static function (Request $request) use ($app): Response {
    $zurueck = static function (?string $notice, ?string $error = null) use ($app): Response {
        $query = array_filter([
            'notice' => $notice,
            'error'  => $error,
        ], static fn (?string $wert): bool => $wert !== null);

        return Response::redirect(
            $app->url('/display/alerts/tips-paypal')
            . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if (!permission('PaypalTipGoals.Global.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    switch ($request->input('action')) {
        case 'toggle':
            $an = !TipAlert::config($app)['enabled'];
            TipAlert::setEnabled($app, $an);

            return $zurueck($an
                ? translate('pp_tip.alert.turned_on')
                : translate('pp_tip.alert.turned_off'));

        case 'test':
            // Der Hauptschalter von Alerts steht darueber. Ohne diesen
            // Hinweis sucht man den Fehler beim Text.
            if (!Alerts::enabled($app)) {
                return $zurueck(null, translate('pp_tip.alert.test_while_all_off'));
            }

            if (!TipAlert::config($app)['enabled']) {
                return $zurueck(null, translate('pp_tip.alert.test_while_off'));
            }

            // Die Werte aus dem Formular gelten fuer diesen einen Test
            // und werden nicht gespeichert.
            $werte = $request->post['preview'] ?? [];
            $werte = is_array($werte) ? array_map('strval', $werte) : [];

            $config = TipAlert::config($app);

            /*
             * Die Stufe zum eingetippten Betrag - dieselbe, die eine
             * echte Spende dieser Hoehe ausloesen wuerde.
             *
             * Sonst zeigte der Test immer die unterste, und wer eine
             * Stufe fuer 50 Euro einrichtet, koennte sie nie ansehen,
             * ohne 50 Euro zu spenden.
             */
            $stufe = TipAlert::tierFor(
                $config['tiers'],
                TipAlert::amountFrom((string) ($werte['amount'] ?? ''))
            );

            $ok = Alerts::send($app, [
                'kind'     => 'tip',
                'text'     => $stufe['text'],
                'video'    => $stufe['video'],
                'audio'    => $stufe['audio'],
                'duration' => $stufe['duration'],
                'values'   => $werte,
            ]);

            return $ok
                ? $zurueck(translate('pp_tip.alert.test_sent'))
                : $zurueck(null, translate('pp_tip.alert.test_failed'));

        case 'save':
            /*
             * Die Stufen kommen als Feld an: tiers[0][min_amount],
             * tiers[0][text] und so fort. $request->input() liefert
             * nur Zeichenketten - ein verschachteltes Feld muss aus
             * post kommen.
             */
            $stufen = $request->post['tiers'] ?? [];
            $stufen = is_array($stufen) ? array_values($stufen) : [];

            /*
             * Hinzufuegen und Entfernen laufen ueber dasselbe
             * Formular: so bleibt stehen, was daneben schon eingetippt
             * ist. Gespeichert wird dabei mit - das ist der Preis
             * dafuer, dass es ohne JavaScript funktioniert.
             */
            $weg = $request->input('remove_tier');

            if ($weg !== '' && is_numeric($weg)) {
                $stufen = TipAlert::withoutTier($stufen, (int) $weg);
            } elseif ($request->input('add_tier') !== '') {
                $stufen = TipAlert::withNewTier($stufen);
            }

            TipAlert::save($app, ['tiers' => $stufen]);

            return $zurueck(translate('pp_tip.alert.saved'));
    }

    return $zurueck(null, translate('common.error.unknown_action'));
}, ['auth' => true]);
