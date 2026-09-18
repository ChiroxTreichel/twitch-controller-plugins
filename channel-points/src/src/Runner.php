<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChannelPoints;

use TwitchController\Core\App;

/**
 * Das Schalten im Hintergrund.
 *
 * Einmal je Takt: nachsehen, was der Stream gerade zeigt, und jede
 * Belohnung mit Bedingungen auf den Stand bringen, der dazu gehoert.
 *
 * Geschickt wird nur bei einem Unterschied. Das ist keine
 * Sparmassnahme, sondern Bedingung: ein PATCH je Takt je Belohnung
 * waere bei zwanzig Belohnungen ein Dauerfeuer auf Twitch - und der
 * Eintrag "geaendert" im Dashboard staende dort im Minutentakt, ohne
 * dass sich etwas geaendert haette.
 */
final class Runner
{
    public function __construct(private readonly App $app)
    {
    }

    public function tick(): void
    {
        if (!Rewards::enabled($this->app)) {
            return;
        }

        $alle = Rewards::all($this->app);
        $gruppen = Groups::all($this->app);

        /*
         * Erst die billige Frage: gibt es ueberhaupt etwas zu tun?
         * Ohne das wuerde jede Installation, die nur Belohnungen
         * verwaltet und keine Bedingungen nutzt, alle fuenf Minuten
         * den Streamstatus abfragen - fuer nichts.
         *
         * Eine Bedingung kann auch an einer Gruppe haengen: dann hat
         * die Belohnung selbst keine, wird aber trotzdem geschaltet.
         */
        $redetMit = static function (array $b) use ($gruppen): bool {
            if (empty($b['manageable'])) {
                return false;
            }

            if (Conditions::automatic($b)) {
                return true;
            }

            foreach (Groups::forReward($gruppen, (string) $b['id']) as $gruppe) {
                if (!empty($gruppe['enabled']) && Conditions::automatic($gruppe)) {
                    return true;
                }
            }

            return false;
        };

        if (array_filter($alle, $redetMit) === []) {
            return;
        }

        $stream = (new Stream($this->app))->refreshed();

        if (!$stream['live']) {
            return;
        }

        $api = new RewardApi($this->app);

        if (!$api->allowed()) {
            return;
        }

        $geaendert = false;

        foreach ($alle as $stelle => $belohnung) {
            $soll = Conditions::decide($belohnung, $stream, $gruppen);

            if ($soll === null || $soll === !empty($belohnung['is_enabled'])) {
                continue;
            }

            if (!$api->setEnabled((string) $belohnung['id'], $soll)) {
                $this->app->log(sprintf(
                    'Kanalpunkte: "%s" liess sich nicht %s: %s',
                    (string) $belohnung['title'],
                    $soll ? 'einschalten' : 'ausschalten',
                    $api->error()
                ));

                continue;
            }

            $alle[$stelle]['is_enabled'] = $soll;
            $geaendert = true;

            $this->app->log(sprintf(
                'Kanalpunkte: "%s" %s (%s)',
                (string) $belohnung['title'],
                $soll ? 'eingeschaltet' : 'ausgeschaltet',
                $stream['game'] !== '' ? $stream['game'] : $stream['title']
            ));
        }

        if ($geaendert) {
            Rewards::store($this->app, $alle);
        }
    }
}
