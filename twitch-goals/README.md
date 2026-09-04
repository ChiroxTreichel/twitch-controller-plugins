# Twitch-Goals

Follower- und Sub-Ziel, die Zahlen **direkt von Twitch** und dauerhaft
aktuell. Braucht das Plugin **Goals** — das wird bei der Installation
mitinstalliert.

## Zwei Stellen, absichtlich getrennt

| wo | was |
| --- | --- |
| Reiter in *Goals* | die Titel, und was Twitch gerade meldet |
| *Plugin → Einstellungen* | das Aussehen: HTML und CSS |

Das Aussehen gehört nicht zwischen Follower- und Sub-Ziel — es ist eine
Einstellung dieses Plugins, wie bei den Alerts die Größe der Fläche.

## Woher die Zahlen kommen

| Quelle | wofür |
| --- | --- |
| `helix/goals` | die auf Twitch angelegten Ziele: Typ `follower` und `subscription_count` — daraus kommen Stand **und** Zielwert |
| `helix/channels/followers` / `helix/subscriptions` | der reine Zähler, als Rückfall |

Der Rückfall greift nur, wenn auf Twitch **kein** Ziel dieser Art
angelegt ist. Sonst würde der Zähler den Stand des Ziels überschreiben,
und die beiden müssen nicht übereinstimmen: ein Ziel zählt ab seinem
Anlegen, der Zähler ab null. Ohne Ziel steht dann die Zahl da, nur ohne
Zielwert — die Seite sagt das auch.

**Aktuell** bleibt es auf zwei Wegen: die Abos `channel.goal.begin`,
`.progress` und `.end` melden jede Änderung sofort, und `cron.tick`
fragt zusätzlich höchstens jede Minute nach. Ohne das Nachfragen stimmte
die Anzeige nach einem Neustart erst beim nächsten Follower wieder.

## Aussehen

HTML und CSS des Gerüsts stehen in den Einstellungen. Voreingestellt ist
das Aussehen des alten Systems — wortgleich aus
`legacy/templates/goals.html` und `legacy/public/styles.css`, ohne den
Tip-Balken. Der kommt mit dem Spenden-Plugin und hängt sich dann als
eigener Abschnitt in denselben Hook.

### Pflichtelemente

Diese Attribute **müssen** im HTML vorkommen:

```html
<p class="goal-label"   data-bind="follower_title"></p>
<p class="goal-current" data-bind="follower_current" data-format="int"></p>
<p class="goal-amount"  data-bind="follower_goal"    data-format="int"></p>
```

und dasselbe mit `sub_*`, dazu je ein Balken mit
`data-fill="follower"` und `data-fill="sub"`.

Fehlt eines, sagt die Seite es beim Speichern und listet es oben auf.
Gespeichert wird trotzdem: ein halb fertiges Gerüst soll man stehen
lassen und weiterschreiben können. Der Grund für die Prüfung ist, dass
man ein fehlendes Element im Overlay **nicht sieht** — dort ist dann nur
nichts, und das fällt mitten im Stream auf.

`data-format` bestimmt die Schreibweise: `int` für ganze Zahlen mit
Tausenderpunkten, `euro` für Beträge. Ohne Angabe wird der Wert
unverändert eingesetzt.

**Zurücksetzen** stellt die Vorgabe wieder her — intern heißt das
einfach: leere Felder speichern.

## Voraussetzungen

Der Kanal braucht `channel:read:goals`; das Plugin fordert es an. Nach
der Installation einmal den Abo-Abgleich unter *Konto → Einstellungen →
Kanal* auslösen, sonst kommen die Ereignisse nicht an.

Gepostet wird nichts — dieses Plugin liest nur. Für das Nachfragen muss
der worker-Container laufen; ohne ihn bleibt „Jetzt abrufen" der einzige
Weg.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `TwitchGoals.Global.View` | die Ziele sehen |
| `TwitchGoals.Global.Edit` | Titel und Aussehen ändern |
