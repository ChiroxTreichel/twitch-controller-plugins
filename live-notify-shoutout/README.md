# Live-Benachrichtigung – Shoutout

Löst einen Twitch-Shoutout aus, wenn ein beobachteter Kanal live geht.
Ein Ziel wie Discord und Chat, nur ohne Text: Twitch baut den Shoutout
selbst und zeigt ihn im Chat als eigenes Element.

Braucht **Live-Benachrichtigung – Discord ab 1.0.0**: dort liegen die
Kanalliste, die Live-Erkennung und die Einhängepunkte.

## Eigener Haken, eigene Entscheidung

Im alten System ging der Shoutout nur hinaus, **nachdem** die
Chat-Nachricht gelungen war. Das war eine stille Kopplung zweier Dinge,
die nichts miteinander zu tun haben: wer nur den Shoutout wollte, bekam
keinen, solange kein Chat-Ziel angehakt war.

Hier ist der Shoutout ein eigenes Ziel mit eigenem Haken. Ob Chat
angehakt ist, spielt keine Rolle.

## Nichts einzustellen

Ein Shoutout hat keinen Text. Darum hat dieses Plugin auch keinen
Eintrag unter *Plugins → Einstellungen* — der Haken in der Kanalzeile
ist die ganze Bedienung.

## Zwei Bedingungen von Twitch

Beide sind die häufigsten Gründe für „es passiert nichts", und beide
sind kein Fehler dieser Anwendung:

- **Der eigene Kanal muss live sein.** Ein Shoutout ist ein Element im
  laufenden Stream. Gefragt wird darum vorher, damit im Log „eigener
  Kanal ist nicht live" steht und nicht eine Ablehnung von Twitch, die
  man erst nachschlagen muss.
- **Es gibt Sperrfristen:** zwei Minuten zwischen zwei Shoutouts,
  sechzig Minuten für denselben Kanal. Twitch antwortet darauf mit
  `429`, und die Meldung im Log sagt genau das — wer sie als Störung
  liest, sucht an der falschen Stelle.

Nachzulesen unter `docker compose logs -f web worker`.

## Twitch-Freigabe

`moderator:manage:shoutouts`. Fehlt sie, meldet das Ziel das von sich
aus: der Grund steht oben auf der Kanalseite, statt dass der Haken
stillschweigend nichts tut. Der Kanal muss dafür einmal neu verbunden
werden.

Ausgelöst wird als **Moderator des eigenen Kanals** — Twitch verlangt,
dass Token und `moderator_id` zusammenpassen, und beides ist hier der
Kanalinhaber.

## Rechte

Keine eigenen. Wer den Haken je Kanal setzen darf, entscheidet das
Basis-Plugin mit `LiveNotify.Global.Edit`.

## Was das Plugin speichert

Nichts. Keine Tabelle, keine Einstellung.

Der Haken in der Kanalzeile gehört dem Basis-Plugin: wird dieses Plugin
entfernt, bleibt er stehen und wirkt nach einer Neuinstallation sofort
wieder.
