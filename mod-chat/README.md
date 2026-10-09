# Stream - Moderatorenchat

Ein Chat nur für das Team. Alle, die sich im Twitch-Controller anmelden
können, schreiben sich hier streambezogene Nachrichten — „Raid kommt
gleich“, „XY ist wieder da“, „Ton ist weg“. Ohne Discord daneben und
ohne dass der Twitch-Chat mitliest.

Der Menüpunkt heißt **Chat** und steht unter **Stream**.

## Live

Neue Nachrichten erscheinen von selbst, ohne die Seite neu zu laden —
die Seite fragt alle zwei Sekunden nach. Abschicken geht ebenso ohne
Seitenwechsel:

- **Enter** schickt ab, **Umschalt + Enter** macht eine neue Zeile
- Wer hochgescrollt hat, um nachzulesen, wird nicht weggerissen — es
  erscheint ein Knopf *Neue Nachrichten*
- Steht die Seite in einem Reiter im Hintergrund, zählt der Titel die
  ungelesenen Nachrichten mit: `(3) Chat`
- Adressen in Nachrichten sind anklickbar

## Als Dock in OBS

Für den Streamer zum Mitlesen gibt es eine schmale Ansicht ohne Menü und
**ohne Eingabefeld**:

```
https://<deine-domain>/stream/chat?compact=1
```

In OBS unter *Ansicht → Docks → Eigenes Browser-Dock* eintragen. Ein
solches Dock teilt die Anmeldung mit dem Browser; beim ersten Mal führt
es auf die Anmeldung und danach zurück in den Chat.

## Wer darf

Jeder, der angemeldet ist. Das Plugin bringt **keine eigenen Rechte**
mit: ein Teamchat, in dem ein Teil des Teams nicht mitlesen darf, ist
keiner.

Nachrichten lassen sich weder bearbeiten noch löschen. Was dasteht,
haben die anderen schon gelesen.

## Was das Plugin speichert

Eine Tabelle, `mod_chat_messages`, mit Absender, Text und Zeitpunkt.
Nach **30 Tagen** räumt der Hintergrundprozess eine Nachricht ab. Der
Name des Absenders steht in jeder Zeile mit — wer das Team verlässt,
behält in alten Nachrichten seinen Namen.

Beim Entfernen des Plugins geht die Tabelle mit.

## Voraussetzungen

- Twitch-Controller ab Fassung 2.11.0
- keine Twitch-Berechtigungen, keine weiteren Plugins
