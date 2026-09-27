<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Tip-Goals - PayPal
 * ===================================================================
 *
 * PayPal als Zahlungsanbieter fuer Tip-Goals.
 *
 * Bis 1.x war das hier die ganze Spendenseite. Seit 2.0 ist es nur
 * noch der Weg, auf dem das Geld fliesst: Seite, Ziele, Spenden,
 * Rechtstexte und Alert liegen in Tip-Goals, und dieses Plugin meldet
 * sich dort ueber tips.providers an.
 *
 * Was hier bleibt:
 *
 *   der Zugang zu PayPal       verschluesselt, mit eigener Seite
 *   die Gebuehrensaetze        fuer den Vorschlag "Gebuehren uebernehmen"
 *   Order anlegen              start() - schickt den Spender zu PayPal
 *   die Rueckkehr              Capture, dann Donations::complete()
 *
 * Die Rechte sind die von Tip-Goals. Wer die Spendenseite einrichten
 * darf, darf auch ihren Zahlungsweg einrichten - ein eigener Satz
 * Rechte je Anbieter waere eine Liste mehr zum Pflegen, ohne dass sie
 * etwas anderes erlaubte.
 *
 * @var \TwitchController\Core\App $app
 * @var \TwitchController\Core\Hook\Hooks $hooks
 * @var \TwitchController\Core\Http\Router $router
 * @var \TwitchController\Core\Plugin\Manifest $plugin
 */

use TwitchController\Core\Http\Request;
use TwitchController\Core\Http\Response;
use TwitchController\Plugin\PaypalTipGoals\PayPal;
use TwitchController\Plugin\TipGoals\Donations;
use TwitchController\Plugin\TipGoals\PublicPage;
use TwitchController\Plugin\TipGoals\TipGoals;

// -------------------------------------------------------------------
//  Bei Tip-Goals anmelden
// -------------------------------------------------------------------
$hooks->on('tips.providers', static function (array $anbieter) use ($app): array {
    $anbieter[PayPal::PROVIDER] = [
        'label'       => translate('pp_tip.provider'),
        'ready'       => PayPal::hasCredentials($app),
        'fee_percent' => PayPal::feePercent($app),
        'fee_fixed'   => PayPal::feeFixed($app),
        'settings'    => '/display/goals/tips/paypal',

        /*
         * Order anlegen und den Spender hinschicken.
         *
         * Scheitert es, wird geworfen - Tip-Goals verwirft dann die
         * Spende, merkt sich den Fehler fuer die Verwaltung und zeigt
         * dem Spender einen allgemeinen Satz.
         */
        'start' => static function (array $spende) use ($app): Response {
            $order = PayPal::createOrder(
                $app,
                (string) $spende['token'],
                (float) $spende['amount'],
                (string) $spende['description']
            );

            Donations::markPending($app, (string) $spende['token'], $order['id']);

            return Response::redirect($order['url']);
        },
    ];

    return $anbieter;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[PayPal::SLUG] = [
        'label' => translate('pp_tip.settings'),
        'href'  => '/display/goals/tips/paypal',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Zugang
// -------------------------------------------------------------------
$zurueck = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/paypal') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/tips/paypal', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'          => translate('pp_tip.settings'),
        'active'         => 'display/goals',
        'hasCredentials' => PayPal::hasCredentials($app),
        'live'           => PayPal::live($app),
        'feePercent'     => $app->settings->string('fee_percent', PayPal::FEE_PERCENT, PayPal::scope()),
        'feeFixed'       => $app->settings->string('fee_fixed', PayPal::FEE_FIXED, PayPal::scope()),
        'canEdit'        => permission('TipGoals.Global.Edit'),
        'csrf'           => $app->auth->csrfToken(),
        'notice'         => (string) $request->get('notice'),
        'error'          => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'TipGoals.Global.View']);

$router->post('/display/goals/tips/paypal', static function (Request $request) use ($app, $zurueck): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('TipGoals.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    if ($request->input('action') === 'forget') {
        PayPal::forget($app);

        return $zurueck(['notice' => translate('pp_tip.credentials_forgotten')]);
    }

    // Leere Felder loeschen die Zugangsdaten NICHT: sie werden nie
    // wieder angezeigt, und ein Formular, das man wegen der
    // Gebuehrensaetze abschickt, darf nicht nebenbei den Zugang zum
    // Geldkonto wegwerfen. Zum Loeschen gibt es einen eigenen Knopf.
    PayPal::setCredentials($app, $request->input('client_id'), $request->input('secret'));
    PayPal::setLive($app, $request->input('live') === '1');

    $app->settings->setMany([
        'fee_percent' => TipGoals::money($request->input('fee_percent')),
        'fee_fixed'   => TipGoals::money($request->input('fee_fixed')),
    ], PayPal::scope());

    // Was zuletzt schiefging, gilt nach neuen Angaben nicht mehr.
    TipGoals::setLastError($app, '');

    return $zurueck(['notice' => translate('pp_tip.settings_saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Rueckkehr von PayPal. HIER fliesst Geld.
// -------------------------------------------------------------------
//
//  Unter /tips/paypal/... und nicht /tips/return: die Spendenseite
//  beantwortet /tips/{page} selbst, und das faengt jede Adresse mit
//  einem Abschnitt. In 1.x stand die Rueckkehr genau dort - und kam nie
//  an.
//
//  PayPal haengt seine Order-Nummer als ?token an - nicht unseren
//  Merker. Nachgeschlagen wird also ueber die Order.
$router->get('/tips/paypal/return', static function (Request $request) use ($app): Response {
    $orderId = trim($request->get('token'));
    $spende = Donations::byReference($app, PayPal::PROVIDER, $orderId);

    if ($spende === null) {
        $app->log(PayPal::SLUG . ': Rueckkehr ohne bekannte Order (' . $orderId . ').');

        return PublicPage::done($app, false, translate('pp_tip.done.unknown'), translate('pp_tip.done.unknown_hint'));
    }

    $merker = (string) $spende['token'];

    // Schon gebucht - etwa weil jemand die Seite neu laedt. Dann ist
    // alles in Ordnung, und es wird NICHT noch einmal eingezogen.
    if ((string) $spende['status'] === 'captured') {
        return PublicPage::done($app, true, translate('pp_tip.done.already'), translate('pp_tip.done.already_hint'));
    }

    $einzug = PayPal::capture($app, $orderId, $merker);

    if (!$einzug['ok']) {
        $grund = $einzug['error'] !== '' ? $einzug['error'] : $einzug['status'];

        TipGoals::setLastError($app, translate('pp_tip.provider') . ': ' . $grund);
        $app->log(PayPal::SLUG . ': Einzug fuer ' . $merker . ' gescheitert: ' . $grund);

        return PublicPage::done($app, false, translate('pp_tip.done.failed'), translate('pp_tip.done.failed_hint'));
    }

    // Genau einmal buchen: Tip-Goals laesst von zwei gleichzeitigen
    // Rueckkehrern - Nachladen, zwei Tabs - nur einen durch. Dass PayPal
    // nicht zweimal einzieht, sorgt der Merker als PayPal-Request-Id.
    Donations::complete($app, $merker, $einzug['capture_id'], (float) $einzug['gross'], $einzug['net']);

    return PublicPage::done($app, true, translate('pp_tip.done.thanks'), translate('pp_tip.done.thanks_hint', [
        'amount' => number_format((float) $einzug['gross'], 2, ',', '.'),
    ]));
});

/** Bei PayPal abgebrochen. Nichts ist geflossen. */
$router->get('/tips/paypal/cancel', static function (Request $request) use ($app): Response {
    $merker = trim($request->get('merker'));
    $spende = Donations::byToken($app, $merker);

    // Nur eine eigene Spende - der Merker steht in einer Adresse, und
    // die eines anderen Anbieters geht diesen hier nichts an.
    if ($spende !== null && (string) $spende['provider'] === PayPal::PROVIDER) {
        Donations::markCancelled($app, $merker);
    }

    return PublicPage::back($app, 'cancelled');
});
