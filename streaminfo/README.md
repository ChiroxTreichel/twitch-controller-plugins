# Streaminfo

Titel und Kategorie des laufenden Streams ändern, ohne Twitch zu
öffnen. Im alten System war das die am häufigsten benutzte Seite: eine
Stream-Hilfe kann damit den Titel umstellen, **ohne Zugriff auf das
Twitch-Konto** zu haben.

## Was die Seite zeigt

Immer den Stand, der gerade bei Twitch steht — gelesen bei jedem
Seitenaufruf, nicht zwischengespeichert. Der Titel kann sich jederzeit
anderswo ändern: im Twitch-Dashboard, per Handy, durch einen Mod. Ein
gespeicherter Wert wäre dann falsch, und falsch ist hier schlimmer als
langsam.

## Titel

Ein Textfeld, bis 140 Zeichen — die Grenze von Twitch. Länger nimmt die
API nicht an, und ein Feld, das mehr erlaubt als hinterher durchgeht,
zeigt den Fehler erst beim Speichern.

Beim Speichern wird geputzt: Umbrüche und doppelte Leerzeichen werden zu
einem Leerzeichen. Ein Titel ist eine Zeile, und wer aus einem Textfeld
einfügt, hat leicht einen Umbruch darin.

Die vordefinierten Titel des alten Systems sind **nicht** hier. Eine
Liste von Vorlagen ist eine Sache für sich und kommt als eigenes Plugin;
wer sie nicht braucht, soll sie nicht mitgeliefert bekommen.

## Kategorie

Ein Suchfeld. Getippt wird der Name, gesucht wird bei Twitch, und die
Vorschläge kommen mit Bild und Namen — auswählbar mit Maus oder mit
`↑` `↓` `Enter`, `Esc` schließt die Liste.

Abgeschickt wird die **ID**, nicht der Name: Twitch kennt Kategorien nur
daran. Deshalb gilt eine Kategorie erst als gewählt, wenn eine Zeile
angeklickt wurde — tippt man danach weiter, wird die ID wieder verworfen.
Sonst ginge eine ID mit einem Namen hinaus, den jemand seither
überschrieben hat.

Gefragt wird 220 ms nach dem letzten Tastendruck, nicht bei jedem: jeder
Aufruf zählt bei Twitchs Grenze mit. Und jede Antwort trägt die Nummer
ihrer Frage — sonst überschreibt die langsame Antwort auf `min` die
schnelle auf `minecraft`, und die Liste springt zurück, während man
tippt.

Ohne JavaScript bleibt die Seite bedienbar: man tippt den Titel und
speichert, die Kategorie bleibt dann, wie sie ist.

## Es wird nur geschickt, was sich geändert hat

Twitch nimmt jedes Feld, das im Aufruf steht, als gewollt. Wer allein
den Titel anfasst, würde also ungefragt die Kategorie mitschreiben —
darum liest das Speichern zuerst den aktuellen Stand und vergleicht.

Ein leeres Feld heißt **nicht anfassen**, nicht "löschen". Ein Formular,
das beim Speichern den Titel wegnimmt, wäre im Stream ein teurer
Fehlgriff.

Hat sich nichts geändert, ist das keine Fehlermeldung: wer zweimal auf
Speichern drückt, hat nichts falsch gemacht.

## Rechte

| Recht | Bedeutung |
| --- | --- |
| `Streaminfo.Global.View` | darf Titel und Kategorie sehen |
| `Streaminfo.Title.Edit` | darf den Titel ändern |
| `Streaminfo.Category.Edit` | darf die Kategorie ändern |

Titel und Kategorie sind getrennt, wie im alten System: eine
Stream-Hilfe darf oft den Titel anpassen, aber nicht das Spiel umstellen
— das ändert, wer den Kanal in den Verzeichnissen findet.

Die Kategorie-Suche hängt am selben Recht wie das Ändern. Wer sie nicht
braucht, soll sie nicht auslösen können — sie kostet bei jedem
Tastendruck einen Aufruf bei Twitch.

## Twitch-Freigabe

Zum Ändern braucht der Kanal `channel:manage:broadcast`. Das Plugin
fordert sie an, sobald es eingeschaltet ist; fehlt sie, steht der Hinweis
oben auf jeder Seite und noch einmal ausführlich auf dieser.

**Lesen geht ohne.** Die Seite zeigt den aktuellen Stand also auch dann,
wenn das Speichern gesperrt ist — ein leeres Formular mit einer Warnung
wäre weniger wert als der Blick auf das, was gerade läuft.

## Für Erweiterungen

Drei Einhängepunkte, damit Erweiterungen die Seite ergänzen können,
ohne dass Streaminfo sie kennt:

| Hook | Art | Bedeutung |
| --- | --- | --- |
| `streaminfo.fields` | filter | Blöcke über dem Titelfeld |
| `streaminfo.title_bare` | filter | beim Anzeigen: Vorsätze abnehmen |
| `streaminfo.title_compose` | filter | beim Speichern: Vorsätze anbauen |

`streaminfo.fields` bekommt als zweites Argument den Zusammenhang —
`title` (der ganze Titel bei Twitch), `bare` (ohne Vorsätze) und
`canEdit`. Jeder Beitrag nennt seinen Platz in der Reihe, damit die
Seite nicht von der Ladereihenfolge der Plugins abhängt:

```php
$hooks->on('streaminfo.fields', static function (array $felder, array $kontext) use ($app): array {
    $felder['mein-plugin'] = ['order' => 30, 'html' => '…'];

    return $felder;
});
```

**`title_bare` und `title_compose` müssen zueinander passen.** Was
`compose` anbaut, muss `bare` abbauen — und nur das Eigene. Bliebe ein
Vorsatz beim Anzeigen stehen, würde ihn das nächste Speichern ein
zweites Mal davorsetzen: aus `[VTuber] Titel` würde
`[VTuber] [VTuber] Titel`, und beim nächsten Mal wieder eins mehr.

Zusammengesetzt wird **vor** dem Kürzen auf 140 Zeichen. Ein Vorsatz
nimmt dem Titel also Platz weg, statt selbst abgeschnitten zu werden.

Zwei Erweiterungen benutzen das: **Streaminfo – Vorlagen** (gespeicherte
Titel zur Auswahl) und **Streaminfo – Tags** (Tags in eckigen Klammern
vor dem Titel).

## Was das Plugin speichert

Nichts. Keine Tabelle, keine Einstellung — alles steht bei Twitch.
