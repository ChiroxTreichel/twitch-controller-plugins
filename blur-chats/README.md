# Overlay - Blur Chats

Deckt Stellen im Bild ab, **je Twitch-Kategorie**: im einen Spiel den
Ingame-Chat unten links, im anderen die Freundesliste oben rechts.
Wechselt die Kategorie, springen die Bereiche im Overlay von selbst um —
ohne Szenenwechsel und ohne Neuladen der Browserquelle.

Der Menüpunkt heißt **Blur Chats** und steht unter **Stream**, mit einem
Schalter zum Ein- und Ausschalten. **An ist die Vorgabe**: solange keine
Bereiche angelegt sind, ist ohnehin nichts zu sehen.

## Milchglas, kein echter Weichzeichner

Wichtig vorweg: das Plugin **verwischt das Bild nicht**. Eine
Browserquelle sieht nicht, was in OBS unter ihr liegt — sie kann es also
auch nicht weichzeichnen. Stattdessen legt sie eine **fast deckende
Fläche** darüber, mit weichem Rand und leichter Körnung, wie Milchglas.

Damit der Chat wirklich unlesbar ist, die **Deckkraft hoch** lassen
(Vorgabe 90 %). Bei niedriger Deckkraft scheint der Text durch.

## Einrichten

1. In OBS eine **Browserquelle** anlegen mit der Adresse
   `https://<deine-domain>/overlay?view=blur-chats`.
2. Die Quelle **genau so groß** machen wie die Bühne unter
   *Konto → Overlay* (Vorgabe 1920 × 1080) und **über das Spiel** legen.
   Kleiner schneidet ab, größer lässt rechts und unten Platz — die
   Bereiche sitzen dann nicht, wo sie sollen.
3. Einmal anmelden: Rechtsklick auf die Quelle, *Interagieren*, dort
   einloggen. OBS merkt sich die Anmeldung.

## Bereiche anlegen

1. Unter **Stream → Blur Chats** eine Kategorie hinzufügen — über die
   Suche oder mit **Aktuelle Kategorie übernehmen**, wenn das Spiel
   gerade läuft. Die laufende Kategorie ist in der Liste markiert.
2. Am besten zuerst einen **Screenshot** hinterlegen: Datei wählen oder
   einfach **Strg+V** auf der Seite. Er liegt im Editor unter der Fläche
   und hilft beim Treffen. Er ist **nur im Editor** zu sehen, nie im
   Overlay.
3. Bereiche mit der Maus **verschieben**, am Punkt unten rechts die
   **Größe** ändern. Darunter steht jeder Bereich als eigene Zeile:
   Name, X, Y, Breite, Höhe, Farbe, Deckkraft, Rand und Ecken. Mit
   **+ Bereich** kommt ein neuer dazu, mit **–** geht einer weg.
4. **Speichern.** Läuft die Kategorie gerade, ist die Änderung sofort im
   Overlay.

Alle Werte sind **Pixel der Bühne**, von oben links gezählt. Ändert
man später die Bühnengröße, bleiben die Zahlen stehen — dann die
Bereiche einmal nachziehen.

Eine Kategorie ohne Bereiche, ein Stream ohne Kategorie oder der
Schalter auf aus: dann ist der Platz im Overlay leer.

## Voraussetzungen

- Twitch-Controller ab **Kern 2.16.0** — der Kern kennt ab dort die
  laufende Kategorie und meldet Wechsel.
- **Keine neuen Twitch-Rechte.** Die Kategorie kommt aus dem Kern, die
  Suche nutzt die vorhandene Anmeldung des Kanals.
- Kein anderes Plugin nötig.

## Rechte

- **Anzeigen** — die Seite sehen
- **Bearbeiten** — Kategorien, Bereiche und Screenshots ändern
- **An/Aus** — Blur Chats ein- und ausschalten
