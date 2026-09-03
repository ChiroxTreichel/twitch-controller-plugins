<?php

declare(strict_types=1);

namespace TwitchController\Plugin\ChatCommands;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use TwitchController\Core\App;
use TwitchController\Core\Twitch\TokenStore;

/**
 * ===================================================================
 *  !discord - der besondere Befehl
 * ===================================================================
 *
 * Jeder andere Befehl antwortet mit festem Text. Dieser fragt vorher
 * Twitch, ob der Schreiber dem Kanal lange genug folgt, und gibt den
 * Anmeldelink nur dann heraus. Vier Dinge daran sind leicht zu
 * uebersehen:
 *
 * 1. `!discord <name>` aendert nur, WER ANGESPROCHEN wird - nicht, wer
 *    geprueft wird. Geprueft wird immer der Schreiber. Ein Zuschauer,
 *    der lange genug folgt, kann den Link damit an jemanden
 *    weiterreichen: "!discord freund" antwortet "Hey @freund. Tritt
 *    doch meinem Discord-Server bei: …". Wer selbst nicht lange genug
 *    folgt, bekommt die Absage - ebenfalls an @freund gerichtet. So
 *    war es im alten System, und es ist Absicht: die Huerde haengt am
 *    Schreiber, die Anrede ist nur Hoeflichkeit.
 *
 * 2. Der Kanalinhaber wird nicht geprueft. Man kann sich nicht selbst
 *    folgen - ohne diese Ausnahme koennte er seinen eigenen Link nie
 *    abrufen.
 *
 * 3. Zwei Absagen, nicht eine: "folgt gar nicht" und "folgt noch nicht
 *    lange genug, es fehlen noch N Tage". Die zweite ist die
 *    hilfreiche - sie sagt, wann es klappt.
 *
 * 4. Twitch verwirft eine Nachricht, die mit der vorigen desselben
 *    Absenders identisch ist. Zwei Leute hintereinander bekommen sonst
 *    nur einmal eine Antwort - und zwar lautlos. Siehe
 *    Dispatcher::unique().
 */
final class Discord
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array<string, mixed> $message Nutzlast von core.chat.message
     * @param list<string>         $parts   Die Wortteile der Chatzeile
     */
    public function answer(array $message, array $parts): string
    {
        $einstellungen = Commands::settingsOf($this->app, 'discord');
        $link = trim((string) ($einstellungen['invite_link'] ?? ''));
        $tage = max(0, (int) ($einstellungen['minimum_follow_days'] ?? 0));

        $schreiber = (string) ($message['chatter_login'] ?? '');
        $schreiberId = (string) ($message['chatter_id'] ?? '');

        // Siehe 1. oben: die Anrede kann jemand anderes sein.
        $angesprochen = self::mention(self::login((string) ($parts[1] ?? '')) ?: $schreiber);

        if ($link === '') {
            return translate('chat_commands.discord.not_configured', ['user' => $angesprochen]);
        }

        $kanalId = $this->app->settings->string('twitch_broadcaster_id');
        if ($kanalId === '' || $schreiberId === '') {
            return translate('chat_commands.discord.no_user', ['user' => self::mention($schreiber)]);
        }

        // Siehe 2. oben.
        if ($schreiberId === $kanalId) {
            return translate('chat_commands.discord.join', [
                'user' => $angesprochen,
                'link' => $link,
            ]);
        }

        try {
            $seit = $this->followedAt($kanalId, $schreiberId);
        } catch (Throwable $e) {
            $this->app->log('Chatbefehle: !discord konnte den Follow-Status nicht pruefen: ' . $e->getMessage());

            return translate('chat_commands.discord.check_failed', ['user' => $angesprochen]);
        }

        $kanal = $this->channelName();

        if ($seit === null) {
            return translate('chat_commands.discord.not_following', [
                'user'    => $angesprochen,
                'channel' => $kanal,
                'days'    => (string) $tage,
            ]);
        }

        $frei = $seit->modify('+' . $tage . ' days');
        $jetzt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($frei > $jetzt) {
            // max(1, …): "es fehlen noch 0 Tage" waere Unsinn, und die
            // Differenz in ganzen Tagen wird kurz vor Ablauf zu null.
            $restTage = max(1, (int) $jetzt->diff($frei)->format('%a'));

            return translate('chat_commands.discord.too_recent', [
                'user'      => $angesprochen,
                'channel'   => $kanal,
                'days'      => (string) $tage,
                'remaining' => (string) $restTage,
            ]);
        }

        return translate('chat_commands.discord.join', [
            'user' => $angesprochen,
            'link' => $link,
        ]);
    }

    /**
     * Seit wann folgt dieser Benutzer - oder null, wenn gar nicht.
     *
     * Der Endpunkt braucht einen Benutzer-Token mit
     * moderator:read:followers, und zwar von einem Moderator des
     * Kanals. Genommen wird deshalb der Token des Kanalinhabers: er ist
     * es immer, ein Bot-Konto nur, wenn man es dazu gemacht hat.
     *
     * Das alte System schickte hier zusaetzlich moderator_id mit. Den
     * Parameter kennt der Endpunkt nicht - Twitch hat ihn ignoriert.
     */
    private function followedAt(string $kanalId, string $benutzerId): ?DateTimeImmutable
    {
        $antwort = $this->app->twitch->api()->as(TokenStore::BROADCASTER)->get('channels/followers', [
            'broadcaster_id' => $kanalId,
            'user_id'        => $benutzerId,
        ]);

        if (!$antwort->ok()) {
            throw new \RuntimeException($antwort->error());
        }

        $daten = is_array($antwort->json['data'] ?? null) ? $antwort->json['data'] : [];
        $erster = is_array($daten[0] ?? null) ? $daten[0] : [];
        $seit = trim((string) ($erster['followed_at'] ?? ''));

        if ($seit === '') {
            return null;
        }

        return new DateTimeImmutable($seit);
    }

    /**
     * Wie der Kanal in der Antwort heisst.
     *
     * Im alten System stand hier der Kanalname fest im Code - bei einem
     * Werkzeug, das sich jeder selbst hinstellt, waere das der Name
     * eines Fremden.
     */
    private function channelName(): string
    {
        foreach (['twitch_broadcaster_name', 'twitch_broadcaster_login'] as $key) {
            $name = trim($this->app->settings->string($key));
            if ($name !== '') {
                return $name;
            }
        }

        return translate('chat_commands.discord.this_channel');
    }

    /**
     * Aus "@Name" wird "name", aus allem Unerlaubten "".
     */
    private static function login(string $roh): string
    {
        $login = strtolower(ltrim(trim($roh), '@'));

        return preg_match('/^[a-z0-9_]{1,25}$/', $login) === 1 ? $login : '';
    }

    private static function mention(string $login): string
    {
        $login = ltrim(trim($login), '@');

        return $login === '' ? '' : '@' . $login;
    }
}
