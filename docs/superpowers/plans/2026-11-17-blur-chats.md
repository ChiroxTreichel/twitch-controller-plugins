# Blur Chats – Bauplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Der Kern kennt die aktuelle Twitch-Kategorie und meldet Wechsel. Ein
neues Plugin „Overlay - Blur Chats“ legt pro Kategorie Milchglas-Bereiche
(Pixel, Deckkraft, weicher Rand) ins Overlay.

**Architecture:** Kern: neue Klasse `Twitch\Category`. Sie hält die Kategorie
in den Kern-Einstellungen, wird aus `channel.update`/`stream.online` gefüttert
(nach `events->store()`, der Weg bleibt unverändert) und feuert
`core.category.changed`. Plugin: Tabelle mit Bereichen, Verwaltungsseite mit
Drag-Editor und Screenshot-Hintergrund, ein Overlay-Platz `fill`, der über
`Bus::send` live umschaltet.

**Tech Stack:** PHP 8 ohne Composer, Postgres, Vanilla-JS, Hooks/Bus/Settings
des Kerns. Es gibt keine Testsuite. Geprüft wird mit PHP-Skripten im
Container `twitch-controller-web-1` (mountet `/docker/overlay` nach
`/var/www/html`), mit `php -l` und am Ende im echten Overlay.

**Spec:** `docs/superpowers/specs/2026-11-17-blur-chats-design.md`

## Global Constraints

- Kern-Version `2.16.0` (`core/App.php::VERSION`). Das Plugin verlangt `"core": ">=2.16.0"`.
- Plugin: Slug `blur-chats`, Name `Overlay - Blur Chats`, Version `1.0.0`, Tags `overlay`, `stream`. Es hängt von keinem anderen Plugin ab.
- Rechte: `BlurChats.Global.View`, `BlurChats.Global.Edit`, `BlurChats.Global.Toggle`
- Bereichswerte in **Pixeln** der Bühne (`overlay_width` × `overlay_height`, Vorgabe 1920×1080)
- Vorgaben für neue Bereiche: Farbe `#1e1e24`, Deckkraft `90`, Rand (feather) `24`, Ecken `12`
- Listen im Formular als Einzelzeilen mit + und –, nie als Textfeld (Speicher-Notiz)
- Tabellen mit `blur_chats_` präfixen. Texte in `lang/de.json` und `lang/en.json`, Kommentare im Stil des Repos auf Deutsch.
- `/docker/overlay` ist die **Live-Installation**: Kern-PHP wirkt sofort. Nach Änderungen `docker compose restart worker` (in `/docker/overlay`).
- **Gepackt wird erst nach Freigabe durch den Benutzer** (Packen = sofort im Marktplatz).
- Committen/pushen nur auf Ansage des Benutzers.

## Review Focus

1. **Kategorie ohne Bereiche / leere Kategorie (offline, `category_id` = "")** → der Platz ist leer, kein JS-Fehler. *(Test in Task 5)*
2. **Bereich ragt über die Bühne hinaus oder hat 0 px** → wird beim Speichern auf die Bühne begrenzt, Mindestgröße 10×10. *(Test in Task 3)*
3. **Screenshot zu groß oder kein Bild** → wird abgelehnt mit verständlicher Meldung, nichts wird gespeichert. *(Test in Task 4)*
4. **Webhook kommt doppelt (gleiche Kategorie)** → `core.category.changed` feuert nicht ein zweites Mal. *(Test in Task 1)*
5. **Browserquelle lädt mitten im Stream neu** → `state.js` liefert sofort die Bereiche der aktuellen Kategorie. *(Prüfung in Task 5)*

---

### Task 1: Kern – `Twitch\Category`

**Files:**
- Create: `/docker/overlay/core/Twitch/Category.php`
- Modify: `/docker/overlay/core/Twitch/Twitch.php` (Methode `category()` wie `eventSub()`)

**Interfaces:**
- Produces:
  - `Category::current(): array{id: string, name: string, title: string, updated_at: int}`. Ist `updated_at === 0`, ruft die Methode einmal `refresh()` auf.
  - `Category::remember(string $id, string $name, string $title): bool`. Speichert und gibt `true` zurück, wenn sich die **ID** geändert hat. Dann dispatcht sie `core.category.changed` mit `($id, $name, $previousId)`.
  - `Category::refresh(): bool`. Fragt Helix `GET channels?broadcaster_id=` mit `TokenStore::BROADCASTER` ab (Vorbild `streaminfo/src/src/Channel.php::info()`) und ruft dann `remember()` auf. Fehler werden mit `$app->log()` geschluckt.
  - `Category::refreshIfDue(int $maxAge = 3600): void`
  - `$app->twitch->category(): Category`
  - Settings-Schlüssel im Kern-Scope: `twitch_category_id`, `twitch_category_name`, `twitch_title`, `twitch_category_at` (Unixzeit)

- [ ] **Step 1: Prüfskript schreiben** (Scratchpad, `category-check.php`): App booten wie `bin/worker.php`, einen Zähler mit `$app->hooks->on('core.category.changed', …)` einhängen, dann prüfen:
  - `remember('1','A','t')` → `true`, Zähler 1
  - gleicher Aufruf noch einmal → `false`, Zähler 1 *(Review Focus 4)*
  - `remember('2','B','t')` → `true`, Zähler 2, Hook bekam `previousId === '1'`
  - am Ende `refresh()` → `true|false` ohne Exception, danach `current()['id']` gleich der echten Kategorie (live: `24241`)
- [ ] **Step 2: Laufen lassen.** `docker exec -i twitch-controller-web-1 php < …/category-check.php`. Erwartet: Fehler „Class Category not found“.
- [ ] **Step 3: `Category` und `Twitch::category()` umsetzen**
- [ ] **Step 4: Laufen lassen.** Erwartet: alle Prüfungen OK, die echte Kategorie ist am Ende wieder gesetzt.
- [ ] **Step 5:** `php -l` auf beide Dateien, ohne Commit (Commit nur auf Ansage)

### Task 2: Kern – Verdrahtung, Abo, Version

**Files:**
- Modify: `/docker/overlay/core/Twitch/EventSub.php::desired()`: `channel.update`, Version `'2'`, Bedingung `$channel`
- Modify: `/docker/overlay/core/Twitch/WebhookController.php::store()`: **nach** `events->store(...)` bei `twitch.channel.update` `$app->twitch->category()->remember(category_id, category_name, title)` aus `$event`, bei `twitch.stream.online` `->refresh()`. Mit eigenem `try/catch`, ein Fehler darf keine zweite Zustellung auslösen.
- Modify: `/docker/overlay/bin/worker.php`: vor `cron.tick` `$app->twitch->category()->refreshIfDue()`. Wenn die Einstellung `eventsub_sync_pending` gesetzt ist und `$app->twitch->hasChannel()`: `eventSub()->sync()`, bei leerem `failed` den Merker löschen, sonst loggen.
- Modify: `/docker/overlay/core/install.php`: wenn `$fromVersion !== null && version_compare($fromVersion, '2.16.0', '<')`, Merker `eventsub_sync_pending = true` setzen. Dafür hat die Datei `$db` und nicht `$app`, also per SQL in `settings` schreiben, so wie die Datei andere Werte setzt.
- Modify: `/docker/overlay/core/App.php`: `VERSION = '2.16.0'`
- Modify: `/docker/overlay/docs/entwicklung.md`: Zeile `core.category.changed | dispatch | die Twitch-Kategorie hat gewechselt (id, name, vorherige id)` in die Hakentabelle, dazu ein kurzer Abschnitt unter „Twitch“ zu `$app->twitch->category()`

**Interfaces:**
- Consumes: Task 1

- [ ] **Step 1: Prüfung Abo.** Skript: `$app->twitch->eventSub()->desired()` enthält genau einen Eintrag `channel.update` (auch wenn Timer an ist). Erwartet vor der Änderung: keiner, wenn Timer aus ist.
- [ ] **Step 2: Umsetzen** (alle Dateien oben)
- [ ] **Step 3: Prüfen.** Das Skript aus Step 1 findet jetzt genau einen Eintrag. `php -l` auf alle geänderten Dateien. `docker compose restart worker`, dann `docker compose logs --tail=50 worker`: Erwartet sind keine Fehler und `twitch_category_at` ist frisch (psql: `select value from settings where key='twitch_category_at'`).
- [ ] **Step 4: Echter Wechsel.** Den Benutzer bitten, die Kategorie kurz umzustellen (oder über Streaminfo). Erwartet: `twitch_category_id` ändert sich innerhalb weniger Sekunden, und im Feed erscheint **kein** neuer Eintrag.

### Task 3: Plugin-Grundgerüst und Bereiche

**Files:**
- Create: `blur-chats/src/plugin.json`, `install.php`, `uninstall.php`, `plugin.php` (zunächst Rechte, Menü mit Schalter wie `giveaway/src/plugin.php`, Gruppe `stream`), `lang/de.json`, `lang/en.json`
- Create: `blur-chats/src/src/Regions.php` (Namespace `TwitchController\Plugin\BlurChats`)

Tabellen (`install.php`, idempotent):
```
blur_chats_regions (id BIGSERIAL PK, category_id TEXT NOT NULL, category_name TEXT NOT NULL,
  label TEXT NOT NULL DEFAULT '', x INT, y INT, w INT, h INT, color VARCHAR(7),
  opacity SMALLINT, feather SMALLINT, radius SMALLINT, sort INT NOT NULL DEFAULT 0)
  + Index auf category_id
blur_chats_screens (category_id TEXT PK, image BYTEA NOT NULL, updated_at TIMESTAMPTZ NOT NULL DEFAULT now())
```

**Interfaces:**
- Produces:
  - `Regions::SLOT = 'blur-chats'`, `Regions::enabled(App): bool`, `Regions::setEnabled(App, bool): void` (Scope `plugin:blur-chats`)
  - `Regions::normalize(array $roh, int $stageW, int $stageH): array`, **rein, ohne DB.** Begrenzt `x,y` auf die Bühne, `w,h` auf mindestens 10 und höchstens bis zum Bühnenrand, `opacity` auf 0–100, `feather` auf 0–200, `radius` auf 0–500, `color` auf `#rrggbb` (sonst Vorgabe), `label` getrimmt und höchstens 60 Zeichen.
  - `Regions::forCategory(App, string $categoryId): list<array{id,label,x,y,w,h,color,opacity,feather,radius}>`
  - `Regions::categories(App): list<array{id: string, name: string, count: int}>`
  - `Regions::save(App, string $categoryId, string $categoryName, list<array> $rows): void`. Ersetzt alle Bereiche der Kategorie in einer Transaktion, `sort` nach Reihenfolge.
  - `Regions::removeCategory(App, string $categoryId): void` (auch den Screenshot)
  - `Regions::stage(App): array{width:int,height:int}` aus den Kern-Settings, gleiche Grenzen wie in `Bus::layout()`

- [ ] **Step 1: Prüfskript** `regions-check.php`: Autoloader registrieren, `blur-chats/src/src/Regions.php` per `require` laden, dann prüfen:
  - `normalize(['x'=>1900,'y'=>-5,'w'=>500,'h'=>0], 1920, 1080)` → `x=1900, y=0, w=20, h=10` *(Review Focus 2)*
  - `normalize(['opacity'=>150,'color'=>'red'],…)` → `opacity=100`, `color='#1e1e24'`
  - `normalize([], …)` → Vorgaben `opacity 90, feather 24, radius 12`
- [ ] **Step 2: Laufen lassen.** Erwartet: Fehler, die Datei fehlt.
- [ ] **Step 3: Dateien oben umsetzen**
- [ ] **Step 4: Laufen lassen.** Erwartet: OK. `php -l` auf alle PHP-Dateien.

### Task 4: Verwaltungsseite mit Editor und Screenshot

**Files:**
- Modify: `blur-chats/src/plugin.php` (Routen)
- Create: `blur-chats/src/views/page.php`, `blur-chats/src/assets/blur-chats.css`, `blur-chats/src/assets/blur-chats.js`
- Create: `blur-chats/src/src/Screens.php`

**Routen** (alle `auth`, Rechte wie angegeben):
- `GET /stream/blur-chats` (View): Kategorieliste links (aktuelle markiert über `$app->twitch->category()`), Editor der gewählten Kategorie `?category=<id>`
- `POST /stream/blur-chats` (Edit, CSRF), Aktionen:
  - `add_category` (id und name)
  - `add_current`
  - `save` (Zeilen `regions[n][label|x|y|w|h|color|opacity|feather|radius]`)
  - `remove_category`
  - `screen_remove`
- `GET /stream/blur-chats/categories?q=` (Edit): JSON aus Helix `search/categories` (`first=10`), `[{id,name,box_art_url}]`
- `POST /stream/blur-chats/screen` (Edit, CSRF): Rumpf `category`, `image` als Data-URL
- `GET /stream/blur-chats/screen/{id}` (View): `image/jpeg`, `Cache-Control: no-store`
- `POST /stream/blur-chats/toggle` (Toggle): wie beim Giveaway

**Interfaces:**
- Consumes: `Regions::*` (Task 3), `Category::current()` (Task 1)
- Produces:
  - `Screens::store(App, string $categoryId, string $dataUrl): string`. Gibt die Fehlermeldung zurück oder `''`. Nimmt nur `data:image/jpeg;base64,` mit JPEG-Magie `FF D8 FF` an, höchstens **3 MB** nach dem Dekodieren.
  - `Screens::get(App, string $categoryId): ?string`
  - `Screens::has(App, string $categoryId): bool`
  - Nach `save`/`remove_category`/Toggle wird `Overlay::push(App)` aufgerufen (Task 5). Bis dahin ist die Stelle ein Platzhalter.

**Editor (JS):**
- Fläche im Seitenverhältnis der Bühne, auf die Breite der Seite skaliert. Gerechnet wird intern in Bühnenpixeln.
- Bereiche mit der Maus ziehen, Anfasser unten rechts zum Größe ändern (Pointer-Events, keine Bibliothek).
- Die Vorschau zeigt Farbe, Deckkraft, Rand und Ecken genauso wie das Overlay (gleiche CSS-Regeln).
- Zeilen darunter: Name, X, Y, Breite, Höhe, Farbe, Deckkraft-Regler (mit %-Anzeige), Rand, Ecken, „–“. Darunter „+ Bereich“ (neuer Bereich mittig, 400×200, Vorgaben). Zeile und Fläche sind in beide Richtungen verbunden.
- Screenshot: Dateiwahl oder **Strg+V** auf der Seite. Im Browser über Canvas auf höchstens Bühnenbreite verkleinern, als JPEG mit Qualität 0.85 schicken, dann als Hintergrund der Fläche zeigen. Knopf „Screenshot entfernen“.
- Kategoriesuche mit Vorschlagsliste (Vorbild: Streaminfo-Kategoriefeld) und Knopf „Aktuelle Kategorie übernehmen“.

- [ ] **Step 1: Prüfskript** `screens-check.php` für `Screens::store` (die DB-Teile laufen erst nach der Installation, siehe Task 6. Vorher die Prüflogik als reine Funktion `Screens::validate(string $dataUrl): array{0: string, 1: string}` mit Fehler und Bytes testen):
  - `'data:image/png;base64,…'` → Fehler
  - JPEG-Header, aber 3 MB + 1 Byte → Fehler *(Review Focus 3)*
  - kleines echtes JPEG (z. B. mit `imagejpeg` erzeugt oder ein fest eingetragenes Mini-JPEG) → kein Fehler
- [ ] **Step 2: Laufen lassen.** Erwartet: Fehler, die Klasse fehlt.
- [ ] **Step 3: `Screens`, Routen, View, CSS, JS umsetzen**
- [ ] **Step 4: Prüfskript erneut, erwartet OK.** `php -l` auf alle Dateien. Mit `node --check` die JS-Syntax prüfen, falls `node` da ist.

### Task 5: Overlay

**Files:**
- Modify: `blur-chats/src/plugin.php` (`overlay.slots`, `overlay.assets`, `core.category.changed`, Route `state.js`)
- Create: `blur-chats/src/src/Overlay.php`, `blur-chats/src/assets/overlay.js`, `blur-chats/src/assets/overlay.css`

**Interfaces:**
- Produces:
  - `Overlay::state(App): array{regions: list<array>}`. Bei abgeschaltetem Plugin oder leerer Kategorie kommt `regions: []` zurück.
  - `Overlay::push(App): void` = `(new Bus($app))->send(Regions::SLOT, Overlay::state($app))`
- Platz: `['label' => translate('blur_chats.name'), 'position' => 'fill']`
- `GET /stream/blur-chats/state.js` (Recht `Account.Overlay.View`, `no-store`) → `window.BLUR_CHATS_STATE = {…};`. Reihenfolge der Assets: erst `state.js`, dann `overlay.js`.
- `core.category.changed` → `Overlay::push($app)`
- `overlay.js`:
  - `Overlay.on('blur-chats', render)`, beim Start `render(window.BLUR_CHATS_STATE)`
  - Pro Bereich ein `div.blur-chats-region`, absolut positioniert in px, mit `background-color` als rgba aus `color`/`opacity`, `border-radius` und `filter: blur(<feather>px)`
  - Darüber eine Körnung als SVG-`feTurbulence`-Data-URI mit niedriger Deckkraft
  - Wechsel: alte Bereiche in 300 ms aus-, neue einblenden
  - `regions: []` → leer
- `overlay.css`: Platz `pointer-events: none`, Übergänge

- [ ] **Step 1: Prüfskript** `overlay-check.php` (nach Installation, siehe Task 6): `Overlay::state()` bei abgeschaltetem Plugin → `regions === []`. Bei aktueller Kategorie ohne Bereiche → `[]` *(Review Focus 1)*. Mit gespeichertem Bereich → genau dieser Bereich.
- [ ] **Step 2: Umsetzen**
- [ ] **Step 3: `php -l`, `node --check`.** Dann `/overlay?view=blur-chats` im Browser bzw. in OBS öffnen: Bereiche stehen an den eingestellten Pixeln, und nach dem Neuladen sind sie sofort da *(Review Focus 5)*.

### Task 6: README, Installation, Abnahme

**Files:**
- Create: `blur-chats/README.md`. Inhalt: was das Plugin tut, ausdrücklich **kein echter Weichzeichner, sondern Milchglas**, Einrichtung (Browserquelle `?view=blur-chats` in Bühnengröße über das Spiel legen), keine neuen Twitch-Rechte, verlangt Kern ≥ 2.16.0.

- [ ] **Step 1: Den Benutzer um Freigabe zum Packen bitten** (Packen veröffentlicht sofort). Kern 2.16.0 muss vorher live sein.
- [ ] **Step 2:** `php bin/pack.php blur-chats`. Erwartet: Version 1.0.0, Größe und Prüfsumme werden ausgegeben.
- [ ] **Step 3:** Der Benutzer installiert über den Marktplatz. Danach die Prüfskripte aus Task 4 (DB-Teil) und Task 5 gegen die Installation laufen lassen. Erwartet: OK.
- [ ] **Step 4: Abnahme mit dem Benutzer:**
  - Bereich für die aktuelle Kategorie anlegen
  - Screenshot einfügen
  - Deckkraft ändern, im Overlay prüfen
  - Kategorie wechseln, die Bereiche springen um
