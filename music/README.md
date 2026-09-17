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

## Die Bannliste

Vier Arten, in der Prüfreihenfolge des alten Systems:

| | |
| --- | --- |
| Titel | die genaueste Auskunft, darum zuerst |
| Interpret | über die Spotify-ID, nicht über den Namen |
| Genre | Spotify hängt Genres an den **Interpreten**, nicht an den Titel |
| Zuschauer | über den Twitch-Namen |

Titel und Interpreten kommen aus der Suche — ihre Kennung tippt niemand
von Hand. Genres und Zuschauer werden eingetippt und kleingeschrieben
verglichen.

Die Reihenfolge entscheidet, **was in der Absage steht**: ein gesperrter
Titel eines gesperrten Interpreten wird als Titel gemeldet, und das ist
die Auskunft, mit der der Zuschauer etwas anfangen kann.

## Im Overlay

Ein eigener Platz mit Bild, Titel, Interpret, „gewünscht von" und einem
Fortschrittsbalken — wie das alte `obs.php`, nur im dunklen Stil statt
auf weißem Grund. Breite und Höhe stehen in den Einstellungen.

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

## Noch nicht da

Die **Merkliste** („Favoriten") hat ihre Tabelle, aber noch keine
Oberfläche. Ebenso das **Panel** des alten Systems — Lautstärke, weiter,
zurück, „in die Bibliothek". Beides kommt als eigener Schritt; der
Draht dorthin (`Spotify::setVolume`, `next`, `previous`, `saveTrack`)
steht schon.
