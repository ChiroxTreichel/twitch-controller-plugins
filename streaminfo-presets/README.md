# Streaminfo – Vorlagen

Gespeicherte Stream-Titel zum Auswählen. Auf der Streaminfo-Seite steht
dann eine Liste über dem Titelfeld: im Stream sucht niemand nach der
richtigen Schreibweise von „FFXIV – FATEs".

Braucht **Streaminfo ab 1.1.0** — dort sind die Einhängepunkte, über die
sich dieses Plugin einsetzt. Streaminfo selbst weiß nichts davon.

## Die Liste

Unter **Plugins → Einstellungen → Streaminfo – Vorlagen**. Eine Zeile,
ein Feld — wie beim Löschbot und bei den Timern. Anhängen und Entfernen
sind Absende-Knöpfe im selben Formular, also ohne JavaScript.

Die Reihenfolge bleibt, wie du sie setzt: was du oft brauchst, stellst
du nach oben. Sortiert wird nicht — die Reihenfolge ist selbst eine
Angabe.

Beim Speichern werden leere Zeilen verworfen (so entfernt man eine
Vorlage auch ohne den Knopf) und zwei gleiche Zeilen zu einer
zusammengelegt: in der Auswahl wären sie nicht zu unterscheiden, und wer
eine davon nähme, wüsste nicht, welche er erwischt hat.

## Auf der Streaminfo-Seite

Ein Auswahlfeld über dem Titel. Es hat **kein** `name` und wird nicht
abgeschickt — sein Wert wandert in das Titelfeld daneben, und von dort
geht er wie jeder getippte Titel hinaus. Zwei Felder, die beide `title`
heißen, wären im Formular nicht auseinanderzuhalten.

Der erste Eintrag heißt „— eigener Text —" und ist wählbar: wer ihn
nimmt, will selbst tippen. Das Feld wird dabei **nicht** geleert — das
wäre der Verlust von Text, den jemand getippt hat, und das Ergebnis
eines Klicks, den man auch aus Versehen macht.

Tippt man von Hand, springt die Auswahl zurück auf „eigener Text",
sobald der Titel nicht mehr zu einer Vorlage passt. Sie soll nicht
behaupten, eine Vorlage zu zeigen, wenn dort etwas anderes steht.

Ohne JavaScript ist die Auswahl eine Liste, die nichts tut — man tippt
den Titel wie sonst. Nichts hier ist Voraussetzung fürs Speichern.

## Rechte

| Recht | Bedeutung |
| --- | --- |
| `StreaminfoPresets.Global.Edit` | darf die Liste pflegen |

Wer die Vorlagen **benutzen** darf, entscheidet Streaminfo mit
`Streaminfo.Title.Edit`. Eine eigene Erlaubnis dafür wäre ein zweites
Schloss an derselben Tür.

## Was das Plugin speichert

Die Titelliste, unter `presets` im Bereich `plugin:streaminfo-presets`.
Keine Tabelle.
