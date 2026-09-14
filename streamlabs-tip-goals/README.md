# Streamlabs-Tip-Goals

Spendenziele als Balken im Overlay, gespeist aus **Streamlabs**.

Braucht **Goals** — dort hängt der Reiter und der Platz im Overlay.

## Verträgt sich nicht mit Streamlabs-Tip-Goals

Beide buchen auf dasselbe Ziel. Nebeneinander zählte jede Spende
doppelt, und das fällt erst auf, wenn der Balken zu schnell wächst.

Der Kern lässt das zweite deshalb gar nicht erst installieren — die
Sperre steht als `conflicts` in der `plugin.json`, und der Marktplatz
zeigt statt des Knopfes den Grund. Wer wechseln will, **entfernt** das
andere zuerst; abschalten genügt nicht, denn seine Tabelle und sein
Token stehen dann noch.

## Spenden gehen immer auf das oberste Ziel

Im alten System gab es dafür einen eigenen „aktiv"-Zeiger, der irgendwo
in der Liste stehen konnte. Das war eine zweite Sache zum Pflegen, und
man sah ihr nicht an, warum ein Betrag beim dritten Eintrag landete.

Jetzt entscheidet die Reihenfolge: **das oberste Ziel ist das
laufende.** Ist es voll, schiebst du es nach unten oder löschst es. Der
Reiter zeigt es mit einem farbigen Rand und dem Wort „läuft".

## Was beim ersten Lauf passiert: nichts

Genauer — es wird nur die Uhr gestellt. Gezählt werden Spenden **ab
jetzt**, nicht rückwirkend. Sonst buchte das Einrichten des Plugins die
Spendenhistorie eines ganzen Jahres auf das erste Ziel.

Danach merkt sich das Plugin den Zeitpunkt der zuletzt verbuchten
Spende. Der Zeiger rückt nur vor, wenn auch wirklich gebucht wurde —
sonst liefe er an einer Spende vorbei, die in derselben Runde ankam,
aber noch auf ihre Freigabe wartete.

Nicht gezählt werden gelöschte, nicht bestätigte und noch nicht
freigegebene Spenden. Der Balken soll zeigen, was angekommen ist.

## Die fünf Sekunden

Nachgefragt wird alle fünf Sekunden — **sofern der Hintergrundprozess so
oft tickt.** Er ruft `cron.tick` alle `WORKER_INTERVAL` Sekunden auf, und
das sind ohne Zutun 15. Wer die fünf wirklich will, setzt in der `.env`:

```
WORKER_INTERVAL=5
```

Das betrifft alle Plugins, ist aber unbedenklich: jedes bremst sich
ohnehin selbst (Raids einmal am Tag, Live-Benachrichtigung alle 30
Sekunden).

## Geld ist NUMERIC, nicht Fließkomma

Die Beträge stehen als `NUMERIC(12,2)` in der Tabelle. Bei Geld ist
`0.1 + 0.2` kein akademisches Problem: der Balken stünde irgendwann auf
49,999999 statt auf 50.

Gebucht wird in **einer** Abfrage:

```sql
UPDATE sl_tip_goals SET current = current + :betrag WHERE id = (…)
```

Ohne vorher zu lesen — zwischen Lesen und Schreiben könnte eine zweite
Spende liegen, und die wäre dann weg. Deshalb auch eine Tabelle und
keine Einstellung: eine JSON-Liste müsste gelesen, geändert und
zurückgeschrieben werden.

Eingaben werden großzügig gelesen: `12.50`, `12,50`, `1.234,50` und
`1,234.50` ergeben alle dasselbe. Der **letzte** Trenner ist der
Dezimalpunkt.

## Gibt es kein Ziel, passiert nichts

Die Spende ist trotzdem angekommen — sie zählt nur in keinen Balken.
Eine Zeile im Log sagt es.

## Wenn die Abfrage scheitert

Der Fehler wird **gemerkt** und steht auf der Seite, nicht nur im Log.
Eine Abfrage, die still scheitert, sieht sonst aus wie eine, bei der
nichts passiert ist.

## Der Zugang

Unter *Plugins → Einstellungen*: das Token. Bei Streamlabs steht es
unter **Settings → API Settings → API Tokens** als *Your API Access
Token*.

Eine Kanal-ID braucht es hier nicht — das Token gehört schon zu einem
Konto. Das ist der einzige Unterschied in der Bedienung gegenüber dem
StreamElements-Plugin.

Das Token wird **verschlüsselt** abgelegt und nie wieder angezeigt — es
ist ein Schlüssel zum Spendenkonto, kein Einstellwert. Ein leeres Feld
heißt darum „nicht ändern" und nicht „löschen". Zum Löschen gibt es
einen eigenen Knopf.

## Rechte

| Recht | darf |
| --- | --- |
| `SlTipGoals.Global.View` | die Ziele sehen |
| `SlTipGoals.Global.Edit` | Ziele pflegen und den Zugang hinterlegen |

## Was das Plugin speichert

Die Tabelle `sl_tip_goals` — ein Ziel je Zeile mit Platz, Name,
aktuellem Betrag und Zielbetrag. Dazu im Bereich
`plugin:streamlabs-tip-goals`: das verschlüsselte `token`, die
`channel_id`, `last_donation_at`, `checked_at` und `last_error`.

Beim Entfernen des Plugins geht beides mit — Ziele inklusive. Bei
Streamlabs ändert sich nichts.
