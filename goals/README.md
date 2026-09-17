# Goals

Der **Rahmen** für Ziele. Zeigt selbst kein Ziel an — den Inhalt bringen
Ziel-Plugins mit, etwa **Goals - Twitch**.

Ohne ein solches Plugin steht auf der Seite ein Hinweis, wo man eines
herbekommt.

## Was es bereitstellt

| | |
| --- | --- |
| die Fläche | ein Platz im Overlay, Breite und Abstand von oben einstellbar |
| die Reiter | Hook `goals.tabs` — wie bei den Alerts |
| das Gerüst | Hook `goals.markup` — jedes Plugin liefert HTML und CSS |
| die Werte | `Goals::send()` schickt sie ins Overlay |
| den Prüfer | `Goals::missing()` sagt, welche Pflichtelemente fehlen |

Größe und Lage der Fläche stehen unter *Plugin → Einstellungen*, nicht
als Reiter — sie gehören nicht zwischen Follower- und Sub-Ziel.

## Der Kern wird dafür nicht angefasst

Ein Overlay-Platz ist im Kern ein **leerer Kasten**. Über
`overlay.assets` darf ein Plugin jede **eigene Adresse** als CSS oder
JS anmelden — auch eine Route. Genau das nutzt dieses Plugin:

```
/display/goals/style.css?v=…   das CSS aller Ziel-Plugins
/display/goals/markup.js?v=…   das Gerüst, als window.GOALS_HTML
assets/goals.js                setzt es ein und bindet die Werte
```

Der Stempel in der Adresse ist nötig, weil beides aus den Einstellungen
kommt und keine Datei ist: `App::asset()` rechnet mit dem
Änderungsdatum einer Datei, und ohne wechselnde Adresse behält OBS das
alte Stylesheet.

## Für ein Ziel-Plugin

```php
// Ein Reiter auf der Goals-Seite
$hooks->on('goals.tabs', static function (array $tabs) use ($app, $plugin): array {
    $tabs['meins'] = [
        'label'  => 'Mein Ziel',
        'order'  => 20,
        'render' => static fn (): string => …,   // nur für den offenen Reiter
    ];

    return $tabs;
});

// Gerüst und Aussehen
$hooks->on('goals.markup', static function (array $teile) use ($app): array {
    $teile['meins'] = ['order' => 20, 'html' => …, 'css' => …];

    return $teile;
});

// Werte ins Overlay - ein Ausschnitt genügt
Goals::send($app, ['mein_current' => 5, 'mein_goal' => 10]);

// Ändert sich das Aussehen, muss OBS nachladen
$hooks->on('goals.stamp', static fn (mixed $s): int => max((int) $s, $eigenerStempel));
```

## Der Vertrag im Overlay

Derselbe wie im alten System, damit ein von dort kopiertes Gerüst
sofort passt:

| Attribut | Wirkung |
| --- | --- |
| `data-bind="x"` | der Wert `x` wird als Text eingesetzt |
| `data-format="int"` | ganze Zahl mit Tausenderpunkten; `euro` für Beträge |
| `data-fill="x"` | Breite in Prozent, gerechnet aus `x_current` und `x_goal` |

`data-fill` verallgemeinert, was im alten System drei fest verdrahtete
Fälle waren. Der Zustand wird **ergänzt**, nicht ersetzt: eine Nachricht
darf nur einen Wert enthalten.

Aus dem Gerüst fliegen `script`, `iframe`, `object`, `embed`, `style`
und Attribute wie `onclick` heraus — hier geht es um das Aussehen. Wer
Verhalten braucht, liefert eine JS-Datei über `overlay.assets` mit.

### `GOALS.patch()` — selbst etwas in den Balken schreiben

Der Zustand liegt in `goals.js` und muss dort liegen: eine Nachricht
enthält nur einen Ausschnitt, die übrigen Werte müssen erhalten bleiben.
Ein eigenes Overlay-Skript kommt also nicht heran. Dafür gibt es eine
kleine Tür:

```js
window.GOALS.patch({ tip_title: 'Neue Kamera', tip_current: 100, tip_goal: 400 });
```

Werte hineinreichen, neu zeichnen — mehr nicht. Wann, wie oft und in
welcher Reihenfolge ist Sache des Aufrufers.

`Overlay.on('goals', …)` nimmt dabei **mehrere** Zuhörer: dieses Plugin
ist einer davon und hat schon gezeichnet, wenn deiner an die Reihe
kommt.

Gedacht ist das für Entscheidungen, die nur ein bestimmtes Plugin
treffen kann. *Tip-Goals - PayPal* lässt den Spender sein Ziel wählen
und rotiert deshalb im Overlay durch alle Ziele; *StreamElements* und
*Streamlabs* bekommen ihre Spenden aus einer Schnittstelle, die nichts
von Zielen weiß, und bleiben beim obersten. Beides ist richtig — und
genau darum steht es nicht hier.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `Goals.Global.View` | die Ziele sehen |
| `Goals.Global.Edit` | die Fläche einstellen |
| `Goals.Global.Toggle` | die Ziele ein- und ausschalten |
