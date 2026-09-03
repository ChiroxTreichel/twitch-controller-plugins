# Beispiel-Plugin

Ein Plugin ohne jeden Nutzen — dafür ist jede Zeile kommentiert. Es
zeigt an einem lauffähigen Beispiel, was ein Plugin im Twitch-Controller
alles darf, und ist als Vorlage für eigene Plugins gedacht.

Wer ein Plugin schreiben will, installiert dieses hier, schaut sich den
Ordner `plugins/example/` an und baut darauf auf.

## Was drin steckt

- **Eigener Menüpunkt** und eine eigene Seite unter `/example`
- **Eigene Rechte**, die in der Benutzerverwaltung auftauchen und dort
  je Moderator vergeben werden können
- **Eigene Einstellung** im Scope `plugin:example` — sie verschwindet
  restlos, wenn das Plugin entfernt wird
- **Reaktion auf Twitch-Events** über den Hook `core.event.stored`,
  hier: Follows mitzählen
- **Nachbestellen von Twitch-Abos und -Berechtigungen**, damit ein
  Plugin die Events bekommt, die es braucht
- **Wiederkehrende Aufgabe** über `cron.tick`, die im Hintergrund läuft
- **Eigene Übersetzungen** in `lang/de.json` und `lang/en.json`
- **Eigenes CSS** unter `/plugin/example/assets/example.css`

## Nach der Installation

Das Plugin muss unter *Konto → Plugins* noch **aktiviert** werden.
Danach erscheint der Menüpunkt *Beispiel*.

Es fordert die Berechtigung `channel:read:redemptions` an, weil es
zeigt, wie ein Plugin auf eingelöste Kanalpunkte reagiert. Damit die
Events auch ankommen, danach einmal in den Einstellungen auf **Abos
abgleichen** klicken — bei jedem neu aktivierten Plugin nötig, nicht nur
bei diesem.

## Gefahrlos

Das Plugin ändert nichts am Kern, schreibt nur in seinen eigenen Scope
und legt eine einzige Tabelle an (`example_notes`). Beim Entfernen wird
beides wieder abgeräumt. Es kann jederzeit gelöscht werden.

## Voraussetzungen

- Twitch-Controller ab Fassung 1.0.0
- keine weiteren Plugins
