# Twitch-Alerts

Alerts für **Follows, Bits, Subs, geschenkte Subs, Prime-Subs und
Raids**. Je Fall ein eigener Text, ein eigenes Video, ein eigener Ton
und eine eigene Dauer.

Braucht das Plugin **Alerts** — das wird bei der Installation
mitinstalliert.

## Die sechs Reiter

| Reiter | Fälle |
| --- | --- |
| **Follows** | ein Fall |
| **Bits** | Stufen nach Betrag: „ab 100 Bits" |
| **Subs** | Erster Sub, Resub, Resub mit Streak |
| **Gifted-Subs** | ein Abo / mehrere, jeweils auch anonym |
| **Prime-Subs** | Erster Sub, Resub, Resub mit Streak |
| **Raids** | Stufen nach Zuschauerzahl: „ab 10 Raider" |

## Stufen

Bits und Raids arbeiten mit Stufen. Es greift die **höchste Stufe,
deren Mindestanzahl erreicht ist** — eine Stufe ab 1 fängt alles auf,
und darüber legt man an, was sich lohnt:

```
ab    1 Bits  →  kleines Video
ab  100 Bits  →  größeres Video, längere Dauer
ab 1000 Bits  →  das Besondere
```

## Platzhalter

Im Text stehen Platzhalter, die beim Anzeigen ersetzt werden:

| Platzhalter | wo |
| --- | --- |
| `{{ username }}` | überall |
| `{{ amount }}` | Bits, Raids, Gifted-Subs |
| `{{ message }}` | Bits |
| `{{ receiver }}` | Gifted-Subs |
| `{{ totalsubs }}` | Subs, Prime-Subs — Monate gesamt |
| `{{ consecutive }}` | Subs, Prime-Subs — Monate in Folge |
| `{{ tier }}` | Subs, Gifted-Subs |

Fehlt ein Wert, verschwindet der Platzhalter. Ein `{{ tier }}` mitten
im Stream sieht nach Fehler aus, ein fehlendes Wort nach Absicht.

## Video und Ton

Ins Feld kommt eine Adresse: ein eigener Pfad wie
`/uploads/alerts/sub.webm` oder eine vollständige `https://`-Adresse.
Die Datei muss auf dem Server liegen — der Knopf neben dem Feld setzt
nur den Namen ein, er lädt nichts hoch.

Das Video läuft in Schleife, bis die Dauer um ist. Ein kurzer Clip
beendet damit keinen langen Alert mit einem Standbild.

## Nach der Installation

1. Unter *Konto → Plugins* **aktivieren**
2. Unter *Konto → Einstellungen* auf **Abos abgleichen** klicken —
   sonst schickt Twitch die Events nicht
3. Unter *Anzeigen → Alerts* je Reiter Texte und Dateien eintragen
4. **Test senden** und in OBS nachsehen

Die Vorgabetexte sind schon eingetragen; nach der Installation
funktionieren die Alerts also sofort, nur ohne Video und Ton.

## Was mit Twitch zusammenhängt

Ein geschenktes Abo meldet Twitch **zweimal** — als „Abo" und als
„geschenktes Abo". Nur der zweite Weg wird zum Alert, sonst erschienen
zwei für ein Ereignis.

Bei einem Resub schickt Twitch die Streak nur, wenn der Zuschauer das
Teilen erlaubt hat. Fehlt sie, gilt der Fall *Resub* statt *Resub mit
Streak* — ein Text „seit 0 Monaten dabei" wäre falsch.

## Voraussetzungen

- Twitch-Controller ab Fassung 2.0.0
- Plugin **Alerts** ab 1.0.0 (wird mitinstalliert)
