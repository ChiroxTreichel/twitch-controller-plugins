/*
 * Streaminfo: die Kategorie-Suche.
 *
 * Eine Zugabe. Ohne dieses Skript bleibt die Seite bedienbar - man
 * tippt den Titel in das Textfeld und speichert; die Kategorie bleibt
 * dann, wie sie ist. Nichts hier ist Voraussetzung fuers Speichern.
 *
 * Kein Framework, keine Abhaengigkeit: das Skript laeuft in der
 * Adminoberflaeche neben den Skripten anderer Plugins, und ein
 * geteilter Baukasten waere eine Verabredung, die keines von ihnen
 * kuendigen kann.
 */
(function () {
    'use strict';

    function start() {
        vorschau();
        kategorieSuche();
    }

    // ---------------------------------------------------------------
    //  Die Vorschau des fertigen Titels
    // ---------------------------------------------------------------
    /*
     * Zusammengesetzt aus dem, was Erweiterungen voranstellen, und dem
     * Titelfeld. Der Beitrag einer Erweiterung steht in einem
     * verborgenen Feld mit data-title-prefix - sie haelt es aktuell,
     * diese Funktion liest es.
     *
     * Warum ueber das Dokument und nicht ueber eine Verabredung in
     * JavaScript: ein window.Streaminfo.irgendwas waere eine Abhaengigkeit
     * von der Ladereihenfolge und davon, dass es dieses Skript ueberhaupt
     * gibt. Ein Feld im Formular ist beides nicht - wer nichts beitraegt,
     * setzt kein Feld, und fertig.
     *
     * Gezaehlt werden Zeichen, nicht Bytes: length auf der Zeichenkette
     * waere bei Umlauten und Emoji zu hoch, und der Zaehler soll dasselbe
     * sagen wie Twitch.
     */
    function vorschau() {
        var anzeige = document.getElementById('streaminfo-preview');
        if (!anzeige) {
            return;
        }

        var feld = document.getElementById(anzeige.dataset.title || '');
        if (!feld) {
            return;
        }

        var formular = feld.form || feld.closest('form');
        var grenze = parseInt(anzeige.dataset.max, 10) || 140;
        var warnung = anzeige.dataset.over || '';

        function zeichnen() {
            var vorsatz = '';

            if (formular) {
                // In der Reihenfolge des Dokuments: dieselbe, in der die
                // Bloecke der Erweiterungen stehen.
                Array.prototype.forEach.call(
                    formular.querySelectorAll('[data-title-prefix]'),
                    function (teil) {
                        vorsatz += teil.value || '';
                    }
                );
            }

            var ganz = (vorsatz + ' ' + feld.value).trim();
            var laenge = Array.from(ganz).length;

            if (ganz === '') {
                anzeige.textContent = '';
                anzeige.classList.remove('is-over');

                return;
            }

            anzeige.textContent = ganz + '  (' + laenge + '/' + grenze + ')';

            if (laenge > grenze) {
                anzeige.classList.add('is-over');
                anzeige.textContent += '  ' + warnung;
            } else {
                anzeige.classList.remove('is-over');
            }
        }

        feld.addEventListener('input', zeichnen);

        // Auf Aenderungen im ganzen Formular: eine Erweiterung, die ihr
        // verborgenes Feld nachfuehrt, loest dort ein input-Ereignis aus.
        if (formular) {
            formular.addEventListener('input', zeichnen);
            formular.addEventListener('change', zeichnen);
        }

        zeichnen();
    }

    // ---------------------------------------------------------------
    //  Kategorie: Vorschlaege von Twitch
    // ---------------------------------------------------------------
    function kategorieSuche() {
        var feld = document.getElementById('streaminfo-category');
        var liste = document.getElementById('streaminfo-suggest');
        var idFeld = document.getElementById('streaminfo-game-id');

        if (!feld || !liste || !idFeld) {
            return;
        }

        var adresse = feld.dataset.search || '';
        if (adresse === '') {
            return;
        }

        var wartend = null;
        var letzteFrage = '';
        var aktiv = -1;
        var laufNummer = 0;

        function zu() {
            liste.hidden = true;
            liste.textContent = '';
            aktiv = -1;
        }

        function zeigen(treffer) {
            liste.textContent = '';

            if (treffer.length === 0) {
                zu();
                return;
            }

            /*
             * Zusammengesetzt mit createElement und textContent, nicht
             * mit innerHTML: die Namen kommen von Twitch, sind also
             * fremder Text. Ein Spiel, das ein Anfuehrungszeichen im
             * Namen hat, wuerde eine zusammengeklebte Zeichenkette
             * aufbrechen - und ein Spielname laesst sich auf Twitch
             * nicht frei setzen, aber darauf will man sich nicht
             * verlassen.
             */
            treffer.forEach(function (eintrag, i) {
                var zeile = document.createElement('li');
                zeile.dataset.id = eintrag.id;
                zeile.dataset.name = eintrag.name;
                zeile.dataset.index = String(i);

                if (eintrag.box_art) {
                    var bild = document.createElement('img');
                    bild.src = eintrag.box_art;
                    bild.alt = '';
                    zeile.appendChild(bild);
                }

                var name = document.createElement('span');
                name.className = 'streaminfo-suggest-name';
                name.textContent = eintrag.name;
                zeile.appendChild(name);

                liste.appendChild(zeile);
            });

            liste.hidden = false;
            aktiv = -1;
        }

        function nehmen(zeile) {
            if (!zeile) {
                return;
            }

            idFeld.value = zeile.dataset.id;
            feld.value = zeile.dataset.name;
            letzteFrage = zeile.dataset.name;
            zu();
        }

        function fragen(frage) {
            /*
             * Jede Antwort traegt die Nummer ihrer Frage. Ohne das
             * konnte eine langsame Antwort auf "min" die schnelle auf
             * "minecraft" ueberschreiben - man tippt weiter, und die
             * Liste springt zurueck.
             */
            laufNummer += 1;
            var meine = laufNummer;

            fetch(adresse + '?q=' + encodeURIComponent(frage), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            })
                .then(function (antwort) {
                    return antwort.ok ? antwort.json() : { items: [] };
                })
                .then(function (daten) {
                    if (meine !== laufNummer) {
                        return;
                    }

                    zeigen(Array.isArray(daten.items) ? daten.items : []);
                })
                .catch(function () {
                    if (meine === laufNummer) {
                        zu();
                    }
                });
        }

        feld.addEventListener('input', function () {
            var frage = feld.value.trim();

            if (frage === letzteFrage) {
                return;
            }
            letzteFrage = frage;

            // Getippt heisst noch nicht gewaehlt: solange keine Zeile
            // angeklickt ist, gilt keine Kategorie. Sonst schickte das
            // Formular die ID des zuletzt Gewaehlten mit einem Namen,
            // den jemand seither ueberschrieben hat.
            idFeld.value = '';

            if (frage === '') {
                zu();
                return;
            }

            // Gewartet, weil jeder Tastendruck sonst einen Aufruf bei
            // Twitch kostet - und Twitch zaehlt mit.
            window.clearTimeout(wartend);
            wartend = window.setTimeout(function () {
                fragen(frage);
            }, 220);
        });

        // mousedown und nicht click: click kommt erst nach blur, und
        // blur schliesst die Liste - der Klick ginge ins Leere.
        liste.addEventListener('mousedown', function (ereignis) {
            var zeile = ereignis.target.closest('li');
            if (zeile) {
                ereignis.preventDefault();
                nehmen(zeile);
            }
        });

        feld.addEventListener('keydown', function (ereignis) {
            if (liste.hidden) {
                return;
            }

            var zeilen = liste.querySelectorAll('li');
            if (zeilen.length === 0) {
                return;
            }

            if (ereignis.key === 'ArrowDown') {
                ereignis.preventDefault();
                aktiv = Math.min(zeilen.length - 1, aktiv + 1);
            } else if (ereignis.key === 'ArrowUp') {
                ereignis.preventDefault();
                aktiv = Math.max(0, aktiv - 1);
            } else if (ereignis.key === 'Enter' && aktiv >= 0) {
                // Nur wenn eine Zeile ausgewaehlt ist. Sonst soll Enter
                // das Formular abschicken, wie ueberall sonst.
                ereignis.preventDefault();
                nehmen(zeilen[aktiv]);
                return;
            } else if (ereignis.key === 'Escape') {
                zu();
                return;
            } else {
                return;
            }

            Array.prototype.forEach.call(zeilen, function (zeile, i) {
                zeile.classList.toggle('is-active', i === aktiv);
            });
        });

        // Kurz gewartet: ein Klick auf eine Zeile loest zuerst blur
        // aus, und mousedown darf noch durchkommen.
        feld.addEventListener('blur', function () {
            window.setTimeout(zu, 150);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
