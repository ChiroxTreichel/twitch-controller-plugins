# Tools - Umfragen

Umfragen für die Community, übernommen aus `umfragen.talutah.de`.
Zuschauer melden sich mit Twitch an und stimmen auf einer eigenen Seite
ab; das Team sieht den Zwischenstand, das Overlay zeigt ihn live.

Der Menüpunkt heißt **Umfragen** und steht unter **Tools**.

## Eine Umfrage

| | |
| --- | --- |
| **Überschrift, Beschreibung** | die Frage und was man dazu wissen muss |
| **Einträge** | je ein Feld, mit „+ Eintrag“ und „–“; Enter legt die nächste Zeile an. Doppelte (ohne Groß/Klein) fallen weg |
| **Genau so viele wählen** | man muss **exakt** so viele ankreuzen, wie hier steht |
| **Ende** | als Dauer (Tage, Stunden, Minuten) oder fester Zeitpunkt |
| **Eigene Vorschläge** | Zuschauer dürfen Einträge hinzufügen |
| **Ankündigen** | je einzeln: Discord neu / Ergebnis, Twitch-Chat neu / Ergebnis |

Bis zum Ende kann jeder seine Wahl ändern. Das **Ergebnis** sehen die
Zuschauer erst danach — ein Zwischenstand lenkt die, die noch
abstimmen. Das Team sieht ihn in der Liste.

Laufende Umfragen lassen sich **jetzt beenden** und **löschen**.

## Die Seite für Zuschauer

```
https://<deine-domain>/polls/<nummer>
```

Abstimmen darf jeder mit Twitch-Konto. Die Anmeldung läuft über die
Twitch-App dieser Installation; gespeichert wird nur Name und Kennung,
in einem signierten Cookie. Ohne Anmeldung sieht man Frage und Einträge
schon — der Knopf führt zu Twitch und danach zurück zur Umfrage.

## Ankündigen

**Discord** braucht einen Webhook unter *Einstellungen der Umfragen*
(auch aus der Plugin-Liste erreichbar), mit Testknopf. Die Texte sind die
des alten Systems:

- *„X hat eine neue Umfrage erstellt.“* mit Überschrift, Beschreibung,
  Link und „bis morgen um 18:00 abstimmen“
- *„Die Umfrage … wurde beendet“* mit dem Gewinner — bei Gleichstand
  mit „je“, bei mehr als einer Wahl als „Platz 1, Platz 2, …“

**Twitch-Chat**: dieselben zwei Anlässe, kurz in einer Zeile mit Link.

Das Ergebnis sagt der Hintergrundprozess an, sobald die Zeit um ist.
Angesagt wird genau einmal — auch wenn das Senden scheitert; der Grund
steht dann im Log.

## Im Overlay

In der Liste setzt **Im Overlay zeigen** eine Umfrage in den Platz
**Umfragen** — eine zur Zeit, die neue verdrängt die alte. Der Stand
kommt mit jeder Stimme, nicht alle zehn Sekunden wie früher.

Stelle und Breite stehen in den Einstellungen. Wer die Umfrage wie
früher in einer eigenen Browserquelle will, hängt `?view=polls` an die
Adresse des Overlays.

## Was anders ist als früher

- **Keine eigene Twitch-App** und kein unsigniertes Cookie mehr — wer
  es änderte, stimmte als jemand anderes ab.
- **Keine festen Admins, keine Share-Codes**: wer verwalten darf, regeln
  die Rechte. Das ganze Team sieht alle Umfragen.
- **Ein Webhook für den Kanal** statt einem je Umfrage.
- **Ein Overlay für den Kanal** statt einem je Admin.
- **Stimmen in der Datenbank** statt in JSON-Dateien — zwei, die
  gleichzeitig abstimmen, überschreiben sich nicht mehr.
- Die alten Umfragen sind **nicht** übernommen.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `Polls.Global.View` | Umfragen und Zwischenstand sehen |
| `Polls.Global.Create` | neue Umfragen anlegen |
| `Polls.Global.Edit` | beenden, ins Overlay setzen, Discord und Overlay einstellen |
| `Polls.Global.Delete` | Umfragen löschen |

## Was das Plugin speichert

Drei Tabellen: `polls_polls`, `polls_options`, `polls_votes`. Webhook
und Overlay liegen im Bereich `plugin:polls`. Beim Entfernen geht alles
mit.

## Voraussetzungen

- Twitch-Controller ab Fassung 2.11.0
- für den Twitch-Chat: ein Kanal mit Chat-Freigaben
