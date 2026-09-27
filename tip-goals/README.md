# Tip-Goals

Eine **Spendenseite unter `/tips`**: Twitch-Login, Betrag, Ziel
aussuchen, bezahlen. Die Spende landet auf dem gewählten Ziel und als
Balken im Overlay.

Braucht **Goals**. Mit **Alerts** kommt zusätzlich ein Alert im Stream —
ohne läuft alles andere genauso.

## Bezahlt wird über ein Anbieter-Plugin

Dieses Plugin kennt keinen Zahlungsweg. Es hält die Seite, die Ziele,
die Spenden, die Rechtstexte und den Alert — wie das Geld fließt,
bringt ein eigenes Plugin mit, zum Beispiel **Tip-Goals - PayPal**.

- **Kein Anbieter installiert:** `/tips` bleibt zu, wie ohne Impressum.
- **Einer:** er wird genommen, der Spender sieht keine Auswahl.
- **Mehrere:** der Spender wählt, womit er zahlt. Jeder Anbieter
  bringt seine eigenen Gebührensätze mit, und die Rechnung auf der
  Seite folgt der Wahl.

Installiert, aber nicht eingerichtet (etwa ohne Zugangsdaten), zählt
ein Anbieter nicht: die Seite geht dann auf, sagt aber, dass gerade
nichts angenommen wird. In der Verwaltung steht, was fehlt.

## Kommst du von „Tip-Goals - PayPal“ 1.x?

Bis 1.x war alles ein Plugin. Ab PayPal 2.0 sind es zwei, und dieses
hier ist die Grundlage. Beim Installieren zieht es aus dem alten Plugin
herüber:

- die Ziele mit ihren Nummern und dem gesammelten Stand
- die Spenden
- Name, Beträge, Rechtstexte, Aussehen und die Einstellungen des Alerts
- die einzeln vergebenen Rechte (`PaypalTipGoals.*` → `TipGoals.*`)

Zugang, Modus und Gebührensätze bleiben bei PayPal. Der Umzug passiert
genau einmal und nur, wenn hier noch keine Ziele stehen — sonst würde
zusammengeführt, was nicht zusammengehört, und die alten Tabellen
bleiben stehen. Eine Zeile im Log sagt, was passiert ist.

Neben einem PayPal-Plugin in **1.x** lässt sich dieses nicht
installieren: beide stellten `/tips` bereit.

## Das Aussehen ist das alte

Farben, Rundungen, Knöpfe, Schalter und das Karussell für die Ziele
kommen aus der alten Spendenseite — wer sie kannte, muss die neue nicht
erst wiedererkennen.

Ohne JavaScript liegen die Ziele untereinander, jede Karte mit einem
echten Auswahlknopf. Das sieht anders aus — spenden kann man trotzdem.

## Der Unterschied zu StreamElements und Streamlabs

Dort kommt eine Spende aus einer Schnittstelle und weiß nichts von
Zielen — sie landet deshalb immer auf dem obersten. **Hier wählt der
Spender**, und das ist der Sinn der Seite.

Darum verträgt sich dieses Plugin mit keinem der beiden: nebeneinander
zählte jede Spende doppelt. Der Marktplatz lässt das zweite gar nicht
erst installieren.

## Der Balken rotiert durch die Ziele

Alle 60 Sekunden wandert er zum nächsten Ziel, wie im alten System. Ein
Ziel **ohne Betrag** fällt aus der Rotation. Kommt eine Spende herein,
ändert sich der Betrag sofort.

Am Gerüst ändert das **nichts**: `tip_title`, `tip_current` und
`tip_goal` bleiben die Namen, an denen `data-bind` und `data-fill`
hängen.

## Genau einmal gebucht

Gebucht wird an einer Stelle, für jeden Anbieter gleich:
`Donations::complete()`. Die Bedingung `WHERE status <> 'captured'`
lässt von zwei gleichzeitigen Rückkehrern (Nachladen, zwei Tabs) nur
einen durch, und nur der erhöht das Ziel. Dazu kommt ein eindeutiger
Index auf die Einzugsnummer je Anbieter — und beim Anbieter selbst
seine eigene Sperre gegen doppeltes Einziehen.

Auf das Ziel kommt der **Netto**betrag, wenn der Anbieter ihn nennt —
der Balken soll nicht mehr zeigen, als ankommt. „Einfach so – kein
Ziel“ bucht auf keinen Balken.

## Ohne Impressum bleibt die Seite zu

Eine Seite, die Geld annimmt, muss sagen, wer es bekommt. Solange das
Impressum leer ist — oder kein Anbieter installiert —, antwortet
`/tips` mit einem Hinweis und einem 503. Der Besucher erfährt dabei
**nicht**, was fehlt.

## Die Rechtstexte gehören dir

Impressum, Datenschutz und AGB liefert das Plugin **nicht** mit: in
einem Impressum steht dein Name und deine Anschrift. Geschrieben werden
sie in den Einstellungen, je Text ein Reiter, Markdown ist erlaubt.
Verlinkt wird im Fuß nur, was wirklich da ist.

Das Häkchen „AGB und Datenschutzerklärung gelesen“ steht **immer** da —
es trägt auch die Altersbestätigung — und wird auch vom Server geprüft.

## Meldungen auf der Seite

In der Adresse steht nur ein Code (`?error=terms`), nie ein Satz. Was
der Code bedeutet, steht im Plugin. So lässt sich kein Link bauen, der
auf deiner Spendenseite einen fremden Text anzeigt.

Was ein Anbieter im Fehlerfall zurückgibt, bekommt der Spender nicht zu
sehen — es steht im Reiter *Spendenziele* und im Log.

## Der Spender bekommt kein Konto

Wer sich auf `/tips` anmeldet, bekommt ein mit `APP_KEY` signiertes
Cookie mit seinem Kanalnamen — mehr nicht. Das Twitch-Token aus der
Anmeldung wird **weggeworfen**. Freigaben verlangt die Anmeldung keine.

Anonym heißt anonym: der Name steht zwar in der Tabelle, aber weder der
Alert noch die Liste in der Verwaltung zeigen ihn.

## Rechte

| Recht | darf |
| --- | --- |
| `TipGoals.Global.View` | Ziele und Spenden sehen |
| `TipGoals.Global.Edit` | Ziele pflegen, Spendenseite, Texte und Zahlungsanbieter einrichten |

Die Anbieter-Plugins benutzen dieselben Rechte. Die öffentliche Seite
prüft **kein** Recht — das ist ihr Sinn.

## Einen Zahlungsanbieter schreiben

Ein Anbieter meldet sich über `tips.providers` an:

```php
$hooks->on('tips.providers', static function (array $anbieter) use ($app): array {
    $anbieter['meinanbieter'] = [
        'label'       => 'Mein Anbieter',
        'ready'       => true,            // eingerichtet?
        'fee_percent' => 2.5,             // fuer "Gebuehren uebernehmen"
        'fee_fixed'   => 0.35,
        'settings'    => '/display/goals/tips/meinanbieter',
        'start'       => static function (array $spende) use ($app): Response {
            // token, amount, login, display_name, goal_id, description
            Donations::markPending($app, $spende['token'], $nummerBeimAnbieter);

            return Response::redirect($adresseZumBezahlen);
        },
    ];

    return $anbieter;
});
```

`start()` wirft, wenn es nicht klappt — die Spende wird dann verworfen.
Zurück kommt der Spender über eine eigene Route **unter
`/tips/<schlüssel>/…`** (zwei Abschnitte — `/tips/{page}` beantwortet
alles mit einem). Dort nachschlagen und buchen:

```php
$spende = Donations::byReference($app, 'meinanbieter', $nummerBeimAnbieter);
Donations::complete($app, $spende['token'], $einzugsnummer, $brutto, $netto);

return PublicPage::done($app, true, $ueberschrift, $text);
```

## Für andere Plugins

Jede gebuchte Spende löst `tips.donation` aus:

```php
$hooks->on('tips.donation', static function (array $spende): void {
    // provider, login, name, amount, net, message, goal_id
});
```

## Was das Plugin speichert

`tip_goals` — ein Ziel je Zeile. `tip_donations` — eine Spende je Zeile
mit Anbieter, Betrag, Ziel, Nachricht, Zustand und den Nummern des
Anbieters. Dazu im Bereich `plugin:tip-goals`: Name, Beträge, die drei
Rechtstexte, Aussehen und Alert.

Abgeschlossene Spenden bleiben 90 Tage. Beim Entfernen des Plugins geht
alles mit; beim Anbieter bleibt selbstverständlich alles stehen.
