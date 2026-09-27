<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TipGoals;

use TwitchController\Core\App;
use TwitchController\Core\Http\Response;

/**
 * ===================================================================
 *  Die oeffentlichen Seiten unter /tips
 * ===================================================================
 *
 * Hier und nicht als Closure in plugin.php, weil auch die Anbieter sie
 * brauchen: die Rueckkehr von PayPal endet auf derselben Dankeseite wie
 * die jedes anderen Anbieters, im selben Rahmen, mit demselben Fuss.
 *
 * Meldungen gehen als CODE in die Adresse und nicht als Text. Frueher
 * stand dort der Satz selbst - und damit liess sich ein Link bauen, der
 * auf der echten Spendenseite "Bitte stattdessen an IBAN ... ueberweisen"
 * anzeigt. Jetzt kommt nur, was in message() steht.
 */
final class PublicPage
{
    /** Eine Seite rendern. Die Vorlage liegt unter views/public/. */
    public static function render(App $app, string $vorlage, array $daten = [], int $status = 200): Response
    {
        // Die Wurzel ist /views und NICHT /views/public - obwohl alle
        // Seiten hier darunter liegen.
        //
        // Denn die Seiten holen sich ihren Rahmen selbst, mit
        // $view->render('public/_head'). Waere die Wurzel schon
        // /views/public, suchte der das unter views/public/public/_head -
        // und genau das ist beim ersten Anlauf passiert.
        return Response::html(
            $app->view->from(dirname(__DIR__) . '/views')->render('public/' . $vorlage, $daten + [
                'brand'    => TipGoals::brand($app),
                'identity' => Donations::identity($app),
                'legal'    => Legal::available($app),
                'notice'   => '',
                'error'    => '',
            ], null),
            $status
        );
    }

    /**
     * Die Seite nach der Rueckkehr vom Anbieter.
     *
     * Dieselbe fuer geglueckt und gescheitert, nur mit anderem Text:
     * wer hier landet, hat eine Frage - "ist mein Geld angekommen?" -
     * und die wird in der Ueberschrift beantwortet.
     */
    public static function done(App $app, bool $ok, string $heading, string $body): Response
    {
        return self::render($app, 'done', [
            'ok'      => $ok,
            'heading' => $heading,
            'body'    => $body,
        ]);
    }

    /** Die geschlossene Seite. */
    public static function closed(App $app, int $status = 503): Response
    {
        return self::render($app, 'closed', [], $status);
    }

    /**
     * Zurueck auf /tips, mit einer Meldung.
     *
     * @param string $notice ein Schluessel aus message(), sonst leer
     * @param string $error  dito
     */
    public static function back(App $app, string $notice = '', string $error = ''): Response
    {
        $query = array_filter(['notice' => $notice, 'error' => $error], static fn (string $wert): bool => $wert !== '');

        return Response::redirect($app->url('/tips') . ($query === [] ? '' : '?' . http_build_query($query)));
    }

    /**
     * Der Text zu einem Code aus der Adresse.
     *
     * Was hier nicht steht, wird nicht angezeigt. Ausgeschrieben und
     * nicht zusammengebaut: der Sprachpruefer liest nur literale
     * Schluessel.
     */
    public static function message(App $app, string $code): string
    {
        return match ($code) {
            'cancelled'      => translate('tips.cancelled'),
            'form_expired'   => translate('common.error.form_expired'),
            'not_signed_in'  => translate('tips.error.not_signed_in'),
            'no_identity'    => translate('tips.error.no_identity'),
            'no_provider'    => translate('tips.error.no_provider'),
            'terms'          => translate('tips.error.terms'),
            'goal_gone'      => translate('tips.error.goal_gone'),
            'start_failed'   => translate('tips.error.start_failed'),
            'too_small'      => translate('tips.error.too_small', [
                'min' => number_format(TipGoals::minAmount($app), 2, ',', '.'),
            ]),
            'too_large'      => translate('tips.error.too_large', [
                'max' => number_format(TipGoals::MAX_AMOUNT, 2, ',', '.'),
            ]),
            default          => '',
        };
    }
}
