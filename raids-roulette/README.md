# Raids - Roulette

Ein Würfel über dem Live-Gitter. Das Roulette läuft durch die Kacheln,
wird langsamer und bleibt auf einem Kanal stehen.

Braucht **Raids** — dort steht die Liste, wer gerade live ist.

## Alles im Browser

Kein Server-Anteil, keine Tabelle, keine Einstellung. Die Kacheln
stehen schon auf der Seite, der Zufall ist ein Aufruf von
`Math.random()`, und ein Ergebnis ist nach dem Raid keine Auskunft mehr,
die jemand braucht. Ein Server-Aufruf wäre Arbeit für etwas, das hier
umsonst zu haben ist — und die Animation müsste die Antwort trotzdem
abwarten.

Das heißt auch: der Zufall ist nicht *prüfbar*. Für ein Spiel, das der
Streamer für sich dreht, ist das richtig. Für eine Verlosung, bei der
jemand mitzählt, wäre es das nicht.

## Der Gewinner steht vorher fest

Nicht jeder Schritt wird neu gewürfelt — gezogen wird einmal, und die
Animation läuft nur so lange, bis sie bei diesem Kanal angekommen ist.
Andersherum könnte sie auf dem ersten Kanal enden, und das sieht nach
Fehler aus.

Bei wenigen Kacheln dreht es mehr Runden: zwei Runden über drei Kacheln
wären sechs Schritte, und das ist kein Roulette, das ist ein Blinken.

Wer Animationen abbestellt hat (`prefers-reduced-motion`), bekommt das
Ergebnis sofort. Ein Roulette ist Zierde; das Ergebnis ist es nicht.

## Zusammen mit „Raids – Raiden"

Ist dieses Plugin ebenfalls installiert, **startet das Roulette den Raid
selbst** — es dreht, landet auf einer Kachel und drückt dort den
Raid-Knopf.

Es kennt das andere Plugin dabei nicht. Gesucht wird ein Knopf mit
`data-raid-start` in der Gewinnerkachel; ist keiner da — weil das Plugin
fehlt oder weil das Recht fehlt — bleibt es beim Hervorheben, genau wie
im alten System. Ein *abgeschalteter* Knopf wird nicht gedrückt: dann
fehlt die Twitch-Freigabe, und der Grund steht in seinem `title`.

Zurückholen lässt sich der Raid während der neunzig Sekunden Vorlauf,
die Twitch gibt — siehe den Abbruch-Knopf dort.

## Ohne JavaScript

Dann ist der Würfel nicht da. Er steht mit `hidden` in der Seite, und
erst das Skript blendet ihn ein: ein Knopf, der nichts tut, ist
schlimmer als kein Knopf.

## Rechte

`RaidsRoulette.Global.Spin` — darf drehen. Ein eigenes Recht und nicht
dasselbe wie Raiden: wer drehen darf, aber nicht raiden, sieht den
Gewinner, und der Knopf in der Kachel ist dann gar nicht da.

## Was das Plugin speichert

Nichts.
