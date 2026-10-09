# Tools - Giveaway

Ein Giveaway im Chat mit Glücksrad im Overlay. Zuschauer holen sich per
Befehl Tickets, auf Knopfdruck erscheint das Rad mit allen, die ein
Ticket haben — jeder so groß wie seine Tickets. Dann wird Preis für
Preis gezogen und der Gewinner im Chat angesagt.

Der Menüpunkt heißt **Giveaway** und steht unter **Tools**, mit einem
Schalter zum Ein- und Ausschalten. **Aus ist die Vorgabe**: ein frisch
installiertes Giveaway verteilt keine Tickets, bevor Preise eingetragen
sind.

## Ablauf

1. **Preise eintragen** und mit den Pfeilen sortieren. Gezogen wird
   **von unten nach oben** — der oberste Preis kommt zuletzt.
2. **Einschalten** (Schalter im Menü). Ab jetzt gibt der Befehl Tickets,
   und die Werbung läuft, wenn sie eingerichtet ist.
3. **Gewinner ziehen**: die Teilnahme schließt, und im Overlay erscheint
   das Rad. Der Knopf heißt jetzt *Gewinner von „…“ ziehen*.
4. Drücken: das Rad dreht, bleibt beim Gewinner stehen, und im Chat
   steht `@name hat den Preis "…" gewonnen!!! Gratuliere!`. Nach einer
   kurzen Pause wird der Knopf zum nächsten Preis.
5. Sind alle Preise vergeben: **Giveaway beenden**. Die Tickets werden
   gelöscht, das Rad verschwindet, der Schalter geht aus. Die Liste der
   Gewinner bleibt zum Nachsehen stehen, bis die nächste Ziehung beginnt.

*Giveaway beenden* geht jederzeit — mitten in der Ziehung ist es ein
Abbruch.

Ausgelost wird **auf dem Server**, gewichtet nach Tickets. Das Rad zeigt
nur, was feststeht; zwei Browserquellen können also nicht verschieden
ausgehen.

## Der Befehl

| | |
| --- | --- |
| **Befehl** | Vorgabe `!ticket` |
| **Intervall** | ein Ticket alle so viele Minuten, je Zuschauer |
| **Extraticket für Abonnenten** | Abonnenten bekommen je Befehl zwei statt einem; das Gründer-Badge zählt mit |
| **Mehrfach gewinnen** | an: der Gewinner bleibt auf dem Rad, nur das Gewinnerticket ist weg. Aus: er verschwindet ganz |

Wer den Befehl schickt, bevor sein Intervall um ist, bekommt eine
Antwort mit der Wartezeit. Während der Ziehung ist die Teilnahme zu,
dafür gibt es eine eigene Antwort.

Der Befehl läuft über das Plugin **Chat - Befehle**: er steht dort in
`!befehle`, und dessen Hauptschalter gilt auch für ihn. Einen Namen,
den es dort schon gibt, lehnt das Giveaway ab — der würde sonst vor ihm
antworten.

## Texte und Platzhalter

Platzhalter wie bei den Alerts, `{{ username }}`, mit oder ohne
Leerzeichen. Unter jedem Feld steht, welche dort etwas bedeuten; ein
anderer fällt weg. Ein leeres Feld heißt: still.

| Platzhalter | wird zu |
| --- | --- |
| `{{ username }}` | `@login` — im Chat eine echte Erwähnung |
| `{{ tickets }}` | so viele Tickets hat er jetzt |
| `{{ added }}` | so viele kamen eben dazu (1 oder 2) |
| `{{ wait }}` | „4 Minuten“, „30 Sekunden“ |
| `{{ command }}` | der Befehl, mit `!` |
| `{{ interval }}` | das Intervall in Minuten |
| `{{ prize }}` | der gewonnene Preis |
| `{{ prizes }}` | alle Preise, durch Komma getrennt |

## Werbung im Chat

Braucht das Plugin **Chat - Timer**. Das kümmert sich um alles, was ein
Timer können muss: nur während des Streams, Abstand halten, genug
Chatzeilen abwarten. Das Giveaway sagt nur, *was* gepostet wird — und
nur, solange Tickets zu holen sind. Ohne Timer-Plugin läuft das
Giveaway trotzdem, nur ohne Erinnerung.

## Im Overlay

Ein Platz **Giveaway**, mittig, 760 Pixel breit. Er ist immer
angemeldet und leer, solange nicht gezogen wird. Wird die Browserquelle
mitten in der Ziehung neu geladen, steht das Rad sofort wieder da.

## Die Ansage

Die Verwaltungsseite sagt an, sobald das Rad steht. Ist sie zu, holt
der Hintergrundprozess die Ansage nach — dann kommt sie ein paar
Sekunden später, aber sie kommt. Angesagt wird genau einmal, auch wenn
beide es gleichzeitig versuchen.

Auf der Seite steht der Name des Gewinners erst nach der Ansage — wer
seinen Bildschirm im Stream zeigt, verrät ihn sonst, während das Rad
noch dreht.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `Giveaway.Global.View` | die Seite sehen |
| `Giveaway.Global.Edit` | Preise, Befehl, Texte und Werbung einstellen |
| `Giveaway.Global.Toggle` | ein- und ausschalten |
| `Giveaway.Global.Manage` | Gewinner ziehen, Giveaway beenden |

## Was das Plugin speichert

Zwei Tabellen: `giveaway_entries` (wer wie viele Tickets hat) und
`giveaway_winners`. Einstellungen und der Stand der Ziehung liegen im
Bereich `plugin:giveaway`. Beim Entfernen geht alles mit.

## Voraussetzungen

- Twitch-Controller ab Fassung 2.11.0
- **Chat - Befehle** ab 1.3.0
- optional **Chat - Timer** für die Werbung
