# Raids

Zwei Fragen, die man vor einem Raid hat:

- Wem folge ich, und wer davon streamt überhaupt noch?
- Wer von meinen Favoriten ist **jetzt** live?

Aus dem alten System übernommen — dort war das der Reiter „Raid" mit
seinen Unterreitern.

Was **nicht** hier ist: das Raiden selbst, das Roulette und die
Raid-Anfragen. Jedes davon wird ein eigenes Plugin und hängt sich über
`raids.tabs` ein.

## „Hat noch gestreamt" wird geschätzt

Twitch verrät nicht, wann jemand zuletzt live war. Es gibt aber zu jedem
Stream ein Archiv-Video, und dessen Datum ist die Näherung — dieselbe,
die das alte System benutzt hat.

Wer seine Aufnahmen abgeschaltet hat, fällt damit aus der Liste. Für die
Frage „wen kann ich raiden" ist das gut genug, und eine bessere Quelle
gibt es nicht.

## Der Abgleich kostet Aufrufe

Drei Abfragen bei Twitch, und die zweite ist die teure:

| Abfrage | wie oft |
| --- | --- |
| `channels/followed` | einmal am Tag, blättert über alle Seiten |
| `videos` | **je Kanal**, jeder Kanal einmal am Tag |
| `streams` | wenn der Live-Reiter geöffnet wird |

Bei zweihundert Follows sind das zweihundert Video-Abfragen. Sie laufen
darum in Häufchen von zehn je Cron-Tick — der Worker tickt alle 15
Sekunden, zweihundert Kanäle sind also in etwa fünf Minuten durch, und
Twitch sieht nie einen Schwung von zweihundert Aufrufen.

Deshalb steht direkt nach der Installation ein Hinweis, wie viele Kanäle
noch auf ihre Prüfung warten: die Liste füllt sich über Minuten, und
ohne den Hinweis sieht das nach einem Fehler aus.

**Eine leere Antwort leert die Liste nicht.** Das ist die wichtigste
Regel im Abgleich: die Favoritenhaken sind Handarbeit, und eine Störung
bei Twitch ist wahrscheinlicher als „folgt niemandem mehr".

## Favoriten

Ein Klick auf den Stern. Das ist ein Absende-Knopf in seinem eigenen
Formular — das alte System schickte dafür eine AJAX-Anfrage, ein
Formular tut dasselbe und funktioniert auch dann, wenn ein Skript fehlt.

Gemerkt werden können nur Kanäle aus der Liste. Ein Login aus einem
Formular ist kein Grund, eine Zeile anzulegen: wer einem Kanal nicht
folgt, kann ihn hier auch nicht merken.

Das Suchfeld ist dagegen JavaScript, und das ist richtig — es filtert
eine Liste, die schon auf der Seite steht. Über den Server wäre es ein
Aufruf je Tastendruck.

## Live wird nicht gespeichert

„Gerade live" ist in einer Minute falsch. Der Live-Reiter fragt bei jedem
Aufruf frisch bei Twitch, in Häufchen von hundert Logins — mehr nimmt
Twitch je Abfrage nicht an. Die Avatare kommen aus der Tabelle; der
Live-Aufruf liefert sie nicht mit.

## Rechte

| Recht | Bedeutung |
| --- | --- |
| `Raids.Global.View` | darf die Seite und die Favoriten sehen |
| `Raids.Favorites.Edit` | darf Favoriten setzen und entfernen |
| `Raids.Global.Sync` | darf die Follow-Liste von Hand abrufen |

## Twitch-Freigabe

`user:read:follows`. Ohne sie lässt sich die Follow-Liste nicht holen —
alles andere arbeitet mit dem, was schon in der Tabelle steht.

Den Namen dieser Freigabe kennt der Kern nicht; das Plugin trägt ihn
über `core.twitch.scope_labels` nach, damit auf der Einstellungsseite
nicht nur der technische Name steht.

## Für Erweiterungen

`raids.tabs` — dieselbe Verabredung wie bei Goals, Alerts und
Streaminfo:

```php
$hooks->on('raids.tabs', static function (array $tabs) use ($app): array {
    $tabs['roulette'] = [
        'label'  => translate('…'),
        'order'  => 20,
        'render' => static fn (): string => '…',
    ];

    return $tabs;
});
```

Der Schlüssel ist die Adresse: `/stream/raids/<schlüssel>`. Ein
unbekannter Schlüssel führt auf den ersten Reiter, nicht auf eine
Fehlerseite. `render` wird **nur** für den offenen Reiter aufgerufen —
im Live-Reiter steckt eine Twitch-Abfrage.

Meldungen (`?notice=` / `?error=`) zeigt der Rahmen.

Wer die Kanalliste braucht, nimmt `Channels`: `active()` für die
aktiven, `favorites()` für die Logins der Favoriten, `live()` für die
gerade streamenden.

## Was das Plugin speichert

Die Tabelle `raid_channels` — ein Kanal je Zeile, mit Twitch-ID,
Anzeigename, Avatar, dem Datum des letzten Streams, dem Zeitpunkt der
letzten Prüfung und dem Favoritenhaken. Dazu `synced_at` im Bereich
`plugin:raids`.

Beim Entfernen des Plugins geht die Tabelle mit, Favoriten inklusive.
Wer nur aufhören will, es zu benutzen, schaltet es aus.
