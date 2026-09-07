# Live-Benachrichtigung – Discord

Beobachtet fremde Twitch-Kanäle und meldet über einen Discord-Webhook,
wenn einer davon live geht. Aus dem alten System übernommen, dort unter
*Networking → Live Benachrichtigung*.

**Chat** und **Shoutout** sind eigene Plugins und hängen sich hier ein.
Der **Alert im Overlay**, den die Legacy als drittes Ziel hatte, ist
nicht dabei — der kommt später wieder.

## Gemeldet wird der Übergang, nicht der Zustand

Das ist der Kern. Der Live-Zustand jedes Kanals steht in der Tabelle,
und nur wenn er von *offline* auf *live* springt, geht eine Nachricht
hinaus. Ohne gemerkten Zustand käme bei jedem Cron-Tick eine neue — alle
15 Sekunden, bis niemand den Discord-Kanal mehr abonniert hat.

Nachgesehen wird höchstens alle 30 Sekunden, und eine Runde ist **ein**
Twitch-Aufruf für alle beobachteten Kanäle. Das ist billig; eine
Meldung, die eine Minute zu spät kommt, ist die halbe Meldung.

Der Schalter in der Seitenleiste hält die **ganze Runde** auf, nicht nur
das Senden. Das ist Absicht: würde weiter mitgeschrieben, gälte ein
Kanal nach dem Wiedereinschalten als „war schon live", und seine Meldung
fiele aus.

## Kanäle als Kacheln

Bild, Name, ein Haken je Ziel, ein Kreuz in der Ecke — wie im alten
System. Wer sieben Kanäle beobachtet, findet den gesuchten am Bild und
nicht am Text.

Das **Profilbild wird nicht gespeichert.** Gespeichert veraltet es: wer
es bei Twitch wechselt, behielte hier das alte, bis irgendetwas es
nachzieht — und dieses "irgendetwas" wäre Code, den man pflegen muss.
Geholt wird es beim Anzeigen der Seite, in **einem** Aufruf für alle
Kanäle. Die Rechnung geht auf, weil die Liste kurz ist. Antwortet Twitch
nicht, zeigen die Kacheln den ersten Buchstaben — eine Seite ohne Bilder
ist besser als eine mit einer Fehlermeldung.

Die Haken *sehen aus* wie Kästchen und sind Absende-Knöpfe: ein echtes
`<input type="checkbox">` müsste beim Anklicken abschicken, und das
kann nur JavaScript. So ist die ganze Zeile anklickbar statt nur der
Kasten.

Das Kreuz entfernt **ohne Rückfrage**. Bei einer Löschung ist eine
Rückfrage sonst Pflicht; hier nicht: einen Kanal wieder aufzunehmen
kostet einen Tastendruck, und verloren geht dabei nur der Haken. Eine
Rückfrage je Kachel wäre teurer als der Fehlgriff, den sie verhindert.

## Kanäle

Aufnehmen über Login **oder Adresse** — `twitch.tv/twitchdev` wird zum
Login, weil man beim Aufnehmen meist die Adresse in der Hand hat. Der
Login wird bei Twitch nachgeschlagen, damit der richtige Anzeigename in
der Liste steht und ein Tippfehler sofort auffällt statt später durch
eine Benachrichtigung, die nie kommt.

Je Kanal ein Haken pro Ziel. Jeder Haken ist ein Absende-Knopf in seinem
eigenen Formular — kein JavaScript.

## Der Webhook ist ein Geheimnis

Wer die Adresse hat, kann in diesen Kanal schreiben, so oft er will. Sie
liegt darum verschlüsselt und wird **nie wieder angezeigt**, nur als
„hinterlegt". Das Feld steht deshalb leer da; leer heißt
*unverändert*, und zum Löschen gibt es einen eigenen Knopf.

Beim Senden wird `allowed_mentions` geleert: eine Live-Meldung soll nicht
alle im Kanal anpingen, auch wenn im Stream-Titel ein `@here` steht.

## Platzhalter

`{{login}}` · `{{display_name}}` · `{{title}}` · `{{game_name}}` ·
`{{url}}`

Leerraum in den Klammern ist erlaubt (`{{ display_name }}`), damit
Vorlagen aus dem alten System weiter passen. Ein **unbekannter**
Platzhalter bleibt stehen statt zu verschwinden: ein stillschweigend
leerer Platz sieht wie ein fehlender Wert aus, und man sucht ihn bei
Twitch.

## Rechte

| Recht | Bedeutung |
| --- | --- |
| `LiveNotify.Global.View` | darf Kanäle und Einstellungen sehen |
| `LiveNotify.Global.Edit` | darf Webhook, Vorlage und die Ziele je Kanal ändern |
| `LiveNotify.Channels.Add` | darf Kanäle aufnehmen |
| `LiveNotify.Channels.Delete` | darf Kanäle entfernen |
| `LiveNotify.Global.Test` | darf eine Testnachricht senden |
| `LiveNotify.Global.Toggle` | darf die Beobachtung ein- und ausschalten |

## Für weitere Ziele

Zwei Einhängepunkte:

```php
// Anmelden - Name, Platz in der Reihe, und ob es gerade kann.
$hooks->on('live_notify.targets', static function (array $ziele) use ($app): array {
    $ziele['mein-ziel'] = [
        'label' => translate('…'),
        'order' => 40,
        'ready' => $kann,
        'hint'  => $kann ? '' : translate('…'),
    ];

    return $ziele;
});

// Ein Kanal ist live geworden.
$hooks->on('live_notify.live', static function (array $info, array $ziele) use ($app): void {
    if (!in_array('mein-ziel', $ziele, true)) {
        return;
    }
    …
});
```

`$info` enthält `login`, `user_id`, `display_name`, `title`, `game_name`
und `started_at`. `$ziele` sind die für **diesen** Kanal angehakten
Schlüssel.

**Jedes Ziel entscheidet für sich.** Im alten System ging der Shoutout
nur hinaus, nachdem die Chat-Nachricht gelungen war — wer nur den
Shoutout wollte, bekam keinen. Diese Kopplung gibt es hier nicht.

`ready`/`hint` sind wichtiger, als sie aussehen: ein Haken, der
stillschweigend nichts tut, kostet einen Nachmittag. Fehlt eine
Freigabe oder eine Adresse, steht der Grund oben auf der Kanalseite.

Ein Ziel, das sich verschluckt, nimmt die anderen nicht mit — die Hooks
fangen pro Zuhörer ab.

Wer wissen muss, ob der eigene Stream läuft, nimmt
`LiveNotify::broadcasterIsLive($app)`. Das steht hier und nicht in den
Erweiterungen: es ist eine Frage an Twitch über den eigenen Kanal, und
dieses Plugin fragt Twitch ohnehin.

## Was das Plugin speichert

Die Tabelle `live_notify_channels` — ein Kanal je Zeile mit
Anzeigename, den angehakten Zielen (JSONB), dem Live-Zustand und den
Zeitstempeln. **Kein Profilbild** — siehe oben. Dazu im Bereich `plugin:live-notify` die Webhook-Adresse
(verschlüsselt), die Nachrichtenvorlage, der Hauptschalter und der
Zeitpunkt der letzten Runde.

Welche Ziele je Kanal an sind, steht als **Liste** und nicht als Spalte
je Ziel: ein neues Ziel soll keine Migration der Tabelle dieses Plugins
verlangen.
