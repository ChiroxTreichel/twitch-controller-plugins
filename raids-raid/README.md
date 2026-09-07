# Raids – Raiden

Ein Knopf auf jeder Live-Kachel, der den Raid wirklich startet. Und
einer darüber, der ihn während des Vorlaufs wieder abbricht.

Braucht **Raids** — dort steht die Liste, wer gerade live ist. Dieses
Plugin bringt keine eigene Liste mit und keinen eigenen Reiter.

## „/raid name in den Chat" geht nicht

So fühlt es sich an, und genau so funktioniert es nicht: Twitch führt
Schrägstrich-Befehle nur im Chat selbst aus. Eine Nachricht über die
Schnittstelle ist Text — `/raid xyz` landete als sichtbare Zeile im
Chat, und geraidet würde niemand.

Der richtige Weg ist ein eigener Endpunkt: `POST helix/raids` startet,
`DELETE helix/raids` bricht ab. Was Twitch daraus macht, ist dasselbe
wie beim Befehl.

## Die 90 Sekunden

Twitch startet den Raid nicht sofort. Der Kanal bekommt neunzig
Sekunden Vorlauf, in denen die Zuschauer den Hinweis sehen und
mitkommen können — erst danach wechseln sie wirklich.

Diese neunzig Sekunden sind der Grund für den **Abbruch-Knopf**. Ohne
ihn wäre ein Fehlgriff endgültig, und beim Roulette klickt man einmal
und bekommt einen Namen, den man sich nicht ausgesucht hat.

Der Knopf steht nur da, solange ein Raid im Vorlauf sein *kann*. Ob
wirklich einer läuft, wird **geraten und nicht gefragt**: Twitch hat
keinen Endpunkt dafür. Gemerkt wird der Zeitpunkt des Starts, und
daraus folgt das Fenster — zwei Minuten, etwas mehr als die neunzig
Sekunden, weil die Uhr des Servers und die Anzeige im Browser nicht
dieselbe ist. Ein Knopf, der noch da ist, obwohl es nichts mehr
abzubrechen gibt, ist harmlos: Twitch antwortet dann mit einem Fehler,
und der steht auf der Seite.

## Keine Rückfrage vor dem Raid

Sonst wäre eine Pflicht — Raiden schickt die eigenen Zuschauer weg.
Hier nicht: es gibt neunzig Sekunden und einen Knopf, der sie
zurückholt. Eine Rückfrage wäre ein Klick für etwas, das man ohnehin
noch abbrechen kann.

## Zusammen mit dem Roulette

Ist **Raids – Roulette** ebenfalls installiert, drückt es diesen Knopf
selbst: es dreht, landet auf einer Kachel und startet den Raid auf den
Gewinner. Das Roulette findet den Knopf über `data-raid-start`. Ohne
dieses Plugin bleibt es beim Hervorheben.

## Rechte

`RaidsRaid.Global.Start` — darf raiden und abbrechen. Ein eigenes Recht
für den Abbruch gibt es nicht: das wäre ein Schloss an der Tür, durch
die man schon gegangen ist.

## Twitch-Freigabe

`channel:manage:raids`. Fehlt sie, ist der Knopf zu sehen und
abgeschaltet — verstecken wäre schlechter: dann fehlte auf der Seite
etwas, von dem man gelesen hat, und niemand wüsste, warum.

## Was das Plugin speichert

Eine Zahl: `started_at` im Bereich `plugin:raids-raid`, der Zeitpunkt
des letzten Starts. Keine Tabelle. Wen man raiden kann, weiß Raids; ob
ein Raid läuft, weiß Twitch.
