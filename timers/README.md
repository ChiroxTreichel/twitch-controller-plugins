# Timer

Wiederkehrende Nachrichten im Chat — **aber nur, wenn der Stream
läuft.** Das ist die wichtigste Bedingung: ein Timer, der in einen
leeren Chat postet, ist nur Müll im Verlauf.

## Vier Bedingungen

Ein Timer kommt dran, wenn **alle** vier erfüllt sind:

| Bedingung | Bedeutung |
| --- | --- |
| **Stream läuft** | ohne Stream passiert nichts, und die Uhr steht still |
| **Intervall** | frühestens so viele Minuten nach dem letzten Mal (5–120) |
| **Min. Zeilen** | so viele Chatzeilen müssen seitdem gekommen sein |
| **Titel und Spiel** | greift nur, wenn der Stream dazu passt (leer = egal) |

Die zwei Balken auf der Seite zeigen Zeit und Zeilen. Erst wenn beide
voll sind, ist der Timer dran — ohne die Anzeige rät man, warum er
schweigt.

### Warum das Intervall ab Streamstart zählt

Maßgeblich ist der **spätere** von beiden: letzter Post und
Streamstart. Ohne den Streamstart feuerte nach zwei Tagen Pause jeder
Timer in der ersten Minute des nächsten Streams — genau dann, wenn noch
niemand da ist.

Aus demselben Grund werden Zähler und Zeitpunkte bei jedem neuen Stream
zurückgesetzt. Die **Stelle in der Reihe** bleibt dabei stehen, sonst
begänne jeder Stream mit derselben Nachricht.

### Höchstens einer pro Takt

Es postet höchstens ein Timer je Durchlauf, und zwischen zwei Posts
liegen mindestens 30 Sekunden. Ohne das stünden nach einer langen Pause
drei Werbeblöcke untereinander im Chat.

## Filter auf Titel und Kategorie

| Feld | Vergleich |
| --- | --- |
| **Stream-Titel-Stichwörter** | kommagetrennt, **eines genügt**, Teiltreffer, Groß-/Kleinschreibung egal |
| **Aktuelles Spiel** | **exakt**, nur Groß-/Kleinschreibung egal |

Der Titel ist absichtlich großzügig: ein Timer für `Farming` soll auch
bei „Farming & Chill" greifen. Die Kategorie ist streng, damit
`Minecraft` nicht auf „Minecraft Dungeons" passt.

Beides kommt aus dem EventSub-Abo `channel.update`, das dieses Plugin
nachfordert — Titel und Kategorie ändern sich mitten im Stream.

## Nachrichten

Eine je Zeile. Sie rotieren beim Posten: beim ersten Mal die erste,
dann die zweite, und wieder von vorn.

## Als Befehl

Ist **Als Befehl erlauben** an, lässt sich der Timer zusätzlich mit
`!titel` abrufen — das braucht das Plugin **Chatbefehle**. Ohne es
laufen die Timer trotzdem, nur eben ohne `!titel`.

Der Befehl antwortet mit der **ersten** Nachricht, nicht mit der
nächsten aus der Reihe: wer ihn tippt, will die Auskunft, und die soll
nicht davon abhängen, wie oft der Timer heute schon gelaufen ist.

Der Titel muss dafür ein Befehlsname sein können — Kleinbuchstaben,
Ziffern, Bindestrich, Unterstrich. `7dso` geht, `Neuer Timer` nicht;
in dem Fall sagt die Seite es an Ort und Stelle.

## Hauptschalter

Oben auf der Seite und in der Seitenleiste. Aus heißt: kein Timer
postet, und es wird auch nichts gezählt. Die eingestellten Timer
bleiben stehen.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `Timers.Global.View` | Timer sehen |
| `Timers.Global.Edit` | Timer anlegen, ändern, löschen |
| `Timers.Global.Toggle` | Timer ein- und ausschalten |

## Voraussetzungen

Der Kanal muss mit den Chat-Freigaben verbunden sein, damit gepostet
werden kann. Nach der Installation einmal den Abo-Abgleich unter *Konto
→ Einstellungen → Kanal* auslösen — `channel.update` kommt erst dadurch
dazu.

Gepostet wird aus dem Hintergrundprozess (`cron.tick`). Läuft der
worker-Container nicht, passiert nichts.
