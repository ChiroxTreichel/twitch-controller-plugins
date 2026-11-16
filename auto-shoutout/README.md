# Chat - Auto-Shoutout

Eine Liste von Kanälen – wie bei der Live-Benachrichtigung. Statt
nachzusehen, ob einer davon live geht, hört dieses Plugin auf den Chat:
schreibt ein eingetragener Kanal **im laufenden Stream zum ersten Mal**
in den Chat, löst es automatisch einen Twitch-Shoutout auf ihn aus.

## Runden

„Im laufenden Stream" heißt: in der laufenden **Runde**. Jeder Kanal
bekommt je Runde höchstens einen Shoutout.

- Eine neue Runde beginnt von selbst, wenn der eigene Stream startet
  (`stream.online`).
- Mit **Runde neu starten** geht es auch von Hand – praktisch zum
  Ausprobieren.

## Sperrfristen

Twitch erlaubt einen Shoutout alle zwei Minuten und denselben Kanal nur
einmal pro Stunde. Schreiben mehrere eingetragene Kanäle kurz
nacheinander, bekommt der erste sofort seinen Shoutout, die anderen
werden vorgemerkt und im Abstand von zwei Minuten nachgeholt.

## Custom-Command

Optional unter **Konto → Plugins → Auto-Shoutout einrichten** (oder
über den Knopf **Einstellungen** auf der Seite): ein eigener Chattext statt des
Twitch-Shoutouts, z. B. `!so {{username}}` für einen anderen Bot.

- `{{username}}` wird durch den Login ersetzt, `{{displayname}}` durch
  den Anzeigenamen.
- Gesendet wird über den Chat des Controllers (Bot-Konto, sonst der
  Kanalinhaber).
- Bleibt das Feld leer, gibt es den normalen Twitch-Shoutout.
- Der Abstand von zwei Minuten zwischen zwei Shoutouts bleibt auch
  hier – andere Bots haben für ihr `!so` meist eigene Sperrfristen.

## Der eigene Stream

Shoutouts gibt es nur, während der eigene Kanal live ist – Twitch nimmt
sie sonst ohnehin nicht an. Ist man offline, bricht das Plugin still ab:
keine Meldung, und die Nachricht zählt nicht für die Runde.

## Twitch-Freigabe

`moderator:manage:shoutouts`. Fehlt sie, steht ein Hinweis auf der
Seite; der Kanal muss dafür einmal neu verbunden werden.

## Rechte

- `AutoShoutout.Global.View` – Seite sehen
- `AutoShoutout.Global.Toggle` – ein- und ausschalten
- `AutoShoutout.Global.Reset` – Runde neu starten
- `AutoShoutout.Global.Settings` – Custom-Command ändern
- `AutoShoutout.Channels.Add` / `AutoShoutout.Channels.Delete` – Kanäle pflegen

## Was das Plugin speichert

Eine Tabelle `auto_shoutout_channels` mit den Kanälen und ihrem Stand
in der laufenden Runde. Sie wird beim Entfernen mitgelöscht.
