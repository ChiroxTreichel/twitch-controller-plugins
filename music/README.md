# Musik - Spotify

Songwünsche über Spotify, wie im alten `musik.talutah.de`: eine
öffentliche Seite unter **`/music`**, auf der sich Zuschauer nach
Twitch-Anmeldung einen Titel wünschen, die Warteschlange im **Overlay**,
und eine **Bannliste** für Titel, Interpreten, Genres und Zuschauer.

Gespielt wird weiterhin in *deinem* Spotify, auf deinem Rechner. Das
Plugin legt nur in die Warteschlange und liest, was läuft.

## Was du einmal einrichtest

1. Im [Spotify-Entwicklerkonto](https://developer.spotify.com/dashboard)
   eine App anlegen.
2. Client-ID und Secret unter *Musik - Spotify* eintragen. Das Secret
   wird **verschlüsselt** abgelegt und nie wieder angezeigt; ein leeres
   Feld heißt „nicht ändern".
3. Die **Rückkehradresse** aus den Einstellungen im Entwicklerkonto
   eintragen — Zeichen für Zeichen. Spotify vergleicht stur.
4. *Bei Spotify anmelden*.

Die Freigaben sind genau die des alten Systems und keine mehr:
Warteschlange füllen, Wiedergabe lesen, laufender Titel, zuletzt
gespielt, Bibliothek ergänzen.

## Die Regeln gehören dir

Im alten System standen sie als fünf `<li>` im HTML — wer sie ändern
wollte, änderte eine PHP-Datei. Hier stehen sie in den Einstellungen,
eine je Zeile. Wer nichts einträgt, bekommt die fünf von damals.

Der Zuschauer muss sie einmal annehmen, bevor er wünschen darf.

## Grenzen ignorieren

Das Recht **`Music.Limits.Ignore`** erlaubt zu wünschen, *obwohl* die
Songwünsche aus sind, und **ohne** Wartezeit dazwischen. Beides in einem
Recht, weil es eine Rolle ist und nicht zwei: wer Musik einbauen darf,
während für die Zuschauer zu ist, soll nicht daneben fünfzehn Minuten
warten.

Es hebt die **Bannliste nicht** auf. Ein gesperrter Titel ist eine
Entscheidung über den Inhalt, keine Grenze für den Andrang — wer ihn
will, nimmt ihn von der Liste.

Im alten System war das eine Liste von zwei Twitch-IDs im Code. Hier
hängt es am Rechtesystem: der Besucher auf `/music` ist kein
angemeldeter Benutzer, aber wenn es zu seiner Twitch-Kennung einen
Benutzer hier drin gibt, gelten dessen Rechte.

## Die Suche

Sie geht **ohne `limit`** an Spotify. Mit einem Wert — auch mit einem
aus dem erlaubten Bereich 1–50 — antwortet Spotify mit
`400 Invalid limit`; woran es das festmacht, sagt es nicht. Also gilt
Spotifys Vorgabe (20 Treffer), und gekürzt wird hier. Zwanzig Treffer
sieht sich ohnehin niemand ganz an.

Der **Markt** geht mit: er sorgt dafür, dass nur vorgeschlagen wird,
was sich hier auch abspielen lässt. Das Land kommt aus dem
**verbundenen Konto** (`/me` → `country`) und wird beim Verbinden
mitgespeichert — wer aus Österreich streamt, bekommt sonst Titel
angeboten, die er nicht abspielen kann. Steht dort nichts, gilt `DE`.

Wenn die Suche einmal nichts findet, obwohl es etwas zu finden gäbe:
*Einstellungen → Suche prüfen*. Der Knopf schickt dieselbe Anfrage in
mehreren Fassungen an Spotify und zeigt, was jeweils zurückkommt —
Status und Spotifys eigener Text. Eine leere Trefferliste sieht sonst
aus wie „nichts gefunden", auch wenn Spotify abgewiesen hat. Beim nächsten *Neu anmelden* steht das
richtige Land drin.

## Die Bannliste

Vier Arten, in der Prüfreihenfolge des alten Systems:

| | |
| --- | --- |
| Titel | die genaueste Auskunft, darum zuerst |
| Interpret | über die Spotify-ID, nicht über den Namen |
| Genre | Spotify hängt Genres an den **Interpreten**, nicht an den Titel |
| Zuschauer | über den Twitch-Namen |

Jede Art ist ein **Reiter**, und der Reiter *ist* die Art: auf „Titel"
sucht man Titel, auf „Interpreten" Interpreten. Die Zahl steht am
Reiter, sonst müsste man jeden aufmachen, um zu sehen, wo etwas
drinsteht. Ein fünfter Reiter zeigt die letzten Wünsche.

Jeder Reiter hat ein **Eingabefeld** — man hat ja oft schon in der
Hand, was man sperren will. Bei Genres und Zuschauern ist das ein Name;
bei Titeln und Interpreten ein **Spotify-Link**, denn ihre Kennung
tippt niemand ab. Den Namen holt der Server dann selbst, sonst stünde
in der Liste `4cOdK2wGLETKBW3PvgPWqT`.

Ein Interpreten-Link auf dem Titel-Reiter wird abgewiesen: die
Kennungen sind gleich gebaut, und die Sperre hätte einfach nie
gegriffen.

Die **Suche** bleibt daneben — für alles, wovon man keinen Link hat.
Genres und Zuschauer werden kleingeschrieben verglichen.

Die Reihenfolge entscheidet, **was in der Absage steht**: ein gesperrter
Titel eines gesperrten Interpreten wird als Titel gemeldet, und das ist
die Auskunft, mit der der Zuschauer etwas anfangen kann.

## Die öffentliche Seite

Aufbau, Texte und Knopfbeschriftungen sind die des alten Systems.
Geändert sind nur die Farben — das Dunkel dieses Systems statt des
hellen Blau von damals.

**Zwei Spalten.** Links die Songwünsche, rechts die Warteschlange.
Oben links „Regeln anzeigen", oben rechts „Einstellungen" für alle, die
sie sehen dürfen.

Links **drei Reiter**:

| | |
| --- | --- |
| Favoriten | die eigene Merkliste, „Senden" und „Löschen" je Eintrag |
| Suche | tippen und aus den Treffern „Auswählen" oder „Favorit" |
| Manuell eintragen | der Spotify-Teilen-Link, wie gehabt |

Die **Suche** ist der wichtigste davon: mit ihr kommt auch jemand
zurecht, der selbst kein Spotify hat und deshalb keinen Teilen-Link
kopieren kann.

Steht die Wartezeit noch, steht statt des Feldes der **Zähler** — „Du
kannst dir in 12:25 einen Song wünschen." — und er läuft auch im
Fenstertitel mit.

Rechts drei Abschnitte in der Reihenfolge der *ersten* Fassung des
alten Systems: **Läuft gerade**, **Zuletzt gespielt** (drei Titel) und
**Als Nächstes**. Der mittlere war später verschwunden; er beantwortet
„wie hieß das eben nochmal?", und diese Frage kommt sonst im Chat.

Der laufende Titel fällt aus „Zuletzt gespielt" heraus — Spotify führt
ihn dort schon, sobald er ein paar Sekunden läuft, und zweimal
untereinander sieht nach Fehler aus.

Jede Zeile hat **„Favorit"**, solange der Titel nicht schon gemerkt
ist. Am laufenden steht zusätzlich **„Bannen"** — im alten System an
zwei fest eingetragene Twitch-Kennungen gebunden, hier an das Recht
*Sperren verwalten*.

Das **Regelfenster** geht von selbst auf, solange die Regeln nicht
angenommen sind, und lässt sich dann nicht wegklicken. Danach ist es
der Knopf oben links.

## Der Hinweis im Chat

Ist das **Timer-Plugin** installiert, steht in den Einstellungen eine
Karte *Hinweis im Chat*: Intervall, Min. Zeilen und **zwei** Texte —
einer für „Songwünsche sind offen", einer für „sind zu". Welcher
gepostet wird, entscheidet der Schalter im Moment des Postens.

Einen eigenen Ein/Aus-Haken gibt es nicht: die **Textfelder sind der
Schalter**. Sind beide leer, postet der Timer nicht; steht nur einer
da, postet er nur in dessen Zustand.

Gezählt, gewartet und gepostet wird im Timer-Plugin (Haken
`timers.external`); hier steht nur, *was* gesagt wird. Ohne das Plugin
gibt es die Karte nicht — sie würde etwas einstellen, das niemand
postet.

**Läuft gerade keine Musik, postet er nicht.** Ein Hinweis auf die
Seite ist sonst eine Einladung zu einer leeren Warteschlange. Gefragt
wird dafür bei Spotify (`/me/player/currently-playing`), und zwar in
dem Moment, in dem der Timer dran ist — nicht im zuletzt gemerkten
Zustand, denn den schreibt der Takt nur bei Änderung.

**Pausiert zählt als „läuft nicht".** Das alte System war da
großzügiger; dort galt ein pausierter Player als laufend.

## Im Overlay

Ein eigener Platz mit Bild, Titel, Interpret, „gewünscht von" und einem
Fortschrittsbalken — wie das alte `obs.php`. **Breite**, **Höhe**,
**Abstand von links**, **Abstand von oben** und das **Farbschema**
stehen in den Einstellungen.

Das Farbschema ist **dunkel** wie der Rest dieses Systems oder **hell**
wie das alte `obs.php` — weißer Grund mit schwarzer Schrift. Auf einem
hellen Spiel verschwindet ein dunkler Kasten, auf einem dunklen ein
heller; darum die Wahl.

Die beiden Abstände verschieben den Kasten von der **linken oberen
Ecke** aus. Solange beide 0 sind, bleibt er unten links wie bisher —
wer nie einen Abstand eingetragen hat, soll nach einem Update nicht
suchen müssen, wo seine Musik hin ist.

Größe, Stelle und Farbschema greifen beim **Neuladen** der
Browserquelle.

Der Balken läuft **im Browser** weiter. Der Worker meldet sich alle
fünfzehn Sekunden und nur bei Änderung; ein Balken, der so nachgeführt
würde, stünde dreizehn Sekunden still und spränge dann.

## Was anders ist als früher — und warum

| früher | jetzt |
| --- | --- |
| `banned.json`, `who.json`, `favoriten/*.json` | Tabellen |
| Abkühlzeit als **Cookie** | beim Wunsch in der Datenbank |
| `twitchid`/`twitchname` als **unsignierte** Cookies | signiert, über die Twitch-App dieser Installation |
| `Cronjob.php` als Endlosschleife von Hand gestartet | `cron.tick` im Worker |
| Dateisperre für den Refresh-Token | Datenbanksperre |

Das Cookie ist der wichtigste Punkt: es lag beim Zuschauer, und wer es
löschte, durfte sofort wieder — die Abkühlzeit war eine Bitte. Und
unsignierte Cookies hießen: wer sie im Browser ändert, ist jemand
anderes; die Zuschauer-Bannliste war damit ebenfalls eine Bitte.

Die Dateisperre trägt hier nicht, weil Webserver und Worker getrennte
Container sind. Sie ist auch kein Detail: Spotify dreht den
Refresh-Token bei jeder Erneuerung weiter, und zwei gleichzeitige
Erneuerungen beenden die Verbindung — mitten im Stream.

Weggelassen habe ich `blocked_until`: die Datei wurde geschrieben, aber
die Stelle, die sie las, war auskommentiert. Ein 429 steht jetzt im Log.

## Was das Plugin speichert

`music_bans` — eine Sperre je Zeile. `music_wishes` — ein Wunsch je
Zeile, 30 Tage lang. `music_favorites` — die Merkliste je Zuschauer.
Dazu im Bereich `plugin:music`: die verschlüsselten Zugangsdaten, die
Abkühlzeit, die Regeln und die Größe im Overlay.

Beim Entfernen des Plugins geht alles mit — die Bannliste auch. Die ist
über Monate gewachsen und lässt sich aus Spotify nicht zurückholen; wer
sie behalten will, schreibt sie vorher heraus.

## Das Panel für OBS

**`/music/panel`** liefert reinen Text — Songname, Interpret, wer ihn
sich gewünscht hat, und die nächsten fünf aus der Warteschlange. Genau
wie `panel.php` im alten System, und aus demselben Grund ohne HTML: es
ist für eine **Textquelle in OBS** gedacht, die eine Adresse ausliest,
und die zeigt HTML als HTML an.

Die Adresse steht in den Einstellungen zum Herauskopieren.

## Noch nicht da

Die Steuerung des alten Panels — Lautstärke, weiter, zurück, „in die
Bibliothek". Der Draht dorthin (`Spotify::setVolume`, `next`,
`previous`, `saveTrack`) steht schon.
