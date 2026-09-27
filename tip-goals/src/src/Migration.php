<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TipGoals;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;

/**
 * ===================================================================
 *  Umzug aus "Tip-Goals - PayPal" 1.x
 * ===================================================================
 *
 * Bis 1.x war alles ein Plugin: Ziele, Spendenseite, Rechtstexte,
 * Alert UND PayPal. Ab jetzt sind es zwei - dieses hier und PayPal als
 * einer von mehreren Zahlungsanbietern. Was nicht PayPal ist, zieht
 * hierher um: die Ziele mit ihrem Stand, die Spenden, die Einstellungen
 * und die Rechte.
 *
 * Zwei Stellen rufen das auf, und das ist Absicht: die install.php
 * DIESES Plugins und die von PayPal 2.x. Welche zuerst laeuft, haengt
 * davon ab, was zuerst aktualisiert oder installiert wird - der Umzug
 * passiert beim zweiten, und der erste findet schlicht noch nicht
 * alles vor.
 *
 * Er passiert genau einmal und nur, wenn alles da ist:
 *
 *   die alten Tabellen gibt es noch
 *   die neuen gibt es schon - und sie sind LEER
 *   PayPal liegt nicht mehr in 1.x auf der Platte - das liefe sonst
 *   weiter auf den Tabellen, die hier gleich verschwinden
 *
 * Sind die neuen Tabellen nicht leer, hat jemand schon Ziele angelegt.
 * Dann wird NICHT zusammengefuehrt: die Nummern der Ziele stiessen
 * sich, und eine Spende landete auf dem falschen. Die alten Tabellen
 * bleiben dann stehen, und eine Zeile im Log sagt es.
 */
final class Migration
{
    private const OLD_SLUG = 'paypal-tip-goals';

    /**
     * Was aus dem alten Bereich hierher gehoert.
     *
     * Alles andere bleibt, wo es ist: Zugangsdaten, Modus und
     * Gebuehrensaetze gehoeren zu PayPal und nicht zur Seite.
     *
     * @var list<string>
     */
    private const SETTINGS = [
        'alert', 'brand', 'presets', 'min_amount', 'default_amount',
        'legal_impressum', 'legal_datenschutz', 'legal_agb',
        'html', 'css', 'updated_at', 'cleaned_at',
    ];

    public static function fromPaypal(App $app): void
    {
        $db = $app->db;

        $alt = (int) $db->value("SELECT (to_regclass('public.pp_tip_goals') IS NOT NULL
                                      AND to_regclass('public.pp_donation_intents') IS NOT NULL)::int") === 1;
        $neu = (int) $db->value("SELECT (to_regclass('public.tip_goals') IS NOT NULL
                                      AND to_regclass('public.tip_donations') IS NOT NULL)::int") === 1;

        if (!$alt || !$neu) {
            return;
        }

        $paypal = $app->plugins->manifest(self::OLD_SLUG);
        if ($paypal !== null && version_compare($paypal->version, '2.0.0', '<')) {
            return;
        }

        $belegt = (int) $db->value('SELECT (SELECT count(*) FROM tip_goals) + (SELECT count(*) FROM tip_donations)');
        if ($belegt > 0) {
            $app->log(TipGoals::SLUG . ': Umzug aus ' . self::OLD_SLUG . ' uebersprungen - hier stehen schon '
                . 'Ziele oder Spenden. Die alten Tabellen pp_tip_goals und pp_donation_intents bleiben stehen.');

            return;
        }

        try {
            $db->transaction(static function () use ($db): void {
                // Mit ihren Nummern: jede Spende zeigt auf ein Ziel.
                $db->run('INSERT INTO tip_goals (id, position, title, current, target, created_at)
                          SELECT id, position, title, current, target, created_at FROM pp_tip_goals');

                $db->run("SELECT setval(pg_get_serial_sequence('tip_goals', 'id'),
                                        COALESCE((SELECT max(id) FROM tip_goals), 0) + 1, false)");

                // "approved" war PayPals Wort fuer "der Spender ist beim
                // Anbieter" - hier heisst das allgemein "pending".
                $db->run("INSERT INTO tip_donations
                              (token, provider, twitch_user_id, twitch_login, twitch_display_name,
                               message, anonymous, amount_eur, goal_id, provider_ref, provider_tx,
                               status, created_at, expires_at, captured_at)
                          SELECT token, 'paypal', twitch_user_id, twitch_login, twitch_display_name,
                                 message, anonymous, amount_eur, goal_id, paypal_order_id, paypal_capture_id,
                                 CASE status WHEN 'approved' THEN 'pending' ELSE status END,
                                 created_at, expires_at, captured_at
                            FROM pp_donation_intents");

                $alterBereich = Settings::pluginScope(self::OLD_SLUG);
                $neuerBereich = Settings::pluginScope(TipGoals::SLUG);

                $schluessel = [];
                $werte = ['alt' => $alterBereich, 'neu' => $neuerBereich];
                foreach (self::SETTINGS as $i => $key) {
                    $schluessel[] = ':k' . $i;
                    $werte['k' . $i] = $key;
                }
                $liste = implode(', ', $schluessel);

                $db->run(
                    'INSERT INTO settings (scope, key, value)
                     SELECT :neu, key, value FROM settings WHERE scope = :alt AND key IN (' . $liste . ')
                     ON CONFLICT (scope, key) DO UPDATE SET value = EXCLUDED.value, updated_at = now()',
                    $werte
                );

                unset($werte['neu']);
                $db->run(
                    "DELETE FROM settings WHERE scope = :alt AND (key IN (" . $liste . ") OR key = 'last_error')",
                    $werte
                );

                // Die Rechte hiessen PaypalTipGoals.*. Wer sie einzeln
                // vergeben bekam, bekommt die neuen dazu - die alten
                // bleiben stehen und schaden nicht.
                $db->run("
                    UPDATE users
                       SET permissions = permissions || COALESCE((
                               SELECT jsonb_agg(replace(alt, 'PaypalTipGoals.', 'TipGoals.'))
                                 FROM jsonb_array_elements_text(permissions) AS alt
                                WHERE alt LIKE 'PaypalTipGoals.%'
                                  AND NOT jsonb_exists(permissions, replace(alt, 'PaypalTipGoals.', 'TipGoals.'))
                           ), '[]'::jsonb)
                     WHERE permissions::text LIKE '%PaypalTipGoals.%'
                ");

                $db->run('DROP TABLE pp_donation_intents');
                $db->run('DROP TABLE pp_tip_goals');
            });
        } catch (Throwable $e) {
            $app->log(TipGoals::SLUG . ': Umzug aus ' . self::OLD_SLUG . ' gescheitert: ' . $e->getMessage());

            throw $e;
        }

        // Der Zwischenspeicher kennt noch den alten Stand.
        $app->settings->flush();

        $app->log(TipGoals::SLUG . ': Ziele, Spenden und Einstellungen aus ' . self::OLD_SLUG . ' uebernommen.');
    }
}
