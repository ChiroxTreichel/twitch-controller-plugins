<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Polls;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Config\Settings;
use TwitchController\Core\Overlay\Bus;

/**
 * Umfragen, ihre Eintraege und die Stimmen.
 *
 * Wie im alten System: eine Umfrage hat eine Ueberschrift, eine
 * Beschreibung, Eintraege und ein Ende. Man waehlt GENAU so viele
 * Eintraege, wie eingestellt ist, und darf seine Wahl bis zum Ende
 * aendern. Das Ergebnis sieht man erst, wenn die Zeit um ist - der
 * Zwischenstand ist etwas fuer das Team und das Overlay.
 *
 * Was anders ist: kein Share-Code und keine Liste fester Admins mehr.
 * Wer eine Umfrage sehen, anlegen oder beenden darf, regeln die Rechte
 * des Kerns - das ganze Team sieht alle Umfragen.
 */
final class Polls
{
    public const SLUG = 'polls';
    public const SLOT = 'polls';

    public const MAX_TITLE = 120;
    public const MAX_DESCRIPTION = 2000;
    public const MAX_OPTION = 100;
    public const MAX_OPTIONS = 100;

    /** Mindestens eine Minute, wie im alten System; hoechstens ein Jahr. */
    public const MIN_SECONDS = 60;
    public const MAX_SECONDS = 365 * 86400;

    /**
     * Wo der Kasten im Overlay sitzen darf. "fill" fehlt mit Absicht:
     * dann laege die Umfrage ueber der ganzen Buehne. Wer sie allein
     * und gross will, nimmt eine eigene Browserquelle mit ?view=polls.
     */
    public const POSITIONS = [
        'top-left', 'top-center', 'top-right',
        'middle-left', 'center', 'middle-right',
        'bottom-left', 'bottom-center', 'bottom-right',
    ];

    private const SPALTEN = 'id, title, description, max_choices, suggestions,
        announce_discord_new, announce_discord_result, announce_chat_new, announce_chat_result,
        result_announced_at, creator_id, creator_name,
        EXTRACT(EPOCH FROM ends_at)::bigint AS ends_ts,
        EXTRACT(EPOCH FROM created_at)::bigint AS created_ts';

    public function __construct(private readonly App $app)
    {
    }

    public static function scope(): string
    {
        return Settings::pluginScope(self::SLUG);
    }

    // -----------------------------------------------------------------
    //  Einstellungen des Plugins
    // -----------------------------------------------------------------

    public static function webhook(App $app): string
    {
        return $app->settings->string('discord_webhook', '', self::scope());
    }

    /** Die Umfrage, die gerade im Overlay steht - 0 heisst keine. */
    public static function overlayPoll(App $app): int
    {
        return max(0, $app->settings->int('overlay_poll', 0, self::scope()));
    }

    public static function setOverlayPoll(App $app, int $id): void
    {
        $app->settings->set('overlay_poll', max(0, $id), self::scope());
    }

    public static function overlayPosition(App $app): string
    {
        $stelle = $app->settings->string('overlay_position', 'top-right', self::scope());

        return in_array($stelle, self::POSITIONS, true) ? $stelle : 'top-right';
    }

    public static function overlayWidth(App $app): int
    {
        return max(300, min(1920, $app->settings->int('overlay_width', 600, self::scope())));
    }

    /**
     * Webhook und Overlay speichern.
     *
     * @param array<string, string> $eingabe
     * @return string leer bei Erfolg, sonst der Grund
     */
    public static function saveSettings(App $app, array $eingabe): string
    {
        $webhook = trim((string) ($eingabe['discord_webhook'] ?? ''));

        if ($webhook !== '' && !self::looksLikeWebhook($webhook)) {
            return translate('polls.error.webhook');
        }

        $scope = self::scope();
        $app->settings->set('discord_webhook', $webhook, $scope);

        $stelle = (string) ($eingabe['overlay_position'] ?? '');
        $app->settings->set('overlay_position', in_array($stelle, self::POSITIONS, true) ? $stelle : 'top-right', $scope);
        $app->settings->set('overlay_width', max(300, min(1920, (int) ($eingabe['overlay_width'] ?? 600))), $scope);

        return '';
    }

    /**
     * Sieht aus wie ein Discord-Webhook. Ob er funktioniert, zeigt erst
     * das Senden - dafuer gibt es den Testknopf.
     */
    public static function looksLikeWebhook(string $url): bool
    {
        return preg_match('~^https://(?:\w+\.)?discord(?:app)?\.com/api/webhooks/\d+/[\w-]+~', trim($url)) === 1;
    }

    // -----------------------------------------------------------------
    //  Lesen
    // -----------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $zeile = $this->app->db->first(
            'SELECT ' . self::SPALTEN . ' FROM polls_polls WHERE id = CAST(:id AS BIGINT)',
            ['id' => (string) $id]
        );

        return $zeile === null ? null : self::shape($zeile);
    }

    /**
     * Alle Umfragen, die neueste zuerst, mit der Zahl der Teilnehmer.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $zeilen = $this->app->db->all(
            'SELECT ' . self::SPALTEN . ',
                    (SELECT COUNT(DISTINCT v.voter_id) FROM polls_votes v WHERE v.poll_id = p.id) AS participants
               FROM polls_polls p
              ORDER BY created_at DESC, id DESC'
        );

        return array_map(
            static fn (array $z): array => self::shape($z) + ['participants' => (int) $z['participants']],
            $zeilen
        );
    }

    /**
     * Die Eintraege in ihrer Reihenfolge, mit Stimmen.
     *
     * @return list<array{id: int, label: string, position: int, suggested_by: string, votes: int}>
     */
    public function options(int $pollId): array
    {
        return array_map(
            static fn (array $z): array => [
                'id'           => (int) $z['id'],
                'label'        => (string) $z['label'],
                'position'     => (int) $z['position'],
                'suggested_by' => (string) $z['suggested_by'],
                'votes'        => (int) $z['votes'],
            ],
            $this->app->db->all(
                'SELECT o.id, o.label, o.position, o.suggested_by, COUNT(v.voter_id) AS votes
                   FROM polls_options o
                   LEFT JOIN polls_votes v ON v.option_id = o.id
                  WHERE o.poll_id = CAST(:id AS BIGINT)
                  GROUP BY o.id
                  ORDER BY o.position, o.id',
                ['id' => (string) $pollId]
            )
        );
    }

    /**
     * Der Stand: Eintraege nach Stimmen, bei Gleichstand in ihrer
     * Reihenfolge - so stand es im alten System, auf der Seite wie im
     * Overlay.
     *
     * "participants" sind Menschen, "votes" Kreuze. Bei "zwei waehlen"
     * gibt ein Mensch zwei Kreuze; die Prozente auf der Seite rechnen
     * wie frueher auf die Kreuze, die Ansage nennt die Menschen.
     *
     * @param array<string, mixed> $poll
     * @return array{rows: list<array{id: int, label: string, votes: int, share: float}>, participants: int, votes: int}
     */
    public function results(array $poll): array
    {
        $eintraege = $this->options((int) $poll['id']);
        $kreuze = array_sum(array_column($eintraege, 'votes'));

        usort($eintraege, static fn (array $a, array $b): int => [$b['votes'], $a['position'], $a['id']] <=> [$a['votes'], $b['position'], $b['id']]);

        return [
            'rows' => array_map(
                static fn (array $e): array => [
                    'id'    => $e['id'],
                    'label' => $e['label'],
                    'votes' => $e['votes'],
                    'share' => $kreuze > 0 ? round($e['votes'] / $kreuze * 100, 1) : 0.0,
                ],
                $eintraege
            ),
            'participants' => (int) $this->app->db->value(
                'SELECT COUNT(DISTINCT voter_id) FROM polls_votes WHERE poll_id = CAST(:id AS BIGINT)',
                ['id' => (string) $poll['id']]
            ),
            'votes' => $kreuze,
        ];
    }

    /**
     * Was jemand gewaehlt hat - fuer die Haekchen auf der Seite.
     *
     * @return list<int>
     */
    public function choicesOf(int $pollId, string $voterId): array
    {
        return array_map('intval', array_column($this->app->db->all(
            'SELECT option_id FROM polls_votes WHERE poll_id = CAST(:poll AS BIGINT) AND voter_id = :voter',
            ['poll' => (string) $pollId, 'voter' => $voterId]
        ), 'option_id'));
    }

    /** @param array<string, mixed> $poll */
    public static function isOpen(array $poll): bool
    {
        return time() < (int) $poll['ends_ts'];
    }

    // -----------------------------------------------------------------
    //  Anlegen
    // -----------------------------------------------------------------

    /**
     * Eine neue Umfrage aus dem Formular.
     *
     * Alle Fehler auf einmal und nicht nur den ersten: das Formular ist
     * lang, und wer es dreimal abschicken muss, um drei Fehler einzeln
     * zu erfahren, gibt beim zweiten auf.
     *
     * @param array<string, mixed> $eingabe
     * @param array<string, mixed> $ersteller angemeldeter Benutzer
     * @return array{errors: list<string>, id: int}
     */
    public function create(array $eingabe, array $ersteller): array
    {
        $fehler = [];

        $titel = self::cut(self::oneLine((string) ($eingabe['title'] ?? '')), self::MAX_TITLE);
        if ($titel === '') {
            $fehler[] = translate('polls.error.title');
        }

        $beschreibung = self::cut(self::text((string) ($eingabe['description'] ?? '')), self::MAX_DESCRIPTION);

        $eintraege = self::parseOptions(is_array($eingabe['options'] ?? null) ? $eingabe['options'] : []);
        if ($eintraege === []) {
            $fehler[] = translate('polls.error.no_options');
        } elseif (count($eintraege) > self::MAX_OPTIONS) {
            $fehler[] = translate('polls.error.too_many_options', ['max' => (string) self::MAX_OPTIONS]);
        }

        $wahl = (int) ($eingabe['max_choices'] ?? 1);
        if ($wahl < 1) {
            $fehler[] = translate('polls.error.max_choices');
        } elseif ($eintraege !== [] && $wahl > count($eintraege)) {
            $fehler[] = translate('polls.error.max_too_high');
        }

        $ende = self::endFromInput($eingabe, $fehler);

        if ($fehler !== []) {
            return ['errors' => $fehler, 'id' => 0];
        }

        $flag = static fn (string $name): bool => !empty($eingabe[$name]);

        $id = (int) $this->app->db->transaction(function () use ($titel, $beschreibung, $wahl, $ende, $eintraege, $flag, $ersteller): int {
            $id = (int) $this->app->db->value(
                'INSERT INTO polls_polls (title, description, max_choices, ends_at, suggestions,
                        announce_discord_new, announce_discord_result, announce_chat_new, announce_chat_result,
                        creator_id, creator_name)
                 VALUES (:title, :description, CAST(:max AS INTEGER), to_timestamp(CAST(:ende AS BIGINT)), :suggestions,
                        :dn, :dr, :cn, :cr, :creator_id, :creator_name)
                 RETURNING id',
                [
                    'title'        => $titel,
                    'description'  => $beschreibung,
                    'max'          => (string) $wahl,
                    'ende'         => (string) $ende,
                    'suggestions'  => $flag('suggestions'),
                    'dn'           => $flag('announce_discord_new'),
                    'dr'           => $flag('announce_discord_result'),
                    'cn'           => $flag('announce_chat_new'),
                    'cr'           => $flag('announce_chat_result'),
                    'creator_id'   => (string) ($ersteller['twitch_id'] ?? ''),
                    'creator_name' => (string) ($ersteller['display_name'] ?? $ersteller['login'] ?? ''),
                ]
            );

            foreach ($eintraege as $platz => $text) {
                $this->app->db->run(
                    'INSERT INTO polls_options (poll_id, label, position) VALUES (CAST(:poll AS BIGINT), :label, CAST(:pos AS INTEGER))',
                    ['poll' => (string) $id, 'label' => $text, 'pos' => (string) $platz]
                );
            }

            return $id;
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * Die Eintragsfelder des Formulars, ohne leere und ohne Doppelte.
     *
     * Doppelt heisst: gleich bis auf Gross und Klein. Zwei Felder
     * "Pizza" und "pizza" waeren zwei Kaestchen, zwischen denen sich
     * die Stimmen fuer dasselbe aufteilen.
     *
     * @param array<mixed> $felder
     * @return list<string>
     */
    public static function parseOptions(array $felder): array
    {
        $gesehen = [];
        $eintraege = [];

        foreach ($felder as $zeile) {
            $zeile = is_scalar($zeile) ? self::cut(self::oneLine((string) $zeile), self::MAX_OPTION) : '';
            $schluessel = mb_strtolower($zeile);

            if ($zeile === '' || isset($gesehen[$schluessel])) {
                continue;
            }

            $gesehen[$schluessel] = true;
            $eintraege[] = $zeile;
        }

        return $eintraege;
    }

    /**
     * Das Ende aus "Dauer" oder "fester Zeitpunkt".
     *
     * Der Zeitpunkt aus dem Formular ist Ortszeit - die eingestellte
     * Zeitzone, nicht die des Servers.
     *
     * @param array<string, mixed> $eingabe
     * @param list<string> $fehler
     */
    private static function endFromInput(array $eingabe, array &$fehler): int
    {
        $jetzt = time();

        if (($eingabe['time_mode'] ?? '') === 'datetime') {
            $roh = trim((string) ($eingabe['ends_at'] ?? ''));

            try {
                $ende = $roh === '' ? null : (new DateTimeImmutable($roh, new DateTimeZone(date_default_timezone_get())))->getTimestamp();
            } catch (Throwable) {
                $ende = null;
            }

            if ($ende === null) {
                $fehler[] = translate('polls.error.datetime');

                return 0;
            }

            if ($ende - $jetzt < self::MIN_SECONDS) {
                $fehler[] = translate('polls.error.too_soon');
            }
        } else {
            $sekunden = max(0, (int) ($eingabe['days'] ?? 0)) * 86400
                + max(0, (int) ($eingabe['hours'] ?? 0)) * 3600
                + max(0, (int) ($eingabe['minutes'] ?? 0)) * 60;

            if ($sekunden < self::MIN_SECONDS) {
                $fehler[] = translate('polls.error.too_soon');
            }

            $ende = $jetzt + $sekunden;
        }

        if ($ende - $jetzt > self::MAX_SECONDS) {
            $fehler[] = translate('polls.error.too_long');
        }

        return $ende;
    }

    // -----------------------------------------------------------------
    //  Abstimmen und vorschlagen
    // -----------------------------------------------------------------

    /**
     * Eine Stimme abgeben oder die alte ersetzen.
     *
     * Genau so viele Kreuze wie eingestellt - wie im alten System. Ein
     * Kreuz zu wenig ist keine halbe Stimme, sondern ein Versehen.
     *
     * @param array<string, mixed> $poll
     * @param array{login: string, user_id: string} $wer
     * @param list<mixed> $auswahl Kennungen der Eintraege
     * @return string leer bei Erfolg, sonst der Grund
     */
    public function vote(array $poll, array $wer, array $auswahl): string
    {
        if (!self::isOpen($poll)) {
            return translate('polls.error.closed');
        }

        $erlaubt = array_column($this->options((int) $poll['id']), 'id');
        $wahl = array_values(array_intersect(array_unique(array_map('intval', $auswahl)), $erlaubt));

        if (count($wahl) !== (int) $poll['max_choices']) {
            return translate('polls.error.exact', ['count' => (string) $poll['max_choices']]);
        }

        $ergebnis = $this->app->db->transaction(function () use ($poll, $wer, $wahl): string {
            /*
             * Je Zuschauer und Umfrage eine Sperre. Ohne sie koennten
             * zwei schnelle Klicks mit verschiedener Auswahl beide ihre
             * alten Zeilen loeschen und beide ihre neuen schreiben -
             * und dann haette einer doppelt so viele Kreuze wie erlaubt.
             */
            $this->app->db->run(
                'SELECT pg_advisory_xact_lock(hashtext(:schluessel))',
                ['schluessel' => 'polls:' . $poll['id'] . ':' . $wer['user_id']]
            );

            // Noch einmal unter der Sperre: die Zeit kann eben um sein.
            $offen = $this->app->db->value(
                'SELECT ends_at > now() FROM polls_polls WHERE id = CAST(:id AS BIGINT)',
                ['id' => (string) $poll['id']]
            );

            if ($offen !== true && $offen !== 't') {
                return translate('polls.error.closed');
            }

            $this->app->db->run(
                'DELETE FROM polls_votes WHERE poll_id = CAST(:poll AS BIGINT) AND voter_id = :voter',
                ['poll' => (string) $poll['id'], 'voter' => $wer['user_id']]
            );

            foreach ($wahl as $eintrag) {
                $this->app->db->run(
                    'INSERT INTO polls_votes (poll_id, option_id, voter_id, voter_login)
                     VALUES (CAST(:poll AS BIGINT), CAST(:option AS BIGINT), :voter, :login)',
                    [
                        'poll'   => (string) $poll['id'],
                        'option' => (string) $eintrag,
                        'voter'  => $wer['user_id'],
                        'login'  => $wer['login'],
                    ]
                );
            }

            return '';
        });

        if ($ergebnis === '') {
            $this->pushOverlay((int) $poll['id']);
        }

        return (string) $ergebnis;
    }

    /**
     * Ein eigener Vorschlag kommt als neuer Eintrag dazu.
     *
     * Ohne Stimme: wer vorschlaegt, muss danach noch ankreuzen - wie im
     * alten System. Einen Eintrag, den es schon gibt (ohne Gross und
     * Klein), legt die Datenbank nicht ein zweites Mal an.
     *
     * @param array<string, mixed> $poll
     * @param array{login: string} $wer
     * @return string leer bei Erfolg, sonst der Grund
     */
    public function suggest(array $poll, array $wer, string $text): string
    {
        if (!self::isOpen($poll)) {
            return translate('polls.error.closed');
        }

        if (!$poll['suggestions']) {
            return translate('polls.error.no_suggestions');
        }

        $text = self::cut(self::oneLine($text), self::MAX_OPTION);
        if ($text === '') {
            return translate('polls.error.suggestion_empty');
        }

        if (count($this->options((int) $poll['id'])) >= self::MAX_OPTIONS) {
            return translate('polls.error.too_many_options', ['max' => (string) self::MAX_OPTIONS]);
        }

        $this->app->db->run(
            'INSERT INTO polls_options (poll_id, label, position, suggested_by)
             SELECT CAST(:poll AS BIGINT), :label, COALESCE(MAX(position), -1) + 1, :by
               FROM polls_options WHERE poll_id = CAST(:poll2 AS BIGINT)
             ON CONFLICT DO NOTHING',
            ['poll' => (string) $poll['id'], 'poll2' => (string) $poll['id'], 'label' => $text, 'by' => $wer['login']]
        );

        $this->pushOverlay((int) $poll['id']);

        return '';
    }

    // -----------------------------------------------------------------
    //  Beenden und entfernen
    // -----------------------------------------------------------------

    /** Jetzt beenden - die Ansage holt danach der Worker. */
    public function endNow(int $id): void
    {
        $this->app->db->run(
            'UPDATE polls_polls SET ends_at = now() WHERE id = CAST(:id AS BIGINT) AND ends_at > now()',
            ['id' => (string) $id]
        );

        $this->pushOverlay($id);
    }

    public function delete(int $id): void
    {
        $this->app->db->run('DELETE FROM polls_polls WHERE id = CAST(:id AS BIGINT)', ['id' => (string) $id]);

        if (self::overlayPoll($this->app) === $id) {
            self::setOverlayPoll($this->app, 0);
            $this->sendOverlay();
        }
    }

    // -----------------------------------------------------------------
    //  Overlay
    // -----------------------------------------------------------------

    /**
     * Was das Overlay zeigt: die gewaehlte Umfrage mit Zwischenstand,
     * oder nichts.
     *
     * Die Texte kommen fertig mit - im Skript gibt es kein translate().
     *
     * @return array<string, mixed>
     */
    public function overlayState(): array
    {
        $id = self::overlayPoll($this->app);
        $poll = $id > 0 ? $this->find($id) : null;

        if ($poll === null) {
            return ['visible' => false];
        }

        $stand = $this->results($poll);

        return [
            'visible' => true,
            'id'      => $poll['id'],
            'title'   => $poll['title'],
            'ends_ts' => $poll['ends_ts'],
            'now'     => time(),
            'kicker'  => translate('polls.overlay.kicker'),
            'expired' => translate('polls.overlay.expired'),
            'summary' => $stand['votes'] === 1
                ? translate('polls.overlay.summary_one')
                : translate('polls.overlay.summary', ['count' => (string) $stand['votes']]),
            'rows'    => array_map(
                static fn (array $r): array => ['label' => $r['label'], 'votes' => $r['votes']],
                $stand['rows']
            ),
        ];
    }

    /** Neuer Stand ins Overlay - aber nur, wenn diese Umfrage dort steht. */
    public function pushOverlay(int $pollId): void
    {
        if ($pollId > 0 && self::overlayPoll($this->app) === $pollId) {
            $this->sendOverlay();
        }
    }

    public function sendOverlay(): void
    {
        $stand = $this->overlayState();

        (new Bus($this->app))->send(self::SLOT, $stand['visible'] ? ['kind' => 'poll'] + $stand : ['kind' => 'hide']);
    }

    // -----------------------------------------------------------------
    //  Hilfsmittel
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $z
     * @return array<string, mixed>
     */
    private static function shape(array $z): array
    {
        $ja = static fn (mixed $w): bool => $w === true || $w === 't' || $w === '1' || $w === 1;

        return [
            'id'                      => (int) $z['id'],
            'title'                   => (string) $z['title'],
            'description'             => (string) $z['description'],
            'max_choices'             => (int) $z['max_choices'],
            'ends_ts'                 => (int) $z['ends_ts'],
            'created_ts'              => (int) $z['created_ts'],
            'suggestions'             => $ja($z['suggestions']),
            'announce_discord_new'    => $ja($z['announce_discord_new']),
            'announce_discord_result' => $ja($z['announce_discord_result']),
            'announce_chat_new'       => $ja($z['announce_chat_new']),
            'announce_chat_result'    => $ja($z['announce_chat_result']),
            'result_announced'        => $z['result_announced_at'] !== null,
            'creator_id'              => (string) $z['creator_id'],
            'creator_name'            => (string) $z['creator_name'],
        ];
    }

    /** Zeilenenden vereinheitlichen, Steuerzeichen raus, aussen kuerzen. */
    private static function text(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", mb_scrub($text, 'UTF-8'));

        return trim((string) preg_replace('/[\x{0}-\x{8}\x{B}-\x{1F}\x{7F}]/u', '', $text));
    }

    /** Eine Zeile: alle Leerraeume zu einem Leerzeichen. */
    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', self::text($text)));
    }

    private static function cut(string $text, int $max): string
    {
        return trim(mb_substr($text, 0, $max));
    }
}
