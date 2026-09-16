# Der Katalogserver

Liefert dieses Repository so aus, dass der Twitch-Controller es als
Plugin-Katalog lesen kann. Läuft unter `plugins.talutah.de`.

## Dynamisch, nicht erzeugt

`../index.php` **ist** der Katalog. Er liest bei jedem Aufruf die
Plugin-Ordner neben sich und baut daraus die Antwort — es gibt keine
erzeugte `index.json`, die jemand nach einem Paket vergessen könnte
nachzuziehen.

Veröffentlichen heißt deshalb:

```bash
php bin/pack.php <slug>     # im Arbeitsverzeichnis
git commit && git push
```

```bash
git pull                    # auf dem Katalogserver
```

Kein Neustart, kein Erzeugungsschritt. Auch die Prüfsumme wird bei
jedem Aufruf gerechnet — eine abgelegte Prüfsumme, die niemand
nachzieht, ist schlimmer als keine: nach einem erneuten Packen schlüge
jede Installation fehl.

## Was ausgeliefert wird

Eine Erlaubnisliste, keine Sperrliste. Im selben Verzeichnis liegt der
Quelltext jedes Plugins; nichts davon ist geheim — es steckt genauso in
den Paketen —, es gehört hier nur nicht hin.

| Adresse | |
| --- | --- |
| `/index.php` | der Katalog, mit `?search=` |
| `/<slug>/<slug>.zip` | die Pakete |
| `/<slug>/README.md` | die Beschreibungen |

Alles andere ist 404. Insbesondere läuft **genau eine** PHP-Datei — ein
`location ~ \.php$` machte jede Datei unter `<slug>/src/` ausführbar.

## Gesucht wird hier

Der Controller hängt `?search=` an und erwartet eine gefilterte Liste —
so geht nur über die Leitung, was gebraucht wird. Gesucht wird über
Slug, Name, Beschreibung und Schlagworte; mehrere Wörter müssen alle
vorkommen.

## Einrichten

```bash
git clone <dieses-repo> /opt/plugins
cd /opt/plugins/katalog
docker compose up -d
```

Das Proxy-Netz wird **nicht** angelegt (`external: true`) — es muss
dasselbe sein, in dem auch der Twitch-Controller hängt. Welches das
ist, steht in dessen `.env` unter `PROXY_NETWORK`:

```bash
grep PROXY_NETWORK /srv/.../.env
```

Stimmt es nicht, legt Compose nichts an, sondern bricht ab mit
*network … declared as external, but could not be found*. Das ist die
freundliche Variante: ein stillschweigend angelegtes eigenes Netz wäre
schlimmer — der Alias existierte, und der Proxy fände ihn trotzdem
nicht.

Im Nginx Proxy Manager dann:

| | |
| --- | --- |
| Domain Names | `plugins.talutah.de` |
| Scheme | `http` |
| Forward Hostname | `plugins` |
| Forward Port | `80` |

## Die Adressen im Katalog

`download` muss eine vollständige Adresse sein, sonst wirft der
Controller den Eintrag weg, und `readme` muss auf demselben Host
liegen, sonst holt er sie gar nicht erst. Beide baut `index.php` aus
der Anfrage — hinter dem Proxy zählt dabei `X-Forwarded-Proto`, denn
dort kommt sie als `http` an, während sie nach draußen `https` ist.

Reicht der Proxy die Kopfzeile nicht weiter, hilft `CATALOG_BASE_URL`
in der `.env` neben der `docker-compose.yaml`:

```
CATALOG_BASE_URL=https://plugins.talutah.de
```

## Was nicht im Katalog landet

Ein Ordner ohne `<slug>.zip`, ein Manifest, dessen `slug` nicht zum
Ordner passt, und eine Version, die keine ist. Alle drei würden einen
Eintrag ergeben, der vollständig aussieht und dessen Installation erst
beim Klick scheitert.
