# Stream - Zuschauer

Wer dir folgt und wer ein Abo hat — zwei Reiter, beide mit Suche.

## Follower

Name und seit wann er folgt, neueste zuerst. Die Liste führt der Kern: er gleicht sie alle zehn Sekunden mit Twitch ab, weil er
daran die Unfollows erkennt, die Twitch nicht meldet.

## Abonnenten

Name, Stufe, geschenkt (und von wem) und **bis wann das Abo gilt**.

Das Ende sagt Twitch nicht. Es wird aus dem letzten Abo-Ereignis
errechnet: ein neues Abo gilt einen Monat, eine Verlängerung so viele
Monate, wie im Voraus bezahlt sind. Dafür reichen auch Ereignisse von
vor der Installation — der Kern bewahrt sie auf.

- **voraussichtlich** — das Abo hat sich verlängert, ohne dass Twitch
  es gemeldet hat (wer seine Verlängerung nicht im Chat teilt, schickt
  kein Ereignis). Das Ende ist um ganze Monate fortgeschrieben.
- **unbekannt** — zu diesem Abo liegt gar kein Ereignis vor. Das Ende
  steht da nach der nächsten Verlängerung.

Die Abo-Liste führt der Kern (ab 2.19.0) und gleicht sie alle zehn
Sekunden mit Twitch ab; das Plugin übernimmt jede Änderung sofort.
Neue Abos, Verlängerungen und Abo-Enden stehen über die Ereignisse
ohnehin gleich drin.

## Ehemalige — mit Doppel-Opt-in

Wer kein Abo mehr hat oder nicht mehr folgt, und seit wann. Dafür
werden Daten über Menschen aufbewahrt, die gerade nichts mit dem Kanal
zu tun haben — darum zwei Schalter hintereinander, je für Abos und
Follower getrennt:

1. **Einstellungen** (Plugin-Liste → Zuschauer → Einstellungen):
   „Ehemalige Abonnenten erfassen“ bzw. „Ehemalige Follower erfassen“.
   Erst dann wird erfasst. Beim Einschalten werden die Abo-Enden bzw.
   Unfollows übernommen, die schon in den Aktivitäten liegen.
2. **Reiter**: „Hat kein Abo mehr“ bzw. „Folgt nicht mehr“ zeigt die
   Liste.

Ausschalten blendet die Ehemaligen aus und erfasst nichts Neues; was
schon erfasst ist, bleibt liegen. Wer wieder abonniert oder folgt,
verschwindet aus der Liste.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `Viewers.Global.View` | Follower und Abonnenten sehen, jetzt abgleichen |
| `Viewers.Former.View` | die Ehemaligen sehen (wenn freigeschaltet) |
| `Viewers.Global.Edit` | Einstellungen ändern |

## Twitch-Freigaben

`moderator:read:followers` und `channel:read:subscriptions` — beide
fordert der Kern an.
