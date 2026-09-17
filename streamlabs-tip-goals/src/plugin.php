<?php

declare(strict_types=1);

/**
 * ===================================================================
 *  Streamlabs-Tip-Goals
 * ===================================================================
 *
 * Spendenziele als Balken im Overlay, gespeist aus Streamlabs.
 *
 * Braucht Goals - dort haengt der Reiter und der Platz im Overlay.
 * Und es vertraegt sich NICHT mit Streamlabs-Tip-Goals: beide buchen
 * auf dasselbe Ziel, nebeneinander zaehlte jede Spende doppelt. Die
 * Sperre dafuer steht als "conflicts" in plugin.json; der Kern laesst
 * das zweite gar nicht erst installieren.
 *
 * Spenden gehen IMMER auf das erste Ziel der Liste. Siehe TipGoals.
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
use TwitchController\Plugin\StreamlabsTipGoals\Source;
use TwitchController\Plugin\StreamlabsTipGoals\TipAlert;
use TwitchController\Plugin\StreamlabsTipGoals\TipGoals;

// -------------------------------------------------------------------
//  Rechte
// -------------------------------------------------------------------
$hooks->on('permissions.catalog', static function (array $catalog): array {
    $catalog['SlTipGoals'] = [
        'label'       => translate('sl_tip.name'),
        'permissions' => [
            'SlTipGoals.Global.View' => translate('sl_tip.perm.view'),
            'SlTipGoals.Global.Edit' => translate('sl_tip.perm.edit'),
        ],
    ];

    return $catalog;
});

// -------------------------------------------------------------------
//  Dateien und Einstellungen
// -------------------------------------------------------------------
$hooks->on('admin.assets', static function (array $assets) use ($app): array {
    $assets['css'][] = $app->asset('/plugin/streamlabs-tip-goals/assets/tip-goals.css');

    return $assets;
});

$hooks->on('plugin.settings', static function (array $links): array {
    $links[TipGoals::SLUG] = [
        'label' => translate('sl_tip.settings'),
        'href'  => '/display/goals/tips/settings',
    ];

    // Das Aussehen als zweiter Eintrag - wie bei den Twitch-Zielen.
    // Zugang und Aussehen sind zwei verschiedene Fragen, und wer den
    // Balken umbaut, sucht nicht bei den Zugangsdaten.
    $links[TipGoals::SLUG . ':appearance'] = [
        'label' => translate('sl_tip.appearance'),
        'href'  => '/display/goals/tips/appearance',
    ];

    return $links;
});

// -------------------------------------------------------------------
//  Der Reiter auf der Goals-Seite
// -------------------------------------------------------------------
$hooks->on('goals.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('SlTipGoals.Global.View')) {
        return $tabs;
    }

    $vorlagen = $app->view->from($plugin->directory . '/views');

    $tabs['tips'] = [
        'label' => translate('sl_tip.tab'),
        // Hinter den Twitch-Zielen: im alten System standen Follower
        // und Subs oben, das Spendenziel darunter.
        'order' => 20,
        'render' => static fn (): string => $vorlagen->render('tab', [
            'goals'   => TipGoals::all($app),
            'maxGoals' => TipGoals::MAX_GOALS,
            'ready'   => Source::ready($app),
            'lastError' => $app->settings->string('last_error', '', TipGoals::scope()),
            'canEdit' => permission('SlTipGoals.Global.Edit'),
            'csrf'    => $app->auth->csrfToken(),
        ], null),
    ];

    return $tabs;
});

// -------------------------------------------------------------------
//  Der Balken im Overlay
// -------------------------------------------------------------------
$hooks->on('goals.markup', static function (array $teile) use ($app): array {
    $teile['streamlabs-tip-goals'] = [
        'order' => 20,
        'html'  => TipGoals::html($app),
        'css'   => TipGoals::css($app),
    ];

    return $teile;
});

// Der letzte bekannte Stand, damit im Overlay schon etwas steht, bevor
// die erste Spende kommt. Ohne das stuende der Balken nach jedem
// Neuladen auf null - und genau dieser Fehler ist bei den Twitch-Zielen
// schon einmal passiert.
$hooks->on('goals.state', static function (array $zustand) use ($app): array {
    return array_merge($zustand, TipGoals::values($app));
});

// Aendert sich das Geruest, muss OBS nachladen.
$hooks->on('goals.stamp', static function (mixed $stempel) use ($app): int {
    return max((int) $stempel, TipGoals::stamp($app));
});

// -------------------------------------------------------------------
//  Nachfragen
// -------------------------------------------------------------------
$hooks->on('cron.tick', static function () use ($app): void {
    $letzte = $app->settings->int('checked_at', 0, TipGoals::scope());
    if (time() - $letzte < TipGoals::POLL_SECONDS) {
        return;
    }

    $app->settings->set('checked_at', time(), TipGoals::scope());

    $ergebnis = Source::collect($app);

    // Der Fehler wird GEMERKT und nicht nur geloggt: eine Abfrage, die
    // still scheitert, sieht auf der Seite aus wie eine, bei der nichts
    // passiert ist. Das hat hier schon einmal eine Woche gekostet.
    $app->settings->set('last_error', $ergebnis['error'], TipGoals::scope());

    if ($ergebnis['error'] !== '') {
        $app->log(TipGoals::SLUG . ': Abfrage gescheitert: ' . $ergebnis['error']);

        return;
    }

    // Je Spende einzeln buchen und melden.
    //
    // Vorher wurde die SUMME gebucht und sonst nichts - fuer den
    // Balken reicht das, fuer einen Alert nicht: aus "12,50" laesst
    // sich nicht mehr herausfinden, wer wie viel geschickt hat.
    foreach ($ergebnis['donations'] as $spende) {
        TipGoals::applyDonation($app, (float) $spende['amount']);

        // Ein eigener Hook davor, damit ein anderes Plugin dasselbe
        // Ereignis abgreifen kann - etwa fuer eine Chatnachricht.
        $app->hooks->dispatch('tips.donation', $spende);

        TipAlert::fire($app, $spende);
    }
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

    if (!permission('SlTipGoals.Global.Edit')) {
        return $zurueck(['error' => translate('common.error.no_permission')]);
    }

    $aktion = $request->input('action');

    if ($aktion === 'add') {
        if (!TipGoals::add($app)) {
            return $zurueck(['error' => translate('sl_tip.too_many', [
                'max' => (string) TipGoals::MAX_GOALS,
            ])]);
        }

        return $zurueck();
    }

    if ($aktion === 'remove') {
        TipGoals::remove($app, (int) $request->input('id'));
        TipGoals::push($app);

        return $zurueck(['notice' => translate('sl_tip.removed')]);
    }

    if ($aktion === 'up' || $aktion === 'down') {
        TipGoals::move($app, (int) $request->input('id'), $aktion === 'up' ? -1 : 1);

        // Verschieben aendert, welches Ziel das laufende ist - also
        // auch, was im Overlay steht.
        TipGoals::push($app);

        return $zurueck();
    }

    // Speichern. Die Felder kommen als goals[<id>][titel] herein, also
    // ueber $request->post - input() gibt immer eine Zeichenkette.
    $eingaben = $request->post['goals'] ?? [];
    TipGoals::save($app, is_array($eingaben) ? $eingaben : []);
    TipGoals::push($app);

    return $zurueck(['notice' => translate('sl_tip.saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Das Aussehen
// -------------------------------------------------------------------
$zurueckAussehen = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/appearance') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/tips/appearance', static function (Request $request) use ($app, $plugin): Response {
    $html = TipGoals::html($app);

    return Response::html($app->view->from($plugin->directory . '/views')->render('appearance', [
        'title'    => translate('sl_tip.appearance'),
        'active'   => 'display/goals',
        'html'     => $html,
        'css'      => TipGoals::css($app),
        'custom'   => TipGoals::isCustom($app),
        // Beim OEFFNEN schon melden, was fehlt - nicht erst beim
        // Speichern. Wer eine kaputte Fassung stehen hat, soll sie
        // sehen, ohne sie vorher noch einmal abschicken zu muessen.
        'missing'  => Goals::missing($html, TipGoals::REQUIRED_BINDINGS, TipGoals::REQUIRED_FILLS),
        'required' => [
            'tip_title'   => translate('sl_tip.bind.title'),
            'tip_current' => translate('sl_tip.bind.current'),
            'tip_goal'    => translate('sl_tip.bind.goal'),
        ],
        'fills'    => ['tip' => translate('sl_tip.bind.fill')],
        'canEdit'  => permission('SlTipGoals.Global.Edit'),
        'csrf'     => $app->auth->csrfToken(),
        'notice'   => (string) $request->get('notice'),
        'error'    => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'SlTipGoals.Global.View']);

$router->post('/display/goals/tips/appearance', static function (Request $request) use ($app, $zurueckAussehen): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckAussehen(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('SlTipGoals.Global.Edit')) {
        return $zurueckAussehen(['error' => translate('common.error.no_permission')]);
    }

    if ($request->input('action') === 'reset') {
        TipGoals::resetAppearance($app);

        return $zurueckAussehen(['notice' => translate('sl_tip.reset_done')]);
    }

    $fehlend = TipGoals::saveAppearance($app, $request->input('html'), $request->input('css'));

    // Gespeichert ist es in jedem Fall - gemeldet wird trotzdem, was
    // fehlt. Ein halb fertiges Geruest soll man weiterschreiben
    // koennen.
    return $zurueckAussehen($fehlend === []
        ? ['notice' => translate('sl_tip.appearance_saved')]
        : ['error' => translate('sl_tip.missing_hint') . ' ' . implode(', ', $fehlend)]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Die Zugangsdaten
// -------------------------------------------------------------------
$zurueckEinst = static function (array $query = []) use ($app): Response {
    return Response::redirect(
        $app->url('/display/goals/tips/settings') . ($query === [] ? '' : '?' . http_build_query($query))
    );
};

$router->get('/display/goals/tips/settings', static function (Request $request) use ($app, $plugin): Response {
    return Response::html($app->view->from($plugin->directory . '/views')->render('settings', [
        'title'     => translate('sl_tip.name'),
        'active'    => 'display/goals',
        'hasToken'  => Source::hasToken($app),
        'ready'     => Source::ready($app),
        'lastError' => $app->settings->string('last_error', '', TipGoals::scope()),
        'pollSeconds' => TipGoals::POLL_SECONDS,
        'canEdit'   => permission('SlTipGoals.Global.Edit'),
        'csrf'      => $app->auth->csrfToken(),
        'notice'    => (string) $request->get('notice'),
        'error'     => (string) $request->get('error'),
    ]));
}, ['auth' => true, 'permission' => 'SlTipGoals.Global.View']);

$router->post('/display/goals/tips/settings', static function (Request $request) use ($app, $zurueckEinst): Response {
    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueckEinst(['error' => translate('common.error.form_expired')]);
    }

    if (!permission('SlTipGoals.Global.Edit')) {
        return $zurueckEinst(['error' => translate('common.error.no_permission')]);
    }

    // Ein leeres Feld loescht das Token NICHT: es wird nie wieder
    // angezeigt, und ein Formular, das man zum Aendern der Kanal-ID
    // abschickt, darf nicht nebenbei den Zugang wegwerfen. Zum
    // Loeschen gibt es einen eigenen Knopf.
    $token = $request->input('token');
    if ($token !== '') {
        Source::setToken($app, $token);
    }

    if ($request->input('action') === 'forget') {
        Source::setToken($app, '');
    }

    // Was zuletzt schiefging, gilt nach neuen Zugangsdaten nicht mehr.
    $app->settings->set('last_error', '', TipGoals::scope());

    return $zurueckEinst(['notice' => translate('sl_tip.settings_saved')]);
}, ['auth' => true]);

// -------------------------------------------------------------------
//  Der Reiter auf der Alerts-Seite
// -------------------------------------------------------------------
//
//  Er fehlte, und damit fehlte jede Einstellmoeglichkeit: der Alert
//  feuerte mit einem festen Vorgabetext, ohne Video, ohne Ton, ohne
//  Dauer. Im alten System hiess dieser Reiter "Spende".
$hooks->on('alerts.tabs', static function (array $tabs) use ($app, $plugin): array {
    if (!permission('SlTipGoals.Global.View')) {
        return $tabs;
    }

    $tabs['tips-streamlabs'] = [
        'label' => translate('sl_tip.alert.tab'),
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
                    'message' => translate('sl_tip.alert.test_message'),
                ]),
                'target'          => $app->url('/display/alerts/tips-streamlabs'),
                'canEdit'         => permission('SlTipGoals.Global.Edit'),
                'canToggle'       => permission('SlTipGoals.Global.Edit'),
                'canTest'         => permission('SlTipGoals.Global.Edit'),
                'defaultDuration' => Alerts::DEFAULT_DURATION,
                'maxText'         => TipAlert::MAX_TEXT,
                'maxTiers'        => TipAlert::MAX_TIERS,
                'csrf'            => $app->auth->csrfToken(),
            ], null),
    ];

    return $tabs;
});

$router->post('/display/alerts/tips-streamlabs', static function (Request $request) use ($app): Response {
    $zurueck = static function (?string $notice, ?string $error = null) use ($app): Response {
        $query = array_filter([
            'notice' => $notice,
            'error'  => $error,
        ], static fn (?string $wert): bool => $wert !== null);

        return Response::redirect(
            $app->url('/display/alerts/tips-streamlabs')
            . ($query === [] ? '' : '?' . http_build_query($query))
        );
    };

    if (!$app->auth->checkCsrf($request->input('csrf'))) {
        return $zurueck(null, translate('common.error.form_expired'));
    }

    if (!permission('SlTipGoals.Global.Edit')) {
        return $zurueck(null, translate('common.error.no_permission'));
    }

    switch ($request->input('action')) {
        case 'toggle':
            $an = !TipAlert::config($app)['enabled'];
            TipAlert::setEnabled($app, $an);

            return $zurueck($an
                ? translate('sl_tip.alert.turned_on')
                : translate('sl_tip.alert.turned_off'));

        case 'test':
            // Der Hauptschalter von Alerts steht darueber. Ohne diesen
            // Hinweis sucht man den Fehler beim Text.
            if (!Alerts::enabled($app)) {
                return $zurueck(null, translate('sl_tip.alert.test_while_all_off'));
            }

            if (!TipAlert::config($app)['enabled']) {
                return $zurueck(null, translate('sl_tip.alert.test_while_off'));
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
                ? $zurueck(translate('sl_tip.alert.test_sent'))
                : $zurueck(null, translate('sl_tip.alert.test_failed'));

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

            return $zurueck(translate('sl_tip.alert.saved'));
    }

    return $zurueck(null, translate('common.error.unknown_action'));
}, ['auth' => true]);
