# Zwei Overlays: Main und Background – Entwurf

Stand: 2026-11-17. Ein kleiner Umbau, kurzer Entwurf.

## Worum es geht

Blur Chats liegt im Overlay *vor* dem Avatar der Streamerin. Damit Plätze auch
*hinter* dem Avatar liegen können, bekommt der Kern zwei feste Overlays:
**Main** (`/overlay`, wie bisher) und **Background** (`/overlay/background`).
In OBS kommt Background unter den Avatar, Main darüber.

## Ziele

- Pro Platz unter *Konto → Overlay → Plätze* wählen, ob er in Main oder
  Background liegt
- Plugins geben mit `'layer' => 'background'` im Haken `overlay.slots` einen
  Vorschlag vor. Fehlt die Angabe, gilt `main`.
- Beide Overlays haben dieselben Funktionen: Leitung, Live-Layout, `?view=`

## Nicht-Ziele

- Beliebig viele Overlays

## Anti-Ziele

- Bestehende Quellen (`/overlay`) dürfen nicht kaputtgehen. Was nicht als
  Background markiert ist, bleibt in Main.

## Geprüfte Wege

1. **Zwei feste Ebenen, Auswahl pro Platz.** Gewählt.
2. Beliebig viele Overlays: mehr, als gebraucht wird.
3. Nur `?view=` pro Platz: geht heute schon, aber dann gibt es keine
   Zuordnung in der Oberfläche und keine Vorgabe durch das Plugin.

## Entscheidungen

- `Bus::LAYERS = ['main', 'background']`. Die Einstellung `overlay_layers`
  ist eine JSON-Zuordnung Platz → Ebene und gewinnt gegen die Vorgabe des
  Plugins. `Bus::slots()` liefert pro Platz zusätzlich `layer`.
- Reihenfolge: Es bleibt die eine Liste `overlay_order`. Die Pfeile tauschen
  mit dem nächsten Nachbarn **derselben Ebene**. Die Anzeige ist nach Ebene
  gruppiert.
- Ebene wechseln → `Bus::invalidate()`, damit beide Quellen neu laden (ein
  Platz erscheint auf der einen Seite und verschwindet auf der anderen).
- `/overlay` zeigt die Main-Plätze, `/overlay/background` die
  Background-Plätze. Mit `?view=` wird über **alle** Plätze gewählt, so wie
  bisher. Eine leere Background-Seite ist erlaubt und bleibt durchsichtig.
- Auf der Overlay-Seite stehen beide Adressen zum Kopieren. Die
  `?view=`-Adresse pro Platz zeigt auf die Ebene des Platzes.
- Kern **2.17.0**, Doku in `docs/entwicklung.md` (Overlay-Abschnitt,
  Haken `overlay.slots`).
- Blur Chats **1.0.1**: `'layer' => 'background'`, verlangt
  `"core": ">=2.17.0"`, README-Einrichtung angepasst.
