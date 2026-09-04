# Streaminfo – Tags

Eigene Tags festlegen und auf der Streaminfo-Seite einzeln anhaken. Die
angehakten stehen in eckigen Klammern vor dem Titel:

```
[x] VTuber
[x] German
[ ] Langeweile

[VTuber][German] Das ist mein Titel
```

Die Tags stehen also **im Titel** und nicht in Twitchs eigenem Tag-Feld.
Das ist gewollt: es ist das, was man im Verzeichnis und im Chat liest,
und es funktioniert, ohne dass Twitch die Tags kennt.

Braucht **Streaminfo ab 1.1.0** — dort sind die Einhängepunkte.

## Die Liste

Als Reiter **Tags** auf der Streaminfo-Seite — die Tags ändert man oft,
und der Weg über Plugins → Einstellungen wäre einer zu viel.

Eine Zeile, ein Feld; anhängen und entfernen ohne JavaScript. Eine leere
Zeile bleibt gespeichert, damit „Tag hinzufügen" eine Zeile anlegen
kann, die das Speichern übersteht; als Haken erscheint sie nicht — ein
leerer Tag ergäbe den Vorsatz `[]` vor dem Titel.

Die Reihenfolge hier ist die Reihenfolge im Titel — der oberste Tag
steht vorn. Deshalb wird nicht sortiert.

Eckige Klammern im Namen werden entfernt, und das ist keine Kosmetik:
der Vorsatz im Titel *ist* `[` + Name + `]`. Ein Name mit Klammer wäre
hinterher nicht mehr eindeutig zurückzulesen — aus `[a]b]` ließe sich
`a` oder `a]b` machen.

## Was gerade an ist, steht im Titel

Nirgends sonst. Der Titel bei Twitch ist die einzige Wahrheit: er kann
sich jederzeit anderswo ändern — im Dashboard, per Handy, durch einen
Mod — und ein zweiter Ort dafür wäre dann sofort falsch.

Beim Anzeigen wird also zurückgerechnet: aus
`[VTuber][German] Das ist mein Titel` wird `Das ist mein Titel` im
Textfeld plus zwei gesetzte Haken.

**Das ist der empfindliche Teil.** Bliebe ein Vorsatz stehen, würde ihn
das nächste Speichern ein zweites Mal davorsetzen — aus `[VTuber] Titel`
würde `[VTuber][VTuber] Titel`, und beim nächsten Mal wieder eins mehr.

Drei Regeln dafür:

- **Nur eigene Tags werden abgebaut.** Was du selbst in Klammern
  geschrieben hast — `[Achtung] Umbau` — gehört zum Titel und bleibt
  stehen. Sonst frisst das Plugin einen Teil des Titels, den es nie
  angebaut hat.
- **Eine fremde Klammer beendet das Lesen.** `[Achtung] [German] Umbau`
  ist ein Titel, der mit `[Achtung]` beginnt — und nicht eine Liste, aus
  der man sich das Passende heraussucht.
- **Groß- und Kleinschreibung ist beim Lesen egal.** Steht `[german]` im
  Titel, ist der Haken bei `German` gesetzt; geschrieben wird dann die
  festgelegte Schreibweise.

Geschrieben wird dicht aneinander (`[VTuber][German] Titel`), gelesen
wird beides — ein Titel, der noch mit Lücken dasteht, wird erkannt und
beim nächsten Speichern geradegezogen.

## Die Vorschau

Unter den Haken steht, was zu Twitch geht, mit Zeichenzähler. Sie warnt,
wenn Tags und Titel zusammen über Twitchs 140 Zeichen kommen — ohne sie
merkt man das erst am gekürzten Ergebnis. Zusammengesetzt wird **vor**
dem Kürzen: ein Vorsatz nimmt dem Titel Platz weg, statt selbst
abgeschnitten zu werden.

Gerechnet wird dabei in derselben Reihenfolge wie auf dem Server — der
der Kästchen im HTML, und die ist die der Liste. Zwei Rechenwege für
dasselbe Ergebnis liefen auseinander, und die Vorschau zeigte irgendwann
etwas anderes als das Gespeicherte.

## Rechte

| Recht | Bedeutung |
| --- | --- |
| `StreaminfoTags.Global.Edit` | darf die Tag-Liste festlegen |

Wer die Haken **setzen** darf, entscheidet Streaminfo mit
`Streaminfo.Title.Edit`: die Haken ändern den Titel, also gilt dasselbe
Recht wie für den Titel.

## Was das Plugin speichert

Die Tag-Liste, unter `tags` im Bereich `plugin:streaminfo-tags`. Keine
Tabelle — und **nicht**, welche Tags an sind.
