<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Giveaway;

use TwitchController\Core\App;
use TwitchController\Core\Overlay\Bus;

/**
 * Die Ziehung.
 *
 * Zwei Phasen:
 *
 *   collect  Tickets sammeln. Der Befehl vergibt welche, die Werbung
 *            laeuft.
 *   draw     Ziehen. Die Teilnahme ist zu, das Rad steht im Overlay,
 *            und die Preise werden von unten nach oben vergeben.
 *
 * Ein Zug laeuft in drei Schritten, und nur der erste ist ein Klick:
 *
 *   next()      Gewinner auslosen, das Rad drehen lassen
 *   announce()  wenn das Rad steht: im Chat ansagen, Preis abhaken,
 *               nach einer Pause das Rad fuer den naechsten Preis
 *   (Pause)     dann erst wird der Knopf zum naechsten Preis
 *
 * Ausgelost wird HIER und nicht im Overlay. Das Rad zeigt nur, was
 * feststeht - sonst haengt das Ergebnis an einer Browserquelle, die
 * vielleicht gar nicht offen ist, oder an zweien, die verschieden
 * ausgehen.
 *
 * announce() ruft das Skript der Verwaltungsseite, sobald das Rad
 * steht. Ist die Seite nicht offen, holt der Worker es nach (siehe
 * cron.tick) - eine Ansage bleibt also nie liegen, sie kommt dann nur
 * ein paar Sekunden spaeter.
 */
final class Draw
{
    public const COLLECT = 'collect';
    public const DRAW = 'draw';

    /** So lange dreht das Rad. Muss zur Animation in overlay.js passen. */
    public const SPIN_MS = 9000;

    /** Der Gewinner steht da, bevor der Chat es erfaehrt. */
    public const REVEAL_MS = 1500;

    /** Nach der Ansage, bevor es mit dem naechsten Preis weitergeht. */
    public const PAUSE_MS = 5000;

    public function __construct(private readonly App $app)
    {
    }

    // -----------------------------------------------------------------
    //  Stand
    // -----------------------------------------------------------------

    /**
     * @return array{phase: string, prizes: list<string>, next: int, pending: array{id: int, announce_at: float}|null, wait_until: float}
     */
    public function load(): array
    {
        $roh = $this->app->settings->get('draw', null, Giveaway::scope());
        $roh = is_array($roh) ? $roh : [];

        $offen = is_array($roh['pending'] ?? null)
            ? ['id' => (int) ($roh['pending']['id'] ?? 0), 'announce_at' => (float) ($roh['pending']['announce_at'] ?? 0)]
            : null;

        return [
            'phase'      => ($roh['phase'] ?? '') === self::DRAW ? self::DRAW : self::COLLECT,
            'prizes'     => array_values(array_map('strval', array_filter((array) ($roh['prizes'] ?? []), 'is_scalar'))),
            'next'       => (int) ($roh['next'] ?? -1),
            'pending'    => $offen !== null && $offen['id'] > 0 ? $offen : null,
            'wait_until' => (float) ($roh['wait_until'] ?? 0),
        ];
    }

    public function phase(): string
    {
        return $this->load()['phase'];
    }

    // -----------------------------------------------------------------
    //  Die Schritte
    // -----------------------------------------------------------------

    /**
     * "Gewinner ziehen": Teilnahme zu, Rad ins Overlay.
     *
     * Die Preise werden dabei festgehalten. Wer waehrend der Ziehung
     * die Liste umsortiert, soll nicht mittendrin einen anderen Preis
     * verlosen als den, der auf dem Knopf stand.
     *
     * @return string leer bei Erfolg, sonst der Grund
     */
    public function start(): string
    {
        return $this->locked(function (array $stand): string {
            if (!Giveaway::enabled($this->app)) {
                return translate('giveaway.error.off');
            }

            if ($stand['phase'] === self::DRAW) {
                return translate('giveaway.error.already_drawing');
            }

            $preise = Giveaway::prizes($this->app);
            if ($preise === []) {
                return translate('giveaway.error.no_prizes');
            }

            $leute = (new Tickets($this->app))->entries();
            if ($leute === []) {
                return translate('giveaway.error.no_entries');
            }

            // Die Gewinner des letzten Giveaways standen bis hierher zum
            // Nachsehen da. Jetzt beginnt ein neues.
            $this->app->db->run('DELETE FROM giveaway_winners');

            $this->save([
                'phase'      => self::DRAW,
                'prizes'     => $preise,
                'next'       => count($preise) - 1,
                'pending'    => null,
                'wait_until' => 0.0,
            ]);

            $this->send(['kind' => 'show'] + self::wheel($leute, $preise[count($preise) - 1]));

            return '';
        });
    }

    /**
     * "Gewinner von … ziehen": auslosen, festhalten, Rad drehen.
     *
     * Das Ticket des Gewinners ist danach weg. Darf er nur einmal
     * gewinnen, sind es gleich alle - dann steht er beim naechsten
     * Preis nicht mehr auf dem Rad.
     *
     * @return string leer bei Erfolg, sonst der Grund
     */
    public function next(): string
    {
        return $this->locked(function (array $stand): string {
            if (!Giveaway::enabled($this->app)) {
                return translate('giveaway.error.off');
            }

            if ($stand['phase'] !== self::DRAW) {
                return translate('giveaway.error.not_drawing');
            }

            if ($stand['pending'] !== null) {
                return translate('giveaway.error.spinning');
            }

            $preis = $stand['prizes'][$stand['next']] ?? null;
            if ($preis === null) {
                return translate('giveaway.error.all_drawn');
            }

            $leute = (new Tickets($this->app))->entries();
            if ($leute === []) {
                return translate('giveaway.error.nobody_left');
            }

            $platz = self::pick(array_column($leute, 'tickets'));
            $gewinner = $leute[$platz];

            $id = (int) $this->app->db->value(
                'INSERT INTO giveaway_winners (position, prize, twitch_id, login, display_name, tickets)
                 VALUES (CAST(:position AS INTEGER), :prize, :id, :login, :name, CAST(:tickets AS INTEGER))
                 RETURNING id',
                [
                    'position' => (string) $stand['next'],
                    'prize'    => $preis,
                    'id'       => $gewinner['twitch_id'],
                    'login'    => $gewinner['login'],
                    'name'     => $gewinner['display_name'],
                    'tickets'  => (string) $gewinner['tickets'],
                ]
            );

            $this->app->db->run(
                Giveaway::config($this->app)['multiple_wins']
                    ? 'UPDATE giveaway_entries SET tickets = GREATEST(tickets - 1, 0) WHERE twitch_id = :id'
                    : 'UPDATE giveaway_entries SET tickets = 0 WHERE twitch_id = :id',
                ['id' => $gewinner['twitch_id']]
            );

            $stand['pending'] = [
                'id'          => $id,
                'announce_at' => microtime(true) + (self::SPIN_MS + self::REVEAL_MS) / 1000,
            ];
            $this->save($stand);

            // Das Rad dreht mit den Leuten VOR dem Zug - der Gewinner
            // muss ja draufstehen, wenn der Zeiger auf ihm landet.
            $this->send(['kind' => 'spin'] + self::wheel($leute, $preis) + [
                'winner'   => $platz,
                'duration' => self::SPIN_MS,
                'banner'   => translate('giveaway.overlay.winner', [
                    'name'  => $gewinner['display_name'],
                    'prize' => $preis,
                ]),
            ]);

            return '';
        });
    }

    /**
     * Das Rad steht: im Chat ansagen und zum naechsten Preis.
     *
     * Darf beliebig oft gerufen werden - von der Seite, vom Worker,
     * von beiden zugleich. Angesagt wird genau einmal: das UPDATE auf
     * announced_at gelingt nur dem Ersten.
     *
     * @param float $spaeter Sekunden Nachsicht nach dem geplanten
     *                       Zeitpunkt. Der Worker wartet etwas laenger,
     *                       damit die offene Seite zuerst darf.
     */
    public function announce(float $spaeter = 0.0): bool
    {
        return $this->locked(function (array $stand) use ($spaeter): bool {
            $offen = $stand['pending'];

            // Eine halbe Sekunde Spielraum: der Browser zaehlt seine
            // Wartezeit selbst, und ein Hauch zu frueh soll nicht
            // heissen, dass erst der Worker ansagt.
            if ($offen === null || microtime(true) + 0.5 < $offen['announce_at'] + $spaeter) {
                return false;
            }

            $zeile = $this->app->db->first(
                'UPDATE giveaway_winners SET announced_at = now()
                  WHERE id = CAST(:id AS BIGINT) AND announced_at IS NULL
                  RETURNING login, prize',
                ['id' => (string) $offen['id']]
            );

            $stand['pending'] = null;
            $stand['next']--;
            $stand['wait_until'] = microtime(true) + self::PAUSE_MS / 1000;
            $this->save($stand);

            if ($zeile !== null) {
                $text = Giveaway::fill(Giveaway::config($this->app)['announce'], [
                    'username' => Giveaway::mention((string) $zeile['login']),
                    'prize'    => (string) $zeile['prize'],
                ]);

                if ($text !== '') {
                    $ergebnis = $this->app->chat->send($text);

                    if (!$ergebnis['ok']) {
                        $this->app->log('Giveaway: Ansage ging nicht raus - ' . $ergebnis['error']);
                    }
                }
            }

            // Das Rad fuer den naechsten Preis. Mit Verzoegerung: so
            // lange bleibt der Gewinner gross im Bild.
            $leute = (new Tickets($this->app))->entries();
            $preis = $stand['prizes'][$stand['next']] ?? null;

            if ($preis !== null && $leute !== []) {
                $this->send(['kind' => 'show', 'delay' => self::PAUSE_MS] + self::wheel($leute, $preis));
            }

            return true;
        });
    }

    /**
     * "Giveaway beenden": Tickets weg, Rad weg, Schalter aus.
     *
     * Aus, weil sonst der Befehl sofort wieder Tickets fuer ein
     * Giveaway verteilt, das es noch gar nicht gibt. Die Gewinner
     * bleiben zum Nachsehen stehen.
     */
    public function end(): void
    {
        $this->locked(function (): void {
            (new Tickets($this->app))->clear();

            $this->save([
                'phase'      => self::COLLECT,
                'prizes'     => [],
                'next'       => -1,
                'pending'    => null,
                'wait_until' => 0.0,
            ]);

            Giveaway::setEnabled($this->app, false);
            $this->send(['kind' => 'hide']);
        });
    }

    /**
     * Nach dem Schalter im Menue: was das Overlay jetzt zeigen soll.
     *
     * Aus heisst auch: kein Rad im Bild. Wieder an mitten in einer
     * Ziehung bringt es zurueck, wie es war.
     */
    public function refreshOverlay(): void
    {
        $zustand = $this->overlayState();

        $this->send($zustand['visible'] ? ['kind' => 'show'] + $zustand : ['kind' => 'hide']);
    }

    // -----------------------------------------------------------------
    //  Was Seite und Overlay sehen
    // -----------------------------------------------------------------

    /**
     * Der Stand fuer die Verwaltungsseite.
     *
     * @return array<string, mixed>
     */
    public function view(): array
    {
        $stand = $this->load();
        $jetzt = microtime(true);
        $leute = (new Tickets($this->app))->entries();
        $offen = $stand['pending'];

        $schritt = match (true) {
            $stand['phase'] === self::COLLECT                    => 'collect',
            $offen !== null                                      => 'spinning',
            $jetzt < $stand['wait_until']                        => 'pause',
            $stand['next'] < 0                                   => 'done',
            $leute === []                                        => 'empty',
            default                                              => 'ready',
        };

        $ansageIn = $offen !== null ? max(0, (int) round(($offen['announce_at'] - $jetzt) * 1000)) : null;

        return [
            'phase'      => $stand['phase'],
            'step'       => $schritt,
            'nextPrize'  => (string) ($stand['prizes'][$stand['next']] ?? ''),
            'left'       => max(0, $stand['next'] + 1),
            'entries'    => $leute,
            'tickets'    => array_sum(array_column($leute, 'tickets')),
            'winners'    => $this->winners(),
            // Fuer das Skript der Seite: wann ansagen, wann neu laden.
            'announceIn' => $ansageIn,
            'reloadIn'   => match ($schritt) {
                'spinning' => (int) $ansageIn + self::PAUSE_MS + 300,
                'pause'    => max(0, (int) round(($stand['wait_until'] - $jetzt) * 1000)) + 300,
                default    => null,
            },
        ];
    }

    /**
     * Der Stand fuer eine Browserquelle, die gerade erst geladen wurde.
     *
     * Die Leitung ins Overlay spielt nichts nach - ohne das stuende nach
     * einem Neuladen mitten in der Ziehung kein Rad mehr im Bild.
     *
     * @return array<string, mixed>
     */
    public function overlayState(): array
    {
        $stand = $this->load();

        if (!Giveaway::enabled($this->app) || $stand['phase'] !== self::DRAW) {
            return ['visible' => false];
        }

        $leute = (new Tickets($this->app))->entries();
        $preis = (string) ($stand['prizes'][$stand['next']] ?? '');

        // Laeuft gerade ein Zug, oder ist alles vergeben: den letzten
        // Gewinner zeigen. Sonst stuende ein leeres Rad da, und man
        // wuesste nicht, warum.
        $banner = '';
        if ($stand['pending'] !== null || $preis === '' || $leute === []) {
            $letzter = $this->app->db->first('SELECT display_name, prize FROM giveaway_winners ORDER BY id DESC LIMIT 1');

            if ($letzter !== null) {
                $banner = translate('giveaway.overlay.winner', [
                    'name'  => (string) $letzter['display_name'],
                    'prize' => (string) $letzter['prize'],
                ]);
            }
        }

        return ['visible' => true, 'banner' => $banner] + self::wheel($leute, $preis);
    }

    /**
     * Die Gewinner dieses (oder des letzten) Giveaways, in Zugfolge.
     *
     * @return list<array<string, mixed>>
     */
    public function winners(): array
    {
        return $this->app->db->all(
            'SELECT position, prize, login, display_name, tickets, announced_at
               FROM giveaway_winners
              ORDER BY id'
        );
    }

    // -----------------------------------------------------------------
    //  Intern
    // -----------------------------------------------------------------

    /**
     * Ein Platz auf dem Rad, gewichtet nach Tickets.
     *
     * random_int und nicht mt_rand: es geht um Preise, und "der Zufall
     * war vorhersagbar" will niemand hinterher erklaeren muessen.
     *
     * @param list<int> $gewichte
     */
    public static function pick(array $gewichte): int
    {
        $wurf = random_int(1, max(1, (int) array_sum($gewichte)));

        foreach ($gewichte as $platz => $gewicht) {
            $wurf -= $gewicht;

            if ($wurf <= 0) {
                return $platz;
            }
        }

        return count($gewichte) - 1;
    }

    /**
     * Was das Rad zum Zeichnen braucht. Namen und Tickets, nichts sonst
     * - die Twitch-ID hat in einer Browserquelle nichts verloren.
     *
     * @param list<array{display_name: string, tickets: int}> $leute
     * @return array{entries: list<array{name: string, tickets: int}>, prize: string, label: string}
     */
    private static function wheel(array $leute, string $preis): array
    {
        return [
            'entries' => array_map(
                static fn (array $l): array => ['name' => $l['display_name'], 'tickets' => $l['tickets']],
                $leute
            ),
            'prize'   => $preis,
            'label'   => $preis === '' ? '' : translate('giveaway.overlay.prize', ['prize' => $preis]),
        ];
    }

    /** @param array<string, mixed> $daten */
    private function send(array $daten): void
    {
        (new Bus($this->app))->send(Giveaway::SLOT, $daten);
    }

    /** @param array<string, mixed> $stand */
    private function save(array $stand): void
    {
        $this->app->settings->set('draw', $stand, Giveaway::scope());
    }

    /**
     * Ein Schritt unter Sperre.
     *
     * Zwei schnelle Klicks, oder die Seite und der Worker zugleich,
     * duerfen nicht zwei Gewinner ziehen oder zweimal ansagen. Die
     * Sperre haelt bis zum Ende der Transaktion; danach wird der Stand
     * frisch gelesen - waehrend wir warteten, kann ihn der andere
     * geaendert haben.
     *
     * @template T
     * @param callable(array<string, mixed>): T $arbeit
     * @return T
     */
    private function locked(callable $arbeit): mixed
    {
        return $this->app->db->transaction(function () use ($arbeit): mixed {
            $this->app->db->run("SELECT pg_advisory_xact_lock(hashtext('plugin:giveaway'))");
            $this->app->settings->flush();

            return $arbeit($this->load());
        });
    }
}
