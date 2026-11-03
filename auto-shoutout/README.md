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
- `AutoShoutout.Channels.Add` / `AutoShoutout.Channels.Delete` – Kanäle pflegen

## Was das Plugin speichert

Eine Tabelle `auto_shoutout_channels` mit den Kanälen und ihrem Stand
in der laufenden Runde. Sie wird beim Entfernen mitgelöscht.
