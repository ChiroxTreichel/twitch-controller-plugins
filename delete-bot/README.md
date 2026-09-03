# Löschbot

Eine Liste von Mustern. Passt eine Chatnachricht auf eines davon, wird
sie gelöscht.

Nach der Installation ist der Schalter **aus** und die Liste leer — ein
Werkzeug, das ungefragt anfängt, fremde Nachrichten zu löschen, wäre
eine unangenehme Überraschung.

## Muster

Ein Muster je Zeile. Jedes ist ein **regulärer Ausdruck ohne
Begrenzer** — genau wie im alten System, vorhandene Listen bleiben also
gültig.

```
cutt\s*\.\s*ly
bit\s*\.\s*ly/\S+
free\s*follower
```

Groß- und Kleinschreibung ist egal, und ein Muster darf über mehrere
Zeilen einer Nachricht greifen.

### Akzente werden abgeflacht

Vor dem Prüfen werden kombinierende Zeichen entfernt und Akzentbuchstaben
auf ihre Grundform gebracht. `fóllówer` und `fo` + U+0308 `llower` sind
danach beide `follower` — sonst umgeht man jeden Filter mit einem Trema.

Das flacht auch echte Umlaute ab: **`schön` wird zu `schon`.** Ein Muster
`schon` trifft also beides. Das Testfeld zeigt den abgeflachten Text mit
an, damit man sich nicht wundert.

## Ausprobieren

Das gibt es im alten System nicht. Tippe eine Nachricht ein, und die
Seite sagt:

- ob sie gelöscht würde
- **welches Muster** getroffen hat
- wie der Text nach dem Abflachen aussieht
- wie viele Muster übersprungen wurden, weil sie kaputt sind

Geprüft wird mit **derselben Methode** wie im Betrieb (`Words::check()`).
Ein Testfeld, das etwas anderes prüft als der Bot tut, wäre schlimmer als
gar keines. Gelöscht wird beim Testen nichts.

## Kaputte Muster fallen auf

Das alte System hat Regex-Fehler verschluckt (`@preg_match`): ein
Tippfehler im Muster fiel nie auf, es passte einfach nie. Wer eine
Sperre einrichtet und glaubt, sie greife, ist schlechter dran als wer
weiß, dass sie fehlt.

Hier stehen kaputte Muster als Warnung oben auf der Seite, und das
Testfeld zählt sie mit. Gespeichert werden sie trotzdem — ein halb
fertiges Muster soll man stehen lassen und weiterschreiben können.

### Zwei Abweichungen vom alten System

| | vorher | jetzt |
| --- | --- | --- |
| `/` im Muster | zerbrach am Begrenzer, Muster traf nie | wird maskiert und funktioniert |
| Unicode | Ausdruck lief auf Bytes, `.` traf ein halbes Zeichen | Schalter `u`, `.` trifft einen Buchstaben |

Wer bereits `\/` geschrieben hat, merkt davon nichts — maskiert wird nur
ein Schrägstrich ohne Gegenschrägstrich davor.

## Was der Bot nicht löschen kann

**Die eigenen Nachrichten des Kanalinhabers.** Das lässt Twitch
niemanden, auch keinen Moderator. Ein Treffer bei dir selbst steht
darum mit Begründung im Log, statt als Fehlschlag zu erscheinen — und
das Testfeld weist darauf hin.

Gelöscht wird mit dem Token des Kanalinhabers, der in seinem Kanal
immer Moderator ist. Nötig ist dafür `moderator:manage:chat_messages`;
der Kern fordert es an.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `DeleteBot.Global.View` | Musterliste sehen |
| `DeleteBot.Global.Edit` | Musterliste ändern |
| `DeleteBot.Global.Toggle` | Löschbot ein- und ausschalten |
| `DeleteBot.Global.Test` | Nachrichten ausprobieren |

Testen hat ein eigenes Recht, weil es nichts ändert: wer die Liste
nicht pflegen darf, soll trotzdem nachsehen können, warum eine
Nachricht verschwunden ist.

## Für andere Plugins

```php
$hooks->on('delete_bot.deleted', static function (array $message, string $muster): void {
    // z.B. mitzaehlen, wer wie oft auffaellt
});
```
