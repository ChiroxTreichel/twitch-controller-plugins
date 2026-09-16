# Raids - Anfragen

Eine öffentliche Seite unter **`/raidme`**: fremde Streamer melden sich
mit Twitch an und bitten um einen Raid. Der Kanalinhaber nimmt an oder
lehnt ab; wer angenommen ist, taucht im Live-Reiter von Raids auf,
sobald er streamt.

Braucht **Raids**.

## Die öffentliche Seite

`/raidme` — kurz genug, um sie in einen Chat zu tippen oder in ein
Twitch-Panel zu schreiben. Die Adresse steht auch im Reiter *Anfragen*,
als Text und nicht nur als Link: man muss sie **lesen** können.

Es ist eine ganze Seite mit eigenem Stylesheet und keine Ansicht der
Verwaltung. `admin.css` bringt Navigation, Tabellen und Karten mit, und
davon braucht diese Seite nichts — eine Karte in der Mitte, darin ein
Knopf. Sie trägt `noindex`: öffentlich ist sie, weil sie ohne Anmeldung
erreichbar sein muss, nicht damit sie in einer Suchmaschine steht.

## Kein Konto für Besucher

Wer sich dort anmeldet, bekommt **kein Konto in dieser Verwaltung**.
Er bekommt ein signiertes Cookie mit seinem Twitch-Namen und nichts
weiter.

Warum ein Cookie und keine Sitzung in der Datenbank: der Besucher ist
kein Benutzer dieses Systems, und eine Zeile je Neugierigem wäre eine
Tabelle, die von außen wachsen kann. Was im Cookie steht, ist ohnehin
öffentlich — sein Kanalname. Es darf nur niemand *Fremdes*
hineinschreiben, und dafür ist es mit `APP_KEY` signiert. Das
Ablaufdatum wird serverseitig geprüft und nicht dem Browser überlassen:
ein Datum, das der Browser verwaltet, verlängert man, indem man den
Wert abschreibt und neu setzt.

Das **Twitch-Token aus dieser Anmeldung wird weggeworfen.** Gebraucht
wird nur die Auskunft, wer da ist; ein gespeichertes Token eines fremden
Kanals wäre ein Schlüssel, für den es kein Schloss gibt. Verlangt werden
deshalb auch **keine Freigaben** — je weniger man verlangt, desto eher
drückt jemand auf „erlauben".

### Formular-Merkmal ohne Sitzung

Der Kern leitet seinen CSRF-Wert aus dem Sitzungscookie ab. Auf dieser
Seite gibt es keine Sitzung — derselbe Wert wäre für jeden Besucher
gleich und damit keiner. Also aus dem Besucher-Cookie: gleiches
Verfahren, anderes Geheimnis. Wer kein Cookie hat, hat auch keinen
Knopf.

## Was eine Anfrage ist

Eine Zeile je Kanal. Eine zweite Anfrage ist keine neue Frage, sondern
dieselbe lauter — wer sich erneut meldet, überschreibt seine alte Zeile.

- **offen** — wartet auf eine Entscheidung. Blockiert eine neue Anfrage:
  sonst wäre der Knopf ein Weg, die Liste vollzuschreiben, während der
  Kanalinhaber noch überlegt.
- **angenommen** — steht im Live-Reiter, sobald der Kanal streamt.
- **abgelehnt** — bleibt stehen, damit der Anfrager auf `/raidme` liest,
  woran er ist. Und er darf **erneut fragen**: eine Ablehnung gilt für
  den Abend, an dem sie ausgesprochen wurde, nicht für immer.

Entschiedene Anfragen werden nach **14 Tagen** weggeräumt. Danach weiß
das niemand mehr, und die Tabelle soll nicht mitwachsen. Das läuft im
Cron-Tick, aber höchstens einmal am Tag: der Worker tickt alle 15
Sekunden, und eine Aufräumabfrage je Tick wären viertausend am Tag für
eine Tabelle, in der sich meistens nichts geändert hat.

Als Notbremse sind 200 offene Anfragen die Grenze. Nicht gegen den
einzelnen Anfrager — dagegen hilft die offene Anfrage —, sondern gegen
den Fall, dass die Adresse irgendwo landet, wo sie tausend Leute öffnen.

## Angenommen heißt nicht Favorit

Wer angenommen ist, kommt über `raids.live_logins` in den Live-Reiter,
nicht in die Favoriten. Eine Anfrage gilt für heute Abend, ein Favorit
für immer.

Ein so dazugelegter Kanal steht nicht in `raid_channels` und hat dort
kein Bild — `Channels::live()` holt es dann frisch bei Twitch nach.

## Offen und geschlossen

Ein Schalter im Reiter, Vorgabe **offen**. Geschlossen ist nicht
dasselbe wie abgeschaltet: die Liste bleibt, angenommene Kanäle bleiben
im Live-Reiter, es kommt nur nichts Neues dazu. Für die Abende, an denen
man keine Anfragen will — und dafür soll man nicht deinstallieren
müssen.

Der Schalter steht deshalb auch **nicht** in der Navigation: dort sitzt
der Schalter, der ein Plugin stilllegt, und das ist eine andere Frage.

## Kein Profilbild in der Tabelle

Gespeichert veraltet es, sobald jemand sein Bild bei Twitch wechselt,
und dann bräuchte es Code, der es nachzieht. Geholt wird es beim
Anzeigen des Reiters, in **einem** Aufruf für alle. Antwortet Twitch
nicht, zeigen die Kacheln den ersten Buchstaben.

Die **Twitch-ID** steht trotzdem in der Tabelle: sie ändert sich nie,
und der Raid braucht sie. Sie zu speichern erspart einen Aufruf in dem
Moment, in dem es schnell gehen soll.

Die öffentliche Seite zeigt gar kein Bild. Wer sie ansieht, weiß, wie er
aussieht — der Name genügt für die einzige Frage, die er hat: bin ich
als der Richtige angemeldet?

## Rechte

| Recht | Darf |
| --- | --- |
| `RaidsRequests.Global.View` | die eingegangenen Anfragen sehen |
| `RaidsRequests.Global.Decide` | annehmen, ablehnen, öffnen, schließen |

Die öffentliche Seite prüft **kein** Recht — das ist ihr Sinn. Was dort
passieren kann, ist genau eines: sich selbst auf die Liste setzen. Keine
Einstellung, keine Auskunft über andere.

## Was das Plugin speichert

Die Tabelle `raid_requests` — ein Kanal je Zeile mit Twitch-ID,
Anzeigename, Zustand und den beiden Zeitstempeln. Dazu `open` und
`cleaned_at` im Bereich `plugin:raids-requests`.

Beim Entfernen des Plugins gehen Tabelle und Seite mit. Die Cookies der
Besucher bleiben in ihren Browsern stehen und laufen dort ab — sie
zeigen dann auf nichts.
