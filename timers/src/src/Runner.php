<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Timers;

use TwitchController\Core\App;

/**
 * Der Betrieb: Chatzeilen zaehlen und faellige Timer posten.
 *
 * Gezaehlt wird im Webhook-Request (core.chat.message), gepostet im
 * Hintergrundprozess (cron.tick). Beides absichtlich getrennt: eine
 * Chatnachricht darf nicht warten, bis ein Timer seine Runde gedreht
 * hat, und Twitch wartet auf unsere Antwort.
 */
final class Runner
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * Eine Chatzeile zaehlen.
     *
     * Nur waehrend des Streams: "Min. Zeilen" meint Betrieb im Chat,
     * und was nachts um drei geschrieben wird, soll den Timer am
     * naechsten Abend nicht sofort ausloesen.
     */
    public function countLine(): void
    {
        if (!Timers::enabled($this->app)) {
            return;
        }

        if (!(new Stream($this->app))->state()['live']) {
            return;
        }

        $timer = Timers::all($this->app);
        if ($timer === []) {
            return;
        }

        State::save($this->app, State::countLine(State::load($this->app), $timer));
    }

    /**
     * Ein Takt: hoechstens einen Timer posten.
     *
     * Hoechstens einen, und dazu der Mindestabstand aus
     * Timers::GLOBAL_THROTTLE - sonst stehen nach einer langen Pause
     * drei Werbeblöcke untereinander im Chat.
     *
     * @return string Titel des geposteten Timers, leer wenn keiner dran war
     */
    public function tick(): string
    {
        if (!Timers::enabled($this->app)) {
            return '';
        }

        $timer = Timers::all($this->app);
        if ($timer === []) {
            return '';
        }

        $stream = (new Stream($this->app))->refreshed();
        $stand = $this->syncSession($stream);

        if (!$stream['live']) {
            return '';
        }

        $jetzt = time();
        if ($jetzt - $stand['last_post'] < Timers::GLOBAL_THROTTLE) {
            return '';
        }

        foreach ($timer as $eintrag) {
            $id = (string) $eintrag['id'];
            $einzeln = State::of($stand, $id);

            if (!Timers::isDue($eintrag, $einzeln, $stream, $jetzt)) {
                continue;
            }

            $text = Timers::messageAt($eintrag, $einzeln['message_index']);
            if ($text === '') {
                continue;
            }

            $ergebnis = $this->app->chat->send($text);

            if (!$ergebnis['ok']) {
                $this->app->log(sprintf(
                    'Timer: "%s" konnte nicht posten: %s',
                    (string) $eintrag['title'],
                    $ergebnis['error']
                ));

                // Nicht weiterlaufen: geht das Senden gerade nicht,
                // geht es fuer den naechsten Timer auch nicht, und drei
                // Fehlversuche je Takt fuellen nur das Log.
                return '';
            }

            $this->app->log(sprintf(
                'Timer: "%s" gepostet (Nachricht %d).',
                (string) $eintrag['title'],
                $einzeln['message_index'] % max(1, count(Timers::activeMessages($eintrag))) + 1
            ));

            $stand = State::set($stand, $id, [
                'lines'          => 0,
                'last_posted_at' => $jetzt,
                'message_index'  => $einzeln['message_index'] + 1,
            ]);
            $stand['last_post'] = $jetzt;

            State::save($this->app, $stand);

            return (string) $eintrag['title'];
        }

        // Der Sitzungswechsel kann den Stand veraendert haben, auch
        // wenn nichts gepostet wurde.
        State::save($this->app, $stand);

        return '';
    }

    /**
     * Faengt ein neuer Stream an, faengt auch der Stand neu an.
     *
     * @param array{live: bool, started_at: int, title: string, game: string, checked_at: int} $stream
     * @return array{session: int, last_post: int, timers: array<string, array<string, int>>}
     */
    private function syncSession(array $stream): array
    {
        $stand = State::load($this->app);
        $start = $stream['live'] ? $stream['started_at'] : 0;

        if ($stand['session'] === $start) {
            return $stand;
        }

        return State::newSession($stand, $start);
    }

    /**
     * Die Antwort auf "!titel", wenn der Timer als Befehl erlaubt ist.
     *
     * Absichtlich die ERSTE Nachricht und nicht die naechste aus der
     * Reihe: wer den Befehl tippt, will die Auskunft - und die soll
     * nicht davon abhaengen, wie oft der Timer heute schon gelaufen
     * ist. So war es im alten System auch.
     */
    public function answerFor(string $name): string
    {
        if (!Timers::enabled($this->app)) {
            return '';
        }

        $timer = Timers::commands($this->app)[$name] ?? null;

        return $timer === null ? '' : Timers::messageAt($timer, 0);
    }
}
