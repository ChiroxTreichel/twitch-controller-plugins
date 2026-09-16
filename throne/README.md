# Throne – Alerts

Meldet, was auf deiner [Throne](https://throne.com)-Wunschliste
passiert: **Geschenke**, **Spenden** und **erreichte Sammelziele** —
als Alert im Stream und als Zeile im Aktivitäten-Feed.

Braucht **Alerts**, wenn im Stream etwas erscheinen soll. Ohne läuft
der Rest genauso: die Ereignisse landen im Feed.

## Drei Ereignisse, und sie sind nicht dasselbe

| | |
| --- | --- |
| **Geschenk** | jemand kauft dir einen Gegenstand |
| **Spende** | jemand gibt Geld dazu |
| **Sammelziel erreicht** | ein Wunsch ist voll — *ohne Käufer*, das ist der Abschluss von vielen |

Beim Sammelziel gibt es deshalb keinen Namen. Im Feed steht dort, *was*
voll geworden ist; im Alert kannst du `{{ item }}` benutzen.

## Der Webhook steht offen — und ist trotzdem zu

Throne kann sich hier nicht anmelden, also nimmt die Adresse jede
Anfrage entgegen. Was sie schützt, ist die Unterschrift: Throne
unterschreibt jede Meldung mit Ed25519, und unterschrieben wird
`<zeitstempel>.<körper>`.

Der Zeitstempel gehört mit hinein, und er darf nicht älter als **fünf
Minuten** sein. Sonst ließe sich eine mitgeschnittene Meldung später
erneut abschicken — die Unterschrift bliebe ja gültig.

Was nicht durchkommt, bekommt `403` und **keine Begründung**: wer die
Adresse abklopft, soll daraus nichts lernen. Der Grund steht im Log.

**Ohne hinterlegten Schlüssel kommt gar nichts durch**, auch keine
echte Meldung. Das ist Absicht — eine Adresse, die ungeprüft alles
annimmt, wäre schlimmer als eine, die schweigt.

## Einrichten

1. *Networking → Throne* öffnen
2. Die dort angezeigte Adresse bei Throne als Webhook eintragen
3. Den öffentlichen Schlüssel aus den Throne-Einstellungen hier
   hinterlegen — 64 hexadezimale Zeichen

Der Schlüssel wird **verschlüsselt** abgelegt und nie wieder angezeigt.
Ein öffentlicher Schlüssel ist zwar kein Geheimnis, aber wer ihn
austauschen kann, kann sich eigene Ereignisse unterschreiben. Ein
leeres Feld heißt „nicht ändern"; zum Löschen gibt es einen eigenen
Knopf.

## Beträge

Throne schickt Cent. Ohne die Umrechnung stünde im Stream das
Hundertfache — bei kleinen Beträgen fällt das nicht einmal sofort auf.

## Was im Alert steht

Drei Texte, je einer pro Fall, mit `{{ username }}`, `{{ amount }}`,
`{{ item }}` und `{{ message }}`. Die Vorgaben sind die des alten
Systems.

Der Name des Gegenstands steckt **nicht** in `{{ message }}` — sonst
erschiene er doppelt, sobald ein Text beide benutzt.

## Was das Plugin speichert

Keine eigene Tabelle. Die Ereignisse liegen in der Tabelle des Kerns —
dort, wo auch die von Twitch liegen; genau dafür hat sie eine Spalte
für die Quelle. Im Bereich `plugin:throne` liegen der Schlüssel und die
Alert-Texte.

Beim Entfernen des Plugins gehen die Einstellungen mit. Die
**Ereignisse bleiben**: sie gehören dem Kanal und nicht diesem Plugin.

## Rechte

| Recht | darf |
| --- | --- |
| `Throne.Global.View` | die Einrichtung und die Ereignisse sehen |
| `Throne.Global.Edit` | den Schlüssel hinterlegen, Alerts einstellen |
