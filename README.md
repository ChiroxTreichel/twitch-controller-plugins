# Twitch-Controller — Plugins

Dieses Repository hält die Plugins, die im Twitch-Controller unter
*Konto → Plugins → Plugins finden* zum Download stehen. Es ist auf
`plugins.talutah.de` ausgecheckt und wird von dort ausgeliefert.

## Aufbau

Ein Ordner je Plugin, benannt nach seinem Slug:

```
<slug>/
    src/            der Quellcode - hier wird entwickelt
    plugin.json     Manifest, beim Packen aus src/ herausgeschrieben
    README.md       Langtext, erscheint als Beschreibungsseite im Marktplatz
    <slug>.zip      das Paket, aus src/ gebaut
bin/
    pack.php        baut <slug>.zip und plugin.json aus <slug>/src/
```

Der Ordnername **muss** dem `slug` in `plugin.json` entsprechen — der
Kern lehnt ein Plugin sonst ab, weil er unter diesem Namen entpackt.

### src/

Genau das, was auf einer Installation in `plugins/<slug>/` landet:

```
plugin.php       Einstiegspunkt: registriert Hooks und Routen
plugin.json      Manifest
install.php      Tabellen anlegen (idempotent, laeuft auch bei Updates)
uninstall.php    Tabellen abraeumen
views/           eigene Vorlagen                       (optional)
assets/          CSS, JS, Medien                       (optional)
lang/            Uebersetzungen je Sprachcode          (optional)
src/             Klassen unter TwitchController\Plugin\<Slug>\ (optional)
```

Der Inhalt von `src/` landet in der **Wurzel** des Archivs, nicht in
einem Unterordner: der Installer erwartet `plugin.json` und `plugin.php`
dort.

### README.md

Wird angezeigt, wenn man im Marktplatz ein Plugin anklickt statt es
direkt zu installieren. Markdown, aber nur eine enge Teilmenge wird
gerendert: Ueberschriften, Listen, Code, Links, fett und kursiv. HTML
darin wird als Text ausgegeben, nicht ausgefuehrt.

Sinnvoll darin: was das Plugin tut, was nach der Installation noch zu
tun ist, welche Twitch-Berechtigungen es anfordert und warum, welche
anderen Plugins es braucht.

## Ein Plugin packen

```bash
php bin/pack.php example      # ein Plugin
php bin/pack.php --all        # alle
```

Das Werkzeug baut `<slug>.zip` aus `<slug>/src/`, schreibt `plugin.json`
daneben und meldet Version, Groesse und Pruefsumme. Es bricht ab, wenn
der Slug nicht zum Ordner passt, `plugin.php` fehlt oder keine Version
gesetzt ist.

`plugin.json` wird bei **jedem** Packen aus `src/` herauskopiert. So
koennen Katalog und Paket nicht auseinanderlaufen — sonst bewirbt der
Katalog irgendwann eine Version, die im Archiv nicht drin ist.

Gebraucht wird dafuer die PHP-Erweiterung `zip`; fehlt sie, benutzt das
Werkzeug ein vorhandenes `zip` oder `7z`.

## Nach einer Aenderung

1. In `<slug>/src/` entwickeln
2. `version` in `src/plugin.json` hochziehen
3. `php bin/pack.php <slug>`
4. Commit und Push — der Katalogserver zieht den neuen Stand

Wer die Version nicht hochzieht, bekommt bei den Installationen kein
Update angeboten: der Kern vergleicht die Version aus dem Katalog mit
der installierten.
