# Blur Chats – Entwurf

Stand: 2026-11-17

## Worum es geht

Ein Overlay-Plugin, das pro Twitch-Kategorie Stellen im Bild abdeckt, z. B.
Ingame-Chat oder Killfeed, damit man sie im Stream nicht lesen kann.
Wechselt die Kategorie, wechseln die Abdeckungen sofort mit, ohne dass jemand
etwas anfassen muss.

Damit das Plugin die Kategorie kennt, bekommt der **Kern** eine allgemeine
Kategorie-Erkennung, die jedes Plugin nutzen kann.

## Ziele

- Pro Kategorie beliebig viele Bereiche, jeder mit Stelle, Größe, Farbe,
  **Deckkraft** und weichem Übergang am Rand
- Umschalten beim Kategoriewechsel ohne Neuladen der Browserquelle
- Unabhängig von OBS: das Ganze ist ein normaler Platz im bestehenden Overlay

## Nicht-Ziele

- **Echtes Weichzeichnen des Spielbilds.** Eine Browserquelle sieht nicht, was
  in OBS unter ihr liegt. Geplant ist eine Milchglas-Abdeckung, die nach
  Unschärfe aussieht. Das ist so abgesprochen.
- Steuerung von OBS (Filter, Szenen, obs-websocket)
- Bereiche, die sich im Bild bewegen oder dem Spiel folgen

## Anti-Ziele

- Der Kategoriewechsel darf nicht als Eintrag im Aktivitäten-Feed landen und
  keine Alerts auslösen
- Die Browserquelle darf beim Umschalten nicht neu laden (kein Flackern)

## Geprüfte Wege

**Abdeckung:**
1. *OBS zeichnet weich, das Plugin liefert die Maske.* Damit wäre die
   Unschärfe echt, aber man braucht ein OBS-Plugin und muss es einmal
   einrichten. **Verworfen:** es soll ohne OBS gehen.
2. *Milchglas-Fleck im Overlay.* **Gewählt.**

**Kategorie-Erkennung im Kern:**
1. *EventSub `channel.update`.* Twitch meldet jeden Wechsel sofort, dafür ist
   keine zusätzliche Berechtigung nötig. **Gewählt.**
2. *Regelmäßig nachfragen.* Bis zu eine Minute Verzögerung und ständig
   API-Aufrufe. **Verworfen.**

## Teil 1: Kern (`/docker/overlay`)

- In `core/Twitch/EventSub.php::desired()` kommt
  `channel.update`, Version `2`, Bedingung `broadcaster_user_id`, dazu.
  Bestehende Installationen bekommen das Abo beim nächsten Abgleich. Der
  Builder prüft, wann dieser Abgleich läuft, und stößt ihn beim Update an,
  falls nötig.
- **Fund beim Planen:** Das Plugin *Timer* abonniert `channel.update` schon
  selbst über `core.eventsub.subscriptions` und liest es aus
  `core.event.stored`. Die Ereignisse liegen also bereits in `events` (live
  bisher 40 Stück). Im Feed tauchen sie nicht auf, weil der Presenter
  unbekannte Typen ausblendet. Würde der Kern den Typ wie den Chat an
  `events` vorbeischicken, wären die Timer-Filter kaputt. Deshalb **bleibt
  der Weg über `events->store()` unverändert**. Der Kern merkt sich nach dem
  Speichern zusätzlich die Kategorie. Das Anti-Ziel ist trotzdem erfüllt:
  kein Eintrag im Feed, keine Alerts. Doppelte Abos fasst
  `EventSub::desired()` schon zusammen.
- Gemerkt wird in den Einstellungen: `twitch_category_id`,
  `twitch_category_name` und `twitch_title`, dazu der Zeitpunkt.
- Neue Methode, z. B. `$app->twitch->category(): array{id, name}`. Ist noch
  nichts bekannt, fragt sie einmal über Helix `channels` nach und merkt
  sich das Ergebnis.
- Aktiv nachgefragt wird außerdem bei `stream.online` und **stündlich im
  `cron.tick` als Absicherung**, falls ein Webhook verloren geht.
  *(Meine Entscheidung.)*
- Neuer Haken **`core.category.changed`** (dispatch) mit
  `(string $id, string $name, string $previousId)`. Er feuert nur, wenn sich
  die ID wirklich geändert hat, also nicht bei einem reinen Titelwechsel.
- Den Haken in die Hakenliste in `docs/entwicklung.md` eintragen.
- Kern-Version → **2.16.0**.

## Teil 2: Plugin `blur-chats`

**Manifest:** Slug `blur-chats`, Name **„Overlay - Blur Chats“**,
`requires.core >= 2.16.0`, Tags `overlay`, `stream`. Es braucht kein anderes
Plugin.

**Rechte:** `BlurChats.Global.View`, `.Edit`, `.Toggle`

**Menü:** ein eigener Eintrag mit An/Aus-Schalter wie beim Giveaway. Ist der
Schalter aus, bleibt der Platz leer. *(Meine Entscheidung: Gruppe „Stream“.)*

**Daten:** eine Tabelle für die Bereiche:

| Feld | Bedeutung |
|------|-----------|
| `category_id`, `category_name` | Twitch-Kategorie (Name nur zur Anzeige) |
| `label` | eigener Name, z. B. „Ingame-Chat“ |
| `x`, `y`, `w`, `h` | **Pixel** auf der Overlay-Fläche (`overlay_width` × `overlay_height`, z. B. 1920×1080) – so festgelegt vom Benutzer; ändert sich die Auflösung, bleiben die Pixelwerte stehen |
| `color` | Farbe der Abdeckung, Vorgabe dunkles Grau |
| `opacity` | Deckkraft 0–100 %, Vorgabe 90 |
| `feather` | Breite des weichen Rands in px, Vorgabe 24 |
| `radius` | Eckenrundung in px, Vorgabe 12 |
| `sort` | Reihenfolge |

**Verwaltungsseite:**
- Oben eine Liste der Kategorien, die Bereiche haben. Hinzufügen geht über
  eine Kategoriesuche (eigene kleine Route über Helix `search/categories`,
  damit Streaminfo nicht Pflicht ist). Dazu ein Knopf **„Aktuelle Kategorie
  übernehmen“**, der die Kategorie vom Kern nimmt.
- Die aktuell laufende Kategorie ist markiert.
- Pro Kategorie ein **Editor**:
  - eine Fläche im Seitenverhältnis des Overlays (`overlay_width` ×
    `overlay_height`)
  - Bereiche lassen sich mit der Maus verschieben und an den Ecken in der
    Größe ändern, die Vorschau zeigt Farbe, Deckkraft und Rand live
  - darunter jeder Bereich als **eigene Zeile** mit Name, X/Y/Breite/Höhe in px, Farbe,
    Deckkraft-Regler, Rand und Löschknopf, plus „+ Bereich“ (siehe
    Speicher-Notiz *Listen als Einzelzeilen*)
- *Hilfe zum Positionieren:* Man kann pro Kategorie einen Screenshot als
  Hintergrund im Editor hinterlegen. Er ist **nur im Editor zu sehen**, nie
  im Overlay. *(Vom Benutzer gewünscht. Builder prüft, ob der Kern-Upload
  passt, sonst eigene Ablage im Plugin.)*

**Overlay:**
- Ein Platz `blur-chats` mit Position `fill`, er deckt also die ganze Fläche ab
- `state.js` liefert beim Laden die Bereiche der aktuellen Kategorie
  (Vorbild: das Giveaway)
- Bei `core.category.changed`, beim Speichern und beim Schalten schickt das
  Plugin über den Overlay-Bus die neue Bereichsliste
- Darstellung je Bereich: absolut positioniertes `div` in Pixeln (auf der Overlay-Bühne) mit
  Farbe und Deckkraft, weicher Rand über `filter: blur(<feather>px)` am
  Element selbst und leichte Körnung als SVG-Rauschen (Data-URI) für den
  Milchglas-Eindruck
- Beim Umschalten blenden die Bereiche in etwa 300 ms über
- Kategorie ohne Bereiche oder Schalter aus: nichts zu sehen

## Dem Builder überlassen

- Genaue Klassen- und Dateiaufteilung nach dem Muster der bestehenden Plugins
- Wie der EventSub-Abgleich für Bestandsinstallationen angestoßen wird
- Einzelheiten der Drag-/Resize-Logik im Editor (ohne externe Bibliothek)
- Texte in `lang/de.json` und `lang/en.json`, README fürs Plugin
