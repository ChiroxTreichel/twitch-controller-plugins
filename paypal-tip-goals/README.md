# Tip-Goals - PayPal

Eine **Spendenseite unter `/tips`**: Twitch-Login, Betrag, Ziel
aussuchen, per PayPal zahlen. Die Spende landet auf dem gewählten Ziel
und als Balken im Overlay.

Braucht **Goals**. Mit **Alerts** kommt zusätzlich ein Alert im Stream —
ohne läuft alles andere genauso.

## Das Aussehen ist das alte

Farben, Rundungen, Knöpfe, Schalter und das Karussell für die Ziele
kommen aus der alten Spendenseite und sind von dort übernommen — wer
sie kannte, muss die neue nicht erst wiedererkennen.

Zwei Dinge sind trotzdem anders, und beide aus einem Grund:

- **Eine Seite statt zweier.** Früher kam erst eine Startseite und nach
  einem Klick das Formular. Hier steht es gleich da, sobald man
  angemeldet ist. Die Startkarte gibt es weiter — für den, der es noch
  nicht ist.
- **Die Auswahl steckt im Karussell selbst.** Früher schrieb
  JavaScript sie in ein verstecktes Feld; ohne JavaScript ging gar
  nichts. Jetzt trägt jede Karte einen echten Auswahlknopf, und ohne
  JavaScript liegen die Karten untereinander. Das sieht anders aus —
  spenden kann man trotzdem.

## Der Unterschied zu den anderen beiden

Bei StreamElements und Streamlabs kommt eine Spende aus einer
Schnittstelle und weiß nichts von Zielen — sie landet deshalb immer auf
dem obersten. **Hier wählt der Spender**, und das ist der Sinn der
Seite.

Darum verträgt sich dieses Plugin mit keinem der beiden: nebeneinander
zählte jede Spende doppelt. Der Marktplatz lässt das zweite gar nicht
erst installieren.

## Bis zum Schluss fließt kein Geld

Der Weg hat drei Schritte, und zwischen zweien davon ist der Spender
woanders:

1. **Order anlegen** — PayPal antwortet mit einer Adresse
2. **Weiterleiten** — der Spender bezahlt bei PayPal
3. **Rückkehr → Capture** — und *jetzt* fließt Geld

Bricht jemand bei PayPal ab oder schließt den Tab, bleibt eine
genehmigte, unbezahlte Order stehen. Die läuft nach 30 Minuten ab und
wird weggeräumt. Unschön, aber harmlos — gefährlich wäre der umgekehrte
Fall.

### Genau einmal gebucht

Drei Riegel, und jeder einzelne würde nicht reichen:

| | |
| --- | --- |
| `WHERE status <> 'captured'` | zwei gleichzeitige Rückkehrer (Nachladen, zwei Tabs) kommen nur einmal durch, und nur der Gewinner erhöht das Ziel |
| `PayPal-Request-Id` | derselbe Merker beim Anlegen und beim Einziehen — PayPal zieht auch bei einem zweiten Versuch nur einmal ein |
| eindeutiger Index | dieselbe Capture-ID lässt die Datenbank kein zweites Mal zu |

## Was auf dem Ziel landet

Der **Netto**betrag, wenn PayPal ihn nennt — also das, was wirklich
ankommt. Der Balken soll nicht mehr zeigen, als da ist.

Wer „Gebühren übernehmen" anhakt, meint den Betrag, der ankommen soll;
der Aufschlag wird daraus gerechnet und auf Cent aufgerundet. Die Sätze
dafür stehen in den Einstellungen; vorgegeben sind **2,99 % + 0,39 €**,
der Satz für Spenden innerhalb Deutschlands. Geraten wird damit
trotzdem nichts: PayPal berechnet je nach Land und Konto anderes, und
gerechnet wird nur der *Vorschlag* im Formular — was wirklich abgezogen
wurde, sagt die Abrechnung.

„Einfach so – kein Ziel" bucht auf keinen Balken. Das steht dem Spender
auch so auf der Seite.

Verschwindet ein Ziel, während der Spender bei PayPal ist, kommt die
Spende trotzdem an — sie zählt dann in keinen Balken, und eine Zeile im
Log sagt es.

## Ohne Impressum bleibt die Seite zu

Eine Seite, die Geld annimmt, muss sagen, wer es bekommt. Solange das
Impressum leer ist, antwortet `/tips` mit einem Hinweis und einem 503 —
nicht 404: die Seite gibt es, sie ist nur nicht fertig.

Der Besucher erfährt dabei **nicht**, was fehlt. Das ist eine Auskunft
für den Betreiber, nicht für das ganze Netz.

## Die Rechtstexte gehören dir

Impressum, Datenschutz und AGB liefert das Plugin **nicht** mit, und das
ist Absicht: in einem Impressum steht dein Name und deine Anschrift. Ein
Plugin aus dem Katalog, das eines mitbrächte, würde diese Angaben bei
jedem verbreiten, der es installiert — und der Nächste betriebe eine
Spendenseite mit fremden Daten.

Geschrieben werden sie in den Einstellungen, je Text ein Reiter.
Markdown ist erlaubt. Verlinkt wird im Fuß nur, was wirklich da ist —
ein Link auf eine leere Seite ist schlechter als kein Link.

Das Häkchen „AGB und Datenschutzerklärung gelesen" steht davon
unabhängig **immer** da — es trägt auch die Altersbestätigung, und die
gilt so oder so. Geprüft wird es auch vom Server: `required` im
Formular ist eine Bequemlichkeit, keine Bedingung.

## Der Spender bekommt kein Konto

Wer sich auf `/tips` anmeldet, bekommt ein mit `APP_KEY` signiertes
Cookie mit seinem Kanalnamen — mehr nicht. Das Twitch-Token aus der
Anmeldung wird **weggeworfen**: gebraucht wird die Auskunft, wer da ist.
Freigaben verlangt die Anmeldung keine.

Anonym heißt anonym: der Name steht zwar in der Tabelle, aber weder der
Alert noch die Liste in der Verwaltung zeigen ihn.

## Die Zugangsdaten

Client-ID und Secret aus dem PayPal-Entwicklerkonto (*Apps &
Credentials*), **verschlüsselt** abgelegt und nie wieder angezeigt. Ein
leeres Feld heißt „nicht ändern" — sonst würfe ein Speichern der
Vorgabebeträge nebenbei den Zugang zum Geldkonto weg. Zum Löschen gibt
es einen eigenen Knopf.

**Testkonto ist die Vorgabe.** Erst sehen, dass der Weg funktioniert,
dann bewusst auf Echtbetrieb schalten. Achte darauf, dass die Schlüssel
zum gewählten Modus gehören — Sandbox und Live haben verschiedene.

## Rechte

| Recht | darf |
| --- | --- |
| `PaypalTipGoals.Global.View` | Ziele und Spenden sehen |
| `PaypalTipGoals.Global.Edit` | Ziele pflegen, PayPal und die Texte hinterlegen |

Die öffentliche Seite prüft **kein** Recht — das ist ihr Sinn. Was dort
möglich ist, ist genau eines: sich selbst eine Spende vormerken.

## Für andere Plugins

Jede gebuchte Spende löst `tips.donation` aus:

```php
$hooks->on('tips.donation', static function (array $spende): void {
    // login, name, amount, net, message, goal_id
});
```

Der Alert hängt selbst daran — ein anderes Plugin kann dasselbe
Ereignis abgreifen, etwa für eine Chatnachricht, ohne dass dieses hier
davon wissen muss.

## Was das Plugin speichert

`pp_tip_goals` — ein Ziel je Zeile. `pp_donation_intents` — eine Spende
je Zeile mit Betrag, Ziel, Nachricht, Zustand und den PayPal-Nummern.
Dazu im Bereich `plugin:paypal-tip-goals`: die verschlüsselten
Zugangsdaten, die Beträge, die drei Rechtstexte.

Abgeschlossene Spenden bleiben 90 Tage — sie sind der Beleg, wenn jemand
fragt, wo seine Spende geblieben ist. Beim Entfernen des Plugins geht
alles mit; bei PayPal bleibt selbstverständlich alles stehen.
