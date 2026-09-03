# Chatbefehle

Befehle im Chat. Zwei Reiter: **Grundbefehle** sind fest eingebaut und
haben eigene Einstellungen, **Eigene Befehle** legst du selbst an.

Braucht keine IRC-Verbindung und keine Zugangsdaten — gelesen und
geantwortet wird über die Kernfähigkeit *Chat*.

## Hauptschalter

Oben auf der Seite und zusätzlich in der Seitenleiste: aus heißt, im
Chat passiert nichts. Die eingestellten Befehle bleiben stehen.
Voreinstellung ist **an** — wer das Plugin installiert, will Befehle.

Der Schalter in der Seitenleiste ist von jeder Seite aus erreichbar:
mitten im Stream Ruhe herstellen, ohne erst hierher zu navigieren.

## Grundbefehle

| Befehl | Was er tut |
| --- | --- |
| `!befehle` | zählt alle verfügbaren Befehle alphabetisch auf |
| `!discord` | gibt den Anmeldelink nur an Zuschauer heraus, die dem Kanal lange genug folgen |

### `!discord` ist der besondere

Er ist der einzige Befehl, der vorher etwas nachfragt. Vier Dinge daran
sind leicht zu übersehen:

**`!discord <name>` ändert nur die Anrede, nicht die Prüfung.**
Geprüft wird immer der Schreiber. Wer selbst lange genug folgt, kann
den Link damit weiterreichen:

```
Zuschauer: !discord freund
Bot:       Hey @freund. Tritt doch meinem Discord-Server bei: …
```

Wer die Hürde nicht erfüllt, bekommt die Absage — ebenfalls an
`@freund` gerichtet. Die Hürde hängt am Schreiber, die Anrede ist nur
Höflichkeit.

**Der Kanalinhaber wird nicht geprüft.** Man kann sich nicht selbst
folgen; ohne diese Ausnahme käme er nie an seinen eigenen Link.

**Zwei Absagen, nicht eine.** „Folgt gar nicht" und „folgt noch nicht
lange genug, es fehlen noch 3 Tage" — die zweite sagt, wann es klappt.

**Twitch verwirft Wiederholungen.** Eine Nachricht, die mit der vorigen
desselben Absenders identisch ist, verschwindet lautlos. Fragen zwei
Leute hintereinander dasselbe, bekäme nur der erste eine Antwort.
Deshalb hängt an jeder Antwort eine wechselnde Anzahl von
`U+2063 INVISIBLE SEPARATOR` — für Twitch ein anderer Text, für
Zuschauer nichts.

### Einstellungen

| Feld | Bedeutung |
| --- | --- |
| **Anmeldelink** | die Discord-Einladung |
| **Alter in Tagen** | so lange muss man dem Kanal folgen; `0` heißt „folgen genügt" |

Ohne Anmeldelink antwortet der Befehl, dass er noch nicht eingerichtet
ist — er schweigt nicht.

## Eigene Befehle

Name ohne `!` und ein Antworttext, bis zu 400 Zeichen. Platzhalter:

| Platzhalter | wird zu |
| --- | --- |
| `{USER}` | `@login` des Schreibers |

```
!liebe   →  <3 <3 <3 <3 <3
!sauber  →  {USER} putzt hier mal durch den Chat
```

Erlaubt sind Kleinbuchstaben, Ziffern, Bindestrich und Unterstrich.
Namen von Grundbefehlen werden abgelehnt — sonst stellte man etwas ein,
das nie greift.

## Was `!befehle` aufzählt

Grundbefehle und eigene Befehle, alphabetisch. Ein anderes Plugin kann
seine eigenen beisteuern:

```php
$hooks->on('chat_commands.names', static function (array $namen): array {
    $namen[] = 'wuerfel';

    return $namen;
});
```

## Rechte

| Recht | erlaubt |
| --- | --- |
| `ChatCommands.Global.Toggle` | Chatbefehle ein- und ausschalten |
| `ChatCommands.Basic.View` / `.Edit` | Grundbefehle sehen / einstellen |
| `ChatCommands.Custom.View` / `.Edit` | eigene Befehle sehen / pflegen |

## Voraussetzungen

Der Kanal muss mit den Chat-Freigaben verbunden sein — `user:read:chat`,
`user:write:chat`, `user:bot`, `channel:bot`. `!discord` braucht
zusätzlich `moderator:read:followers`, um den Follow-Status zu prüfen.
Alle fordert der Kern von sich aus an; nach einem Update muss der Kanal
einmal neu verbunden werden.

Ist ein **Bot-Konto** verbunden, antwortet der Bot — sonst dein eigener
Account.
