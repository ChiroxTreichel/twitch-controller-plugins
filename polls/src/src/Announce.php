<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Polls;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Support\Http;

/**
 * Ankuendigen: eine neue Umfrage, und ihr Ergebnis.
 *
 * Zwei Wege, je Umfrage einzeln an- und abschaltbar:
 *
 *   Discord      ueber den Webhook aus den Einstellungen. Die Texte sind
 *                die des alten Systems (admin/new.php, admin/announce.php)
 *                - samt der Strichlinie davor, die dort jede Nachricht
 *                vom vorigen trennte.
 *   Twitch-Chat  ueber den Chat des Kerns, kurz in einer Zeile.
 *
 * Das Ergebnis sagt der Worker an, sobald die Zeit um ist (cron.tick).
 * Im alten System war das eine Endlosschleife (announce.php), die
 * jemand von Hand starten musste.
 */
final class Announce
{
    /** Discord nimmt 2000 Zeichen; etwas Luft fuer die Strichlinie. */
    private const MAX_DISCORD = 1900;

    /** Wie im alten notify.php: trennt jede Nachricht von der vorigen. */
    private const LINE = '-----------------------------------------------------';

    public function __construct(private readonly App $app)
    {
    }

    // -----------------------------------------------------------------
    //  Neue Umfrage
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $poll */
    public function newPoll(array $poll): void
    {
        $adresse = $this->app->url('/polls/' . $poll['id']);

        if ($poll['announce_discord_new']) {
            $this->discord(translate('polls.discord.bot_new'), self::discordNewText($poll, $adresse));
        }

        if ($poll['announce_chat_new']) {
            $this->chat(translate('polls.chat.new', [
                'title' => $poll['title'],
                'when'  => self::when((int) $poll['ends_ts']),
                'url'   => $adresse,
            ]));
        }
    }

    /** @param array<string, mixed> $poll */
    public static function discordNewText(array $poll, string $adresse): string
    {
        $text = translate('polls.discord.new', ['name' => $poll['creator_name'] !== '' ? $poll['creator_name'] : '?'])
            . "\n\n__**" . $poll['title'] . '**__';

        if ($poll['description'] !== '') {
            // Drei Backticks in der Beschreibung schloessen den Block zu
            // frueh - danach stuende der Rest als Markdown da.
            $text .= "\n```\n" . str_replace('```', "'''", (string) $poll['description']) . "\n```";
        }

        return $text . "\n\n" . translate('polls.discord.new_open', ['when' => self::when((int) $poll['ends_ts'])])
            . "\n\n" . $adresse;
    }

    // -----------------------------------------------------------------
    //  Ergebnis
    // -----------------------------------------------------------------

    /**
     * Fuer den Worker: alle Umfragen, deren Zeit um ist und deren
     * Ergebnis noch niemand angesagt hat.
     *
     * Erst als angesagt markieren, dann senden. Andersherum sagte ein
     * zweiter Worker, der zur selben Zeit laeuft, dasselbe noch einmal
     * an - und ein Senden, das scheitert, soll nicht jede Viertelminute
     * erneut in den Chat schreiben. Der Grund steht dann im Log.
     */
    public function due(): void
    {
        $faellig = $this->app->db->all(
            'SELECT id FROM polls_polls
              WHERE ends_at <= now()
                AND result_announced_at IS NULL
                AND (announce_discord_result OR announce_chat_result)
              ORDER BY ends_at'
        );

        $polls = new Polls($this->app);

        foreach ($faellig as $zeile) {
            $meins = $this->app->db->first(
                'UPDATE polls_polls SET result_announced_at = now()
                  WHERE id = CAST(:id AS BIGINT) AND result_announced_at IS NULL
                  RETURNING id',
                ['id' => (string) $zeile['id']]
            );

            $poll = $meins === null ? null : $polls->find((int) $zeile['id']);

            if ($poll !== null) {
                $this->result($poll, $polls->results($poll));
            }
        }
    }

    /**
     * @param array<string, mixed> $poll
     * @param array{rows: list<array{label: string, votes: int}>, participants: int, votes: int} $stand
     */
    public function result(array $poll, array $stand): void
    {
        $adresse = $this->app->url('/polls/' . $poll['id']);

        if ($poll['announce_discord_result']) {
            $this->discord(translate('polls.discord.bot_result'), self::discordResultText($poll, $stand, $adresse));
        }

        if ($poll['announce_chat_result']) {
            $this->chat(self::chatResultText($poll, $stand, $adresse));
        }
    }

    /**
     * Wie im alten announce.php: bei "einen waehlen" der Gewinner, sonst
     * die Plaetze bis zur Zahl der Kreuze - Gleichstand zusammen auf
     * einem Platz, mit "je".
     *
     * @param array<string, mixed> $poll
     * @param array{rows: list<array{label: string, votes: int}>, participants: int, votes: int} $stand
     */
    public static function discordResultText(array $poll, array $stand, string $adresse): string
    {
        $text = translate('polls.discord.result', ['title' => $poll['title']]) . "\n\n";
        $plaetze = self::places($stand['rows'], (int) $poll['max_choices']);

        if ($plaetze === []) {
            $text .= translate('polls.discord.no_votes') . "\n";
        } elseif ((int) $poll['max_choices'] === 1) {
            $erster = $plaetze[0];
            $text .= (count($erster['labels']) > 1 ? translate('polls.discord.winners_tie') : translate('polls.discord.winner'))
                . "\n\n" . self::line($erster, $stand['participants'], false) . "\n";
        } else {
            $text .= translate('polls.discord.results') . "\n\n";

            foreach ($plaetze as $platz) {
                $text .= self::line($platz, $stand['participants'], true) . "\n";
            }
        }

        return $text . "\n\n" . translate('polls.discord.result_link') . "\n" . $adresse;
    }

    /**
     * Eine Zeile im Chat - Twitch nimmt 500 Zeichen und keine Absaetze.
     *
     * @param array<string, mixed> $poll
     * @param array{rows: list<array{label: string, votes: int}>, participants: int, votes: int} $stand
     */
    public static function chatResultText(array $poll, array $stand, string $adresse): string
    {
        $plaetze = self::places($stand['rows'], (int) $poll['max_choices']);

        if ($plaetze === []) {
            return translate('polls.chat.no_votes', ['title' => $poll['title'], 'url' => $adresse]);
        }

        if ((int) $poll['max_choices'] === 1) {
            return translate('polls.chat.winner', [
                'title' => $poll['title'],
                'names' => implode(', ', $plaetze[0]['labels']),
                'votes' => (string) $plaetze[0]['votes'],
                'total' => (string) $stand['participants'],
                'url'   => $adresse,
            ]);
        }

        $teile = array_map(
            static fn (array $p): string => $p['place'] . '. ' . implode(', ', $p['labels']) . ' (' . $p['votes'] . ')',
            $plaetze
        );

        return translate('polls.chat.results', [
            'title'  => $poll['title'],
            'places' => implode(' · ', $teile),
            'url'    => $adresse,
        ]);
    }

    /**
     * Die Plaetze: Eintraege mit gleich vielen Stimmen teilen sich
     * einen, der naechste zaehlt dahinter weiter (1, 1, 3). Es gibt so
     * viele Plaetze, wie man ankreuzen durfte - und einen Eintrag ohne
     * Stimme nennt keine Ansage einen Gewinner.
     *
     * @param list<array{label: string, votes: int}> $zeilen nach Stimmen sortiert
     * @return list<array{place: int, labels: list<string>, votes: int}>
     */
    public static function places(array $zeilen, int $anzahl): array
    {
        $plaetze = [];
        $davor = 0;

        foreach ($zeilen as $zeile) {
            if ($zeile['votes'] <= 0) {
                break;
            }

            $letzter = count($plaetze) - 1;

            if ($letzter >= 0 && $plaetze[$letzter]['votes'] === $zeile['votes']) {
                $plaetze[$letzter]['labels'][] = $zeile['label'];
                $davor++;

                continue;
            }

            if ($davor + 1 > $anzahl) {
                break;
            }

            $plaetze[] = ['place' => $davor + 1, 'labels' => [$zeile['label']], 'votes' => $zeile['votes']];
            $davor++;
        }

        return $plaetze;
    }

    /**
     * "**A, B** und **C** mit je 3 von 7 Stimmen." - fett wie damals.
     *
     * @param array{place: int, labels: list<string>, votes: int} $platz
     */
    private static function line(array $platz, int $teilnehmer, bool $mitPlatz): string
    {
        $namen = $platz['labels'];
        $letzter = array_pop($namen);
        $fett = $namen === []
            ? '**' . $letzter . '**'
            : '**' . implode(', ', $namen) . '** ' . translate('polls.and') . ' **' . $letzter . '**';

        $werte = [
            'place' => (string) $platz['place'],
            'names' => $fett,
            'votes' => (string) $platz['votes'],
            'total' => (string) $teilnehmer,
        ];
        $gleich = count($platz['labels']) > 1;

        if ($mitPlatz) {
            return $gleich ? translate('polls.discord.place_tie', $werte) : translate('polls.discord.place', $werte);
        }

        return $gleich ? translate('polls.discord.line_tie', $werte) : translate('polls.discord.line', $werte);
    }

    /**
     * "18:30", "morgen um 18:30", "übermorgen um 18:30", "zum 12.10.2026
     * um 18:30" - wie in der alten Ankuendigung.
     */
    public static function when(int $ende, ?int $jetzt = null): string
    {
        $zone = new DateTimeZone(date_default_timezone_get());
        $endeZeit = (new DateTimeImmutable('@' . $ende))->setTimezone($zone);
        $heute = (new DateTimeImmutable('@' . ($jetzt ?? time())))->setTimezone($zone)->setTime(0, 0);
        $tage = (int) $heute->diff($endeZeit->setTime(0, 0))->format('%r%a');
        $uhrzeit = $endeZeit->format('H:i');

        return match ($tage) {
            0       => translate('polls.when.today', ['time' => $uhrzeit]),
            1       => translate('polls.when.tomorrow', ['time' => $uhrzeit]),
            2       => translate('polls.when.day_after', ['time' => $uhrzeit]),
            default => translate('polls.when.date', ['date' => $endeZeit->format('d.m.Y'), 'time' => $uhrzeit]),
        };
    }

    // -----------------------------------------------------------------
    //  Senden
    // -----------------------------------------------------------------

    /**
     * Eine Nachricht an den Discord-Webhook.
     *
     * @return array{ok: bool, error: string}
     */
    public function discord(string $absender, string $text): array
    {
        $webhook = Polls::webhook($this->app);

        if ($webhook === '') {
            $this->app->log('Umfragen: kein Discord-Webhook eingetragen - Ankuendigung entfaellt.');

            return ['ok' => false, 'error' => translate('polls.error.no_webhook')];
        }

        try {
            $antwort = Http::json('POST', $webhook, [
                'username' => $absender,
                'content'  => self::cut(self::LINE . "\n" . $text, self::MAX_DISCORD),
                // Kein @everyone aus einer Beschreibung heraus.
                'allowed_mentions' => ['parse' => []],
            ]);
        } catch (Throwable $e) {
            $this->app->log('Umfragen: Discord nicht erreichbar - ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (!$antwort->ok()) {
            $this->app->log('Umfragen: Discord lehnt ab (' . $antwort->status . ') - ' . $antwort->body);

            return ['ok' => false, 'error' => translate('polls.error.discord', ['status' => (string) $antwort->status])];
        }

        return ['ok' => true, 'error' => ''];
    }

    private function chat(string $text): void
    {
        $ergebnis = $this->app->chat->send($text);

        if (!$ergebnis['ok']) {
            $this->app->log('Umfragen: Chat-Ankuendigung ging nicht raus - ' . $ergebnis['error']);
        }
    }

    private static function cut(string $text, int $laenge): string
    {
        return mb_substr($text, 0, $laenge);
    }
}
