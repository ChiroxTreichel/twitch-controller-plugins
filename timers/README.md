# Chat - Timer

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

Eine Eingabe je Nachricht, mit **Löschen** für die einzelne Zeile und
**Neue Nachricht** zum Anhängen. Beide sind Absende-Knöpfe im selben
Formular: die übrigen Eingaben gehen dabei nicht verloren, und es
braucht kein JavaScript — das alte System löste das mit JS.

Sie rotieren beim Posten: beim ersten Mal die erste, dann die zweite,
und wieder von vorn. Eine noch leere Zeile wird dabei übersprungen,
sonst setzte der Timer gelegentlich einen Durchgang aus.

Die **letzte** Nachricht lässt sich nicht löschen — ein Timer ohne
Nachricht kann nichts tun, und ein Formular, das sich selbst
unbrauchbar macht, wäre eine Falle.

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

## Timer aus anderen Plugins

Ein Plugin, das regelmäßig etwas in den Chat schreiben will, meldet
seinen Timer über den Haken **`timers.external`** an. Zählen, warten,
Abstand halten und posten macht dann dieser hier — nachgebaut werden
müsste sonst der ganze Betrieb.

```php
$hooks->on('timers.external', static function (array $timer) use ($app): array {
    $timer[] = [
        'id'               => 'music',   // fest, der Laufzeitstand hängt daran
        'title'            => 'music',   // steht so im Log
        'interval_minutes' => 30,
        'min_lines'        => 20,
        'enabled'          => true,
        'resolve'          => static fn (App $app): string => '…',
    ];

    return $timer;
});
```

Der Unterschied zu einem Timer aus der Liste: Ein solcher hat **keine
Nachrichtenliste**, sondern `resolve` — eine Funktion, die den Text
liefert, *wenn* der Timer dran ist. Eine leere Antwort heißt „gerade
nicht"; dann bleibt der Stand stehen und beim nächsten Takt wird wieder
gefragt. So kann ein Plugin von der Lage abhängig machen, ob und was es
sagt.

Der Haken wird bei **jeder Chatzeile** gefragt — die Antwort muss also
billig sein. Was nachzuschlagen ist, gehört in `resolve`.

Diese Timer stehen **nicht** in der Liste auf dieser Seite und lassen
sich hier nicht bearbeiten. Sie gehören dem Plugin, das sie anmeldet,
und werden dort eingestellt. Der Hauptschalter gilt trotzdem: aus ist
aus.

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
