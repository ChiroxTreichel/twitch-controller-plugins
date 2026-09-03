# Alerts

Der Rahmen für Alerts im Stream. Dieses Plugin zeigt **selbst keine
Alerts** — es bringt die Fläche, die Warteschlange und die
Grundeinstellungen mit. Was angezeigt wird, liefern Plugins wie
**Twitch-Alerts**.

Wer Twitch-Alerts installiert, bekommt dieses hier automatisch dazu.
Einzeln installieren muss man es nur, wenn man selbst ein Alert-Plugin
schreibt.

## Was drin steckt

- **Der Bereich «Anzeigen → Alerts»** mit Reitern. Andere Plugins
  hängen ihre Reiter dort ein.
- **Ein Hauptschalter** — alle Alerts auf einmal aus, ohne jeden
  einzelnen anzufassen. Praktisch, wenn im Stream gerade Ruhe sein
  soll.
- **Die Fläche im Overlay**: waagerecht mittig, Abstand von oben
  einstellbar, Breite einstellbar.
- **Eine Warteschlange.** Bei einem Raid kommen fünf Alerts auf einmal
  — sie laufen nacheinander, nicht übereinander.
- **Größe und Dauer** für alle Alerts gemeinsam.

## Nach der Installation

Das Plugin muss unter *Konto → Plugins* **aktiviert** werden. Danach
erscheint *Anzeigen → Alerts*.

Damit die Alerts auch im Stream ankommen, muss die Overlay-Fläche in
OBS eingebunden sein — siehe *Konto → Overlay*. Zum Prüfen gibt es
unter *Anzeigen → Alerts* einen Knopf **Test senden**.

## Aussehen

Übernommen aus dem alten System: zentrierte Spalte, Video oben,
darunter der Text in 32 Pixeln, fett, mit hartem Schatten — damit
weiße Schrift auch auf hellem Spielbild lesbar bleibt. Eingesetzte
Namen und Beträge erscheinen in Lila.

## Für Plugin-Autoren

Einen Reiter anmelden:

```php
$hooks->on('alerts.tabs', function (array $tabs): array {
    $tabs['mein-alert'] = [
        'label'  => 'Mein Alert',
        'order'  => 10,
        'render' => static fn (): string => '…HTML…',
    ];
    return $tabs;
});
```

Einen Alert schicken:

```php
use TwitchController\Plugin\Alerts\Alerts;

Alerts::send($app, [
    'text'     => '{{ username }} hat neu abonniert',
    'values'   => ['username' => $name],
    'video'    => '/uploads/alerts/sub.webm',
    'audio'    => '/uploads/alerts/sub.mp3',
    'duration' => 8,
]);
```

Die Platzhalter werden **auf dem Server** eingesetzt und dabei
escaped. Ein Twitch-Name mit `<script>` darin wird zu Text, nicht zu
Code. Fehlt der Wert zu einem Platzhalter, verschwindet er — ein
`{{ tier }}` mitten im Stream sieht nach Fehler aus.

## Voraussetzungen

- Twitch-Controller ab Fassung 2.0.0
- keine weiteren Plugins
