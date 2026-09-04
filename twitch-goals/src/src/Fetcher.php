<?php

declare(strict_types=1);

namespace TwitchController\Plugin\TwitchGoals;

use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;
use TwitchController\Plugin\Goals\Goals;

/**
 * Die Zahlen von Twitch.
 *
 * Zwei Wege, wie im alten System:
 *
 *   helix/goals   die Ziele, die man auf Twitch selbst angelegt hat.
 *                 Typ "follower" und "subscription_count" - daraus
 *                 kommen aktueller Stand UND Zielwert.
 *   die Zaehler   helix/channels/followers und helix/subscriptions
 *                 liefern ein "total". Das ist der Rueckfall, wenn
 *                 auf Twitch gar kein Ziel angelegt ist: dann steht
 *                 wenigstens die Zahl da, nur ohne Ziel.
 *
 * Aktuell bleibt es auf zwei Arten: die Abos channel.goal.* melden
 * jede Aenderung sofort, und cron.tick fragt zusaetzlich nach. Ohne
 * das Nachfragen stimmte die Anzeige nach einem Neustart des Servers
 * erst beim naechsten Follower wieder.
 */
final class Fetcher
{
    /**
     * Wie oft hoechstens bei Twitch nachgefragt wird.
     *
     * Dreissig Sekunden - dasselbe, was der Knopf "Jetzt abrufen"
     * macht, nur von selbst. Die Abos melden Aenderungen ohnehin
     * sofort; das Nachfragen faengt ab, was dabei verloren geht (ein
     * Neustart, ein verpasster Webhook, ein Ziel, das auf Twitch neu
     * angelegt wurde).
     *
     * Kosten: hoechstens drei Aufrufe je halbe Minute. Twitch rechnet
     * in Punkten pro Minute und liegt damit weit darueber.
     *
     * Der Hintergrundprozess taktet schneller (WORKER_INTERVAL, in der
     * Voreinstellung 15 Sekunden), deshalb greift diese Grenze und
     * nicht der Takt.
     */
    private const POLL_SECONDS = 30;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * Der letzte bekannte Stand.
     *
     * @return array{follower_current: int, follower_goal: int, sub_current: int, sub_goal: int, checked_at: int}
     */
    public function state(): array
    {
        $roh = $this->app->settings->get('state', null, Config::scope());
        $roh = is_array($roh) ? $roh : [];

        return [
            'follower_current' => max(0, (int) ($roh['follower_current'] ?? 0)),
            'follower_goal'    => max(0, (int) ($roh['follower_goal'] ?? 0)),
            'sub_current'      => max(0, (int) ($roh['sub_current'] ?? 0)),
            'sub_goal'         => max(0, (int) ($roh['sub_goal'] ?? 0)),
            'checked_at'       => max(0, (int) ($roh['checked_at'] ?? 0)),
        ];
    }

    /**
     * @param array{follower_current: int, follower_goal: int, sub_current: int, sub_goal: int, checked_at: int} $stand
     */
    private function store(array $stand): void
    {
        $this->app->settings->set('state', $stand, Config::scope());
    }

    /**
     * Bei Twitch nachfragen, wenn der Stand alt genug ist.
     *
     * @param bool $sofort true erzwingt die Abfrage - fuer den Knopf
     *                     "Jetzt abrufen" und nach einem Ereignis
     * @return array{follower_current: int, follower_goal: int, sub_current: int, sub_goal: int, checked_at: int}
     */
    public function refresh(bool $sofort = false): array
    {
        $stand = $this->state();
        $jetzt = time();

        if (!$sofort && $jetzt - $stand['checked_at'] < self::POLL_SECONDS) {
            return $stand;
        }

        $kanalId = $this->app->settings->string('twitch_broadcaster_id');
        if ($kanalId === '') {
            return $stand;
        }

        $neu = $stand;
        $neu['checked_at'] = $jetzt;

        // --- Die Ziele von Twitch ---------------------------------
        foreach ($this->goals($kanalId) as $typ => $werte) {
            if ($typ === 'follower') {
                $neu['follower_current'] = $werte['current'];
                $neu['follower_goal'] = $werte['goal'];
            } elseif ($typ === 'sub') {
                $neu['sub_current'] = $werte['current'];
                $neu['sub_goal'] = $werte['goal'];
            }
        }

        // --- Rueckfall: die reinen Zaehler ------------------------
        // Nur wenn auf Twitch kein Ziel dieses Typs angelegt ist.
        // Sonst wuerde der Zaehler den Stand des Ziels ueberschreiben,
        // und die beiden muessen nicht uebereinstimmen: ein Ziel zaehlt
        // ab seinem Anlegen, der Zaehler ab null.
        if ($neu['follower_goal'] === 0) {
            $anzahl = $this->total('channels/followers', ['broadcaster_id' => $kanalId]);
            if ($anzahl !== null) {
                $neu['follower_current'] = $anzahl;
            }
        }

        if ($neu['sub_goal'] === 0) {
            $anzahl = $this->total('subscriptions', ['broadcaster_id' => $kanalId]);
            if ($anzahl !== null) {
                $neu['sub_current'] = $anzahl;
            }
        }

        $this->store($neu);
        $this->push($neu);

        return $neu;
    }

    /**
     * Ein Ereignis von Twitch einarbeiten.
     *
     * channel.goal.progress bringt current_amount und target_amount
     * gleich mit - dafuer braucht es keine Abfrage. Nur beim Ende
     * eines Ziels wird nachgefragt, weil dann der Rueckfall auf den
     * reinen Zaehler greifen muss.
     *
     * @param array<string, mixed> $event
     */
    public function onEvent(string $typ, array $event): void
    {
        $art = self::kind((string) ($event['type'] ?? ''));
        if ($art === '') {
            return;
        }

        if ($typ === 'twitch.channel.goal.end') {
            $this->refresh(true);

            return;
        }

        $stand = $this->state();
        $stand[$art . '_current'] = max(0, (int) ($event['current_amount'] ?? 0));
        $stand[$art . '_goal'] = max(0, (int) ($event['target_amount'] ?? 0));
        $stand['checked_at'] = time();

        $this->store($stand);
        $this->push($stand);
    }

    /**
     * Den Stand ins Overlay schicken - samt der Titel, damit eine
     * frisch verbundene Browserquelle sofort etwas anzeigt.
     *
     * @param array{follower_current: int, follower_goal: int, sub_current: int, sub_goal: int, checked_at: int} $stand
     */
    public function push(array $stand): void
    {
        Goals::send($this->app, $this->payload($stand));
    }

    /**
     * Nur die Einstellungen ins Overlay - Titel und Schalter.
     *
     * Ohne die Zahlen, und das ist der ganze Zweck: das Overlay legt
     * eintreffende Nachrichten uebereinander, statt seinen Zustand zu
     * ersetzen. Wer also nur einen Titel aendert, laesst die Zahlen
     * stehen, die dort schon richtig standen.
     *
     * Vorher ging hier payload() hinaus, also auch die Zahlen - so wie
     * sie in dem Moment gespeichert waren. Kurz nach einer Installation
     * sind das Nullen, weil noch kein Abruf gelaufen ist. Ein Streamer,
     * der eine Bezeichnung aendert, sah daraufhin 0 von 0 im Overlay -
     * bis der Takt eine halbe Minute spaeter die echten Zahlen
     * nachschob. Das Speichern eines Titels hat mit den Zahlen nichts
     * zu tun und darf sie nicht ueberschreiben.
     */
    public function pushSettings(): void
    {
        Goals::send($this->app, Config::titles($this->app) + [
            'follower_enabled' => Config::goalEnabled($this->app, 'follower'),
            'sub_enabled'      => Config::goalEnabled($this->app, 'sub'),
        ]);
    }

    /**
     * Was das Overlay braucht, um die Ziele zu zeichnen.
     *
     * Eine Quelle fuer zwei Wege: push() schickt es durch die Leitung,
     * und der Hook goals.state gibt dasselbe beim Laden der Seite mit.
     * Getrennt gebaut wuerden die zwei mit der Zeit auseinanderlaufen -
     * ein Feld hier ergaenzt, dort vergessen, und dann zeigt das
     * Overlay bis zur ersten Aenderung etwas anderes als danach.
     *
     * @param array{follower_current: int, follower_goal: int, sub_current: int, sub_goal: int, checked_at: int} $stand
     * @return array<string, mixed>
     */
    public function payload(array $stand): array
    {
        return Config::titles($this->app) + [
            'follower_current' => $stand['follower_current'],
            'follower_goal'    => $stand['follower_goal'],
            'sub_current'      => $stand['sub_current'],
            'sub_goal'         => $stand['sub_goal'],

            // Die beiden Schalter gehen mit ins Overlay: dort wird der
            // Abschnitt ausgeblendet, statt hier das Geruest zu
            // beschneiden. Am HTML des Betreibers herumzuschneiden
            // waere nicht verlaesslich - er darf es frei schreiben.
            'follower_enabled' => Config::goalEnabled($this->app, 'follower'),
            'sub_enabled'      => Config::goalEnabled($this->app, 'sub'),
        ];
    }

    // -----------------------------------------------------------------
    //  Twitch
    // -----------------------------------------------------------------

    /**
     * Die auf Twitch angelegten Ziele, auf unsere zwei Arten gefiltert.
     *
     * @return array<string, array{current: int, goal: int}>
     */
    private function goals(string $kanalId): array
    {
        $daten = $this->data('goals', ['broadcaster_id' => $kanalId]);

        $ziele = [];
        foreach ($daten as $ziel) {
            if (!is_array($ziel)) {
                continue;
            }

            $art = self::kind((string) ($ziel['type'] ?? ''));
            if ($art === '') {
                continue;
            }

            $ziele[$art] = [
                'current' => max(0, (int) ($ziel['current_amount'] ?? 0)),
                'goal'    => max(0, (int) ($ziel['target_amount'] ?? 0)),
            ];
        }

        return $ziele;
    }

    /**
     * Aus dem Typ von Twitch unsere Bezeichnung.
     *
     * Twitch kennt mehr Arten als wir anzeigen. "subscription" zaehlt
     * Abo-Punkte, "subscription_count" zaehlt Abos - im alten System
     * war es der Zaehler, und dabei bleibt es.
     */
    private static function kind(string $typ): string
    {
        return match (strtolower(trim($typ))) {
            'follower' => 'follower',
            'subscription_count' => 'sub',
            default => '',
        };
    }

    /**
     * Das "total" einer Liste - oder null, wenn es nicht klappte.
     *
     * @param array<string, string|int> $query
     */
    private function total(string $endpunkt, array $query): ?int
    {
        try {
            $antwort = $this->app->twitch->api()->as(TokenStore::BROADCASTER)->get($endpunkt, $query);
        } catch (Throwable $e) {
            $this->app->log('Twitch-Goals: ' . $endpunkt . ' nicht abrufbar: ' . $e->getMessage());

            return null;
        }

        if (!$antwort->ok()) {
            $this->app->log('Twitch-Goals: ' . $endpunkt . ' nicht abrufbar: ' . $antwort->error());

            return null;
        }

        return isset($antwort->json['total']) ? max(0, (int) $antwort->json['total']) : null;
    }

    /**
     * @param array<string, string|int> $query
     * @return list<mixed>
     */
    private function data(string $endpunkt, array $query): array
    {
        try {
            $antwort = $this->app->twitch->api()->as(TokenStore::BROADCASTER)->get($endpunkt, $query);
        } catch (Throwable $e) {
            $this->app->log('Twitch-Goals: ' . $endpunkt . ' nicht abrufbar: ' . $e->getMessage());

            return [];
        }

        if (!$antwort->ok()) {
            $this->app->log('Twitch-Goals: ' . $endpunkt . ' nicht abrufbar: ' . $antwort->error());

            return [];
        }

        $daten = $antwort->json['data'] ?? [];

        return is_array($daten) ? array_values($daten) : [];
    }
}
