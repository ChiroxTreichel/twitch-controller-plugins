# Chat - Kanalpunkte

Kanalpunkt-Belohnungen anlegen, ändern – und je nach Stream-Titel und
Kategorie automatisch ein- und ausschalten.

`Chat → Kanalpunkte`

## Wozu

Eine Belohnung „Boss-Kampf übernehmen" gehört nicht in einen
Just-Chatting-Stream. Beim Umschalten der Kategorie denkt daran nur
niemand – und dann steht sie eine Stunde lang einlösbar im Bild.

Die Belohnungen stehen als Kachelgitter. Auf der Kachel selbst nur
das Nötige: Farbe, Name, Kosten, Plaketten – und ein **Stift**, der
den Dialog öffnet. Warum eine fremde Belohnung nicht schaltbar ist,
steht als Hinweistext an der Plakette „fremd", nicht als eigene Zeile.

Im Dialog steckt alles, was diese eine Belohnung betrifft: die Felder,
die Bedingungen, und ganz unten abgesetzt **Entfernen** und
**Wurde bei Twitch gelöscht, jetzt neu erstellen**.

Über dem Gitter und über der Mitgliederliste einer Gruppe steht je ein
**Suchfeld**. Es filtert im Browser, ohne Neuladen – und es sucht nur:
eine ausgeblendete Zeile bleibt angehakt. Ohne JavaScript erscheint es
gar nicht erst.

Darin, farbig getrennt, zwei Blöcke:

| Block | Feld | Bedeutung |
| --- | --- | --- |
| **+ An, wenn …** (grün) | Titel enthält | Teilwort, Groß-/Kleinschreibung egal, mehrere mit Komma |
| | Kategorie ist | genau dieser Name, mehrere mit Komma |
| **− Aus, wenn …** (rot) | Titel enthält | eine Ausnahme je Zeile, „+“ hängt eine an |
| | Kategorie ist | eine Ausnahme je Zeile, „+“ hängt eine an |

Die Aus-Seite ist eine Zeilenliste, weil sich Ausnahmen sammeln – und
weil ein Name dann selbst ein Komma enthalten darf.

Zwei Regeln:

* **Aus schlägt An.** Wer eine Sperrliste pflegt, will sie durchsetzen.
* **Kein Feld gefüllt heißt: nicht anfassen.** Sonst legte die bloße
  Installation jede Belohnung im Kanal um.

Sind Titel- und Kategoriefeld der An-Seite beide gefüllt, müssen beide
passen – wie bei den Timern.

Ohne laufenden Stream wird nichts geschaltet: Titel und Kategorie
stehen dann auf dem Stand des letzten Streams.

## Gruppen

`Chat → Kanalpunkte → Gruppen`

Eine Gruppe fasst mehrere Belohnungen zusammen und trägt dieselben
vier Bedingungsfelder. Sie **ersetzt** die Bedingungen der einzelnen
Belohnung **nicht** – sie kommt dazu:

```
Gruppe "Nur beim Zocken":  An, wenn Kategorie = Minecraft
Belohnung "Boss-Kampf":    Aus, wenn Titel enthält "Tutorial"

→ beides gilt, und ein Aus von irgendwo schaltet ab.
```

**Wer aus sagt, gewinnt.** Egal ob die Regel an der Belohnung oder an
einer ihrer Gruppen hängt. Sagt niemand aus und wenigstens einer an,
ist sie an. Sagt niemand etwas, bleibt sie unangetastet.

Jede Gruppe hat einen **Schalter**. Aus heißt: *diese Regel zählt
gerade nicht mit* – nicht, dass die Belohnungen darin ausgehen. Die
richten sich dann nach ihren eigenen Bedingungen. So lässt sich eine
Gruppe für einen Abend beiseitelegen, ohne sie zu löschen.

Schaltet eine Gruppe eine Belohnung ab, steht ihr Name auf der Kachel
der Belohnung. Sonst sucht man die Bedingung dort, wo keine steht.

Bekommt eine Belohnung eine neue Kennung (beim Neuanlegen) oder wird
sie entfernt, ziehen die Mitgliederlisten mit.

## Was Twitch nicht erlaubt

> „The custom reward's broadcaster must have created the reward using
> the same client ID that's used to update or delete the reward."

Lesen darf dieses System alle Belohnungen des Kanals. **Ändern und
Schalten nur die, die es selbst angelegt hat.** Was im Creator-Dashboard
entstanden ist, gehört dort hin – ein Schaltversuch darauf ergibt 403.

Solche Belohnungen stehen im Gitter als **fremd**. Ihre Felder sind
grau, nur die Bedingungen lassen sich schon eintragen – Wirkung haben
sie, sobald die Belohnung von hier aus angelegt ist.

Der Weg dorthin geht über deine Hand, nicht über dieses System:

1. Belohnung im Creator-Dashboard löschen.
2. Hier auf **Wurde bei Twitch gelöscht, jetzt neu erstellen** drücken.

Dann wird sie mit denselben Werten und Bedingungen neu angelegt –
diesmal als eigene, also schaltbar. **Dieses System löscht nichts**,
und zwar nicht aus Vorsicht, sondern weil Twitch es bei einer fremden
Belohnung gar nicht zuließe. Steht sie beim Drücken noch im Dashboard,
lehnt Twitch wegen des doppelten Namens ab und sagt das auch.

Symbol und Einlöse-Historie sind mit deinem Löschen weg – die lassen
sich über die Schnittstelle nicht mitnehmen.

Ein **Symbol** lässt sich über die Schnittstelle ohnehin weder setzen
noch ändern – es gibt kein Feld dafür. Selbst angelegte Belohnungen
tragen das Vorgabe-Symbol.

## Kanalpunktbelohnungen laden

Holt alle Belohnungen des Kanals von Twitch und legt sie hier ab.
Bereits eingetragene Bedingungen bleiben dabei erhalten – die kennt
Twitch nicht, und ein Laden, das sie wegwirft, wäre eine Falle.

Was bei Twitch nicht mehr existiert, fällt aus der Liste. Was nur hier
steht, bleibt.

## Nur hier vorhanden

Schlägt das Anlegen bei Twitch fehl, geht die Eingabe nicht verloren:
die Belohnung bleibt mit einer lokalen Kennung im Gitter und lässt
sich mit **Bei Twitch anlegen** nachholen.

## Freigabe

`channel:manage:redemptions` – deckt Lesen und Schreiben ab. Fehlt sie,
steht oben auf der Seite ein Hinweis; der Kanal muss einmal neu
verbunden werden.

## Was wann passiert

* `cron.tick` – prüft die Bedingungen und schaltet, **nur bei einer
  Änderung**. Gibt es keine Belohnung mit Bedingungen, passiert gar
  nichts.
* `channel.update` – Titel- und Kategoriewechsel mitten im Stream.
  Ohne dieses Abo fiele ein Wechsel erst beim nächsten Rückfall über
  Helix auf, also bis zu fünf Minuten später.
* `stream.online` / `stream.offline` – abonniert der Kern schon.
