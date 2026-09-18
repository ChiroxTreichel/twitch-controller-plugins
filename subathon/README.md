# Tools - Subathon

Ein Stream, der länger wird: jedes Abo, jede Handvoll Bits und jede
Spende schiebt das Ende nach hinten — bis zu einer Obergrenze.

## Hauptschalter

Am Menüpunkt *Tools → Subathon* sitzt ein Schalter, wie bei Timer und
Kanalpunkten. **Aus** heißt:

* Abos, Bits und Spenden werden **nicht gutgeschrieben** — sie kommen
  an, zählen aber nicht.
* Der Platz im Overlay wird gar nicht erst angemeldet, die Anzeige ist
  also weg (ohne die Browserquelle neu zu laden).
* Der Takt meldet nichts mehr.

Die Seite bleibt bedienbar: nachsehen und einstellen geht weiter, es
zählt nur nichts.

**Das ist nicht „Pausieren".** Die Uhr läuft weiter — wer anhalten
will, nimmt den Knopf auf der Übersicht. Wer den Hauptschalter mitten
im Lauf umlegt, verliert die Gutschriften dieser Zeit.

## Die Reiter

Übernommen aus dem Windows-Programm (`legacy/subathon-tool`), mit
denselben sieben Reitern unter *Tools → Subathon*:

| Reiter | was dort steht |
| --- | --- |
| Übersicht | Zustand, die große Zahl (läuft sichtbar ab), ein Knopf: Pausieren / Weiter / Zurücksetzen |
| Einstellungen | Startzeit, Obergrenze, Auslöser, was ein Abo/Bit/Cent bringt — **zu, solange er läuft** |
| Overlay | die sechs Farben der Anzeige |
| Nachrichten | die Laufschrift, eine je Zeile, mit Platzhaltern |
| Manuelles Buchen | Abo, Geschenk-Abos, Bits, Spende, Minuten von Hand — **ausgeblendet** |
| Happy Hour | Stunden, in denen Spenden mehr bringen |
| Verlauf | wer wann wie viel Zeit gebracht hat |

**Manuelles Buchen** ist aus. Abos, Bits und Spenden buchen sich von
selbst; von Hand braucht man es nur, wenn etwas auf einem Weg ankommt,
den dieses System nicht sieht — eine Überweisung, ein Geschenk im Chat.
Einschalten lässt es sich in den **Einstellungen des Plugins** —
*Konto → Plugins → Tools - Subathon*, dort wo auch „Ausschalten" und
„Entfernen" stehen. Dort und nicht im Reiter *Einstellungen*: der
gehört dem laufenden Subathon (Startzeit, Obergrenze, was ein Abo
bringt), und ob es einen Reiter gibt, ist eine Frage an das Plugin.
Ausgeblendet heißt aus: auch ein altes Formular bucht dann nichts
mehr.

Der **Verlauf** hieß im Programm „Info" und trug die Nachweise mit
sich. Die stehen jetzt hier im Paket; der Reiter zeigt nur noch den
Verlauf, und zwar als Tabelle — Wann, Wer, Was, Menge, Zeit. Ein Satz
je Zeile liest sich einzeln gut und in hundert Zeilen gar nicht. Ein
Strich in der Spalte *Zeit* heißt: es kam an, während nichts lief.

## Was Zeit bringt

**Abos** bringen die eingestellten Minuten; Stufe 2 und 3 entsprechend
ihrem Preis. Was ein Abo kostet, steht in den **Einstellungen des
Plugins** — im Programm standen 4,99 / 7,99 / 19,99 fest im Code, und
Twitch verlangt weder überall dasselbe noch auf Dauer. Gebraucht wird
daraus nur das **Verhältnis**: wer alle drei verdoppelt, ändert
nichts.
**Bits** und **Spenden** rechnen über „Bits pro Sub" und „Cent pro
Sub" — daraus ergeben sich die Sekunden je Bit und je Cent, und die
stehen im Reiter daneben.

**Spenden** zählen von **PayPal**, **StreamElements**, **StreamLabs**
und **Throne**. Die ersten drei melden sich über den Haken
`tips.donation`, Throne über sein Ereignis — das Plugin muss dafür
jeweils installiert sein.

Abos, Geschenk-Abos und Bits kommen über die **EventSub des Kerns**.
Das Programm brachte dafür eigene Twitch-Tokens und eine eigene
Websocket-Verbindung mit; die fünf Zeilen für Zugangsdaten im Reiter
*Einstellungen* sind deshalb weggefallen — alles andere dort ist
1:1 geblieben.

Ein geschenktes Abo meldet Twitch **zweimal** (als `subscribe` mit
`is_gift` und als `subscription.gift`). Gebucht wird nur der zweite
Weg, sonst zählt es doppelt.

## Zu, solange er läuft

Sobald der Subathon angefangen hat, lassen sich die Einstellungen nicht
mehr ändern — Startzeit, Obergrenze und die Werte je Abo, Bit und Cent.
**Pausieren hilft nicht**: pausiert ist gestartet.

Die Startzeit mitten im Lauf zu verschieben hieße, das Ende zu
verschieben, nachdem alle es gesehen haben. Und „Minuten pro Sub"
nachträglich zu ändern hieße, dass zwei Abos derselben Stunde
verschieden viel gebracht haben, ohne dass es irgendwo steht.

Die Zahlen bleiben sichtbar — man will ja wissen, womit gerade
gerechnet wird. Vor dem Start und nach dem Ende ist alles offen: davor
ist nichts passiert, danach ist alles vorbei.

Die Sperre sitzt in der Route und nicht nur an den Feldern: ein
`readonly` im HTML ist eine Bitte, keine Zusage.

## Die Happy Hour

Eine Stunde am Tag, in der **Spenden** mehr bringen — Abos und Bits
nicht, so war es im Programm auch. Drei Arten:

| | |
| --- | --- |
| Double-Time | die Zeit zählt doppelt |
| Extend-Time-Only | die Spende verlängert **nur die Obergrenze**, nicht die Zeit |
| Extend-Time | beides |

*Extend-Only* ist der interessante Fall: die Spende macht den Stream
nicht länger, sondern macht es **möglich**, ihn später länger zu
machen.

## Nichts läuft im Sekundentakt

Das Programm zählte jede Sekunde hoch und schrieb die Pausenzeit in
die `config.json` — eine Datei, jede Sekunde, für einen Wert, den man
auch ausrechnen kann. Hier stehen drei Zahlen in den Einstellungen:

```
Ende = Start + Dauer + Pause
```

Die laufende Anzeige rechnet der Browser, und der kann das. Über die
Leitung geht nur, was sich wirklich ändert.

## Das Overlay

Dieselbe Anzeige wie im Programm: eine Leiste aus drei Abschnitten —
*abgelaufen*, *noch zu streamen*, *noch kaufbar* —, darunter eine
Legende und eine Laufschrift, die alle zehn Sekunden wechselt. Wird ein
Abschnitt zu schmal für seine Zahl, klappt sie als Fahne darunter, mit
einem Dreieck als Zeiger.

Ohne Obergrenze gibt es nur **eine** Zahl, groß in der Mitte: wie lange
noch. Balken ohne „wovon" ergeben nichts.

Die **Legende** zeigt sich nur kurz: 15 Sekunden, dann 4:45 Minuten
nicht. Sie erklärt die drei Farben, und das liest man einmal — 48
Stunden im Bild stehen muss sie dafür nicht. Ausgeblendet wird sie über
die Deckkraft, damit die Laufschrift darunter nicht bei jedem Wechsel
springt.

Die Farben stehen im Reiter *Overlay*. Wo der Platz liegt und wie weit
vorne, entscheidet die Overlay-Seite unter *Konto → Overlay* — wie bei
jedem anderen Platz auch.

## Die Platzhalter der Laufschrift

`{{Conf.MIN_PER_SUB}}`, `{{Conf.BITS_PER_SUB}}`,
`{{Conf.SECONDS_PER_BIT}}`, `{{Conf.CENT_PER_SUB}}`,
`{{Conf.SECONDS_PER_CENT}}`, `{{Conf.CENT_PER_HOUR}}`,
`{{Conf.EURO_PER_HOUR}}`, `{{Conf.EURO_UNTIL_FULL}}`,
`{{Conf.OWNER}}`, `{{Conf.HAPPY_HOUR_START}}`,
`{{Conf.HAPPY_HOUR_END}}` — dazu die sechs Farben als `{{Color.DONE}}`
und so weiter. Der aktuelle Wert steht im Reiter daneben.

Das Präfix `Conf.` ist **freiwillig**: beide Schreibweisen gelten. Im
Programm wurden zwei der Platzhalter nur ohne Präfix ersetzt, in der
mitgelieferten Nachrichtenliste standen sie aber mit — die blieben im
Stream als geschweifte Klammern stehen.

## Rechte

| Recht | erlaubt |
| --- | --- |
| `Subathon.Global.View` | die Seite sehen |
| `Subathon.Global.Edit` | einstellen, pausieren, zurücksetzen |
| `Subathon.Global.Book` | Zeit von Hand buchen |

Buchen ist ein eigenes Recht: wer im Stream eine Spende nachträgt, muss
deshalb nicht auch die Obergrenze verstellen dürfen.

## Was das Plugin speichert

`subathon_log` — eine Buchung je Zeile, mit Zeitpunkt. Dazu im Bereich
`plugin:subathon`: Start, Dauer, Obergrenze, Pause, die Werte je
Abo/Bit/Cent, die Farben, die Nachrichten und die Happy Hours.

Beim Entfernen des Plugins geht alles mit — auch die laufende Zeit.

## Was weggefallen ist

Die `seconds_per_follow` aus der `config.json`: sie stand dort, wurde
aber nirgends benutzt — das Programm hat `channel.follow` nie
abonniert.
