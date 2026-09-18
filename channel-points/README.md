# Kanalpunkte

Kanalpunkt-Belohnungen anlegen, ändern – und je nach Stream-Titel und
Kategorie automatisch ein- und ausschalten.

`Stream → Kanalpunkte`

## Wozu

Eine Belohnung „Boss-Kampf übernehmen" gehört nicht in einen
Just-Chatting-Stream. Beim Umschalten der Kategorie denkt daran nur
niemand – und dann steht sie eine Stunde lang einlösbar im Bild.

Je Belohnung lassen sich vier Listen pflegen, alle mit Komma getrennt:

| Feld | Bedeutung |
| --- | --- |
| An, wenn der Titel enthält | Teilwort, Groß-/Kleinschreibung egal |
| An, wenn die Kategorie ist | genau dieser Name |
| Aus, wenn der Titel enthält | Teilwort |
| Aus, wenn die Kategorie ist | genau dieser Name |

Zwei Regeln:

* **Aus schlägt An.** Wer eine Sperrliste pflegt, will sie durchsetzen.
* **Kein Feld gefüllt heißt: nicht anfassen.** Sonst legte die bloße
  Installation jede Belohnung im Kanal um.

Sind Titel- und Kategoriefeld einer Zeile beide gefüllt, müssen beide
passen – wie bei den Timern.

Ohne laufenden Stream wird nichts geschaltet: Titel und Kategorie
stehen dann auf dem Stand des letzten Streams.

## Was Twitch nicht erlaubt

> „The custom reward's broadcaster must have created the reward using
> the same client ID that's used to update or delete the reward."

Lesen darf dieses System alle Belohnungen des Kanals. **Ändern und
Schalten nur die, die es selbst angelegt hat.** Was im Creator-Dashboard
entstanden ist, gehört dort hin – ein Schaltversuch darauf ergibt 403.

Solche Belohnungen stehen in der Liste als **fremd**. Ihre Felder sind
grau, nur die Bedingungen lassen sich eintragen (Wirkung haben sie
dann allerdings nicht). Der Knopf **Übernehmen** löscht die Belohnung
bei Twitch und legt sie identisch neu an – danach ist sie schaltbar.
Dabei gehen **Symbol und Einlöse-Historie verloren**; einen sanfteren
Weg bietet Twitch nicht.

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
die Belohnung bleibt mit einer lokalen Kennung in der Liste und lässt
sich mit **Bei Twitch anlegen** nachholen. Dasselbe greift, wenn ein
Übernehmen zwischen Löschen und Neuanlegen steckenbleibt.

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
