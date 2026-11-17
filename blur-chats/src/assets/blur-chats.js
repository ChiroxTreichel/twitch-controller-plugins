/*
 * ===================================================================
 *  Blur Chats - der Editor auf der Verwaltungsseite
 * ===================================================================
 *
 * Drei Dinge:
 *
 *   - die Flaeche: Bereiche mit der Maus verschieben und am Anfasser
 *     unten rechts in der Groesse aendern. Zeile und Flaeche sind in
 *     beide Richtungen verbunden - wer zieht, sieht die Zahlen laufen,
 *     wer tippt, sieht den Bereich wandern.
 *   - der Screenshot: Datei waehlen oder Strg+V. Der Browser
 *     verkleinert ihn auf die Buehnenbreite und schickt ein JPEG.
 *   - die Kategoriesuche mit Vorschlagsliste.
 *
 * Gerechnet wird in Pixeln der Buehne. Die Buehne steht in voller
 * Groesse in der Seite und wird nur verkleinert gezeigt (transform) -
 * darum sieht der weiche Rand in der Vorschau aus wie im Overlay.
 *
 * Ohne dieses Skript bleibt die Seite bedienbar: die Zeilen sind ein
 * gewoehnliches Formular.
 *
 * Kein Framework, keine Bibliothek - wie die anderen Plugins.
 */
(function () {
    'use strict';

    var MIN = 10;

    /** Was zum laufenden Editor gehoert - fuer Strg+V, das am Dokument haengt. */
    var aktuell = null;

    function zahl(wert, vorgabe) {
        var n = Math.round(parseFloat(wert));

        return isFinite(n) ? n : vorgabe;
    }

    function begrenzt(wert, min, max) {
        return Math.max(min, Math.min(max, wert));
    }

    function rgba(farbe, deckkraft) {
        var treffer = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(String(farbe || ''));
        var teile = treffer ? treffer.slice(1).map(function (h) { return parseInt(h, 16); }) : [30, 30, 36];

        return 'rgba(' + teile.join(', ') + ', ' + begrenzt(zahl(deckkraft, 90), 0, 100) / 100 + ')';
    }

    // ---------------------------------------------------------------
    //  Der Editor
    // ---------------------------------------------------------------
    function editor(wurzel) {
        var buehneW = zahl(wurzel.dataset.stageW, 1920);
        var buehneH = zahl(wurzel.dataset.stageH, 1080);
        var darf = wurzel.dataset.canEdit === '1';
        var hoechstens = zahl(wurzel.dataset.max, 30);

        var buehne = wurzel.querySelector('[data-bc-stage]');
        var rahmen = buehne ? buehne.parentNode : null;
        var zeilen = wurzel.querySelector('[data-bc-rows]');
        var vorlage = wurzel.querySelector('[data-bc-template]');
        var leer = wurzel.querySelector('[data-bc-empty]');
        var ungespeichert = wurzel.querySelector('[data-bc-dirty]');
        var neuKnopf = wurzel.querySelector('[data-bc-add]');

        if (!buehne || !rahmen || !zeilen) {
            return null;
        }

        if (!darf) {
            wurzel.classList.add('bc-readonly');
        }

        var massstab = 1;

        /** Paare aus Zeile und Kasten, in der Reihenfolge der Seite. */
        var paare = [];

        // ---------- Verkleinern ----------
        function skalieren() {
            var breite = rahmen.clientWidth;

            if (breite <= 0) {
                return;
            }

            massstab = breite / buehneW;
            buehne.style.transform = 'scale(' + massstab + ')';
            buehne.style.setProperty('--bc-inv', String(1 / massstab));
        }

        // ---------- Zeile lesen und schreiben ----------
        function feld(zeile, name) {
            return zeile.querySelector('[data-field="' + name + '"]');
        }

        /** Die Werte einer Zeile, auf die Buehne begrenzt - wie Regions::normalize(). */
        function lesen(zeile) {
            var x = begrenzt(zahl(feld(zeile, 'x').value, 0), 0, buehneW - MIN);
            var y = begrenzt(zahl(feld(zeile, 'y').value, 0), 0, buehneH - MIN);

            return {
                label: feld(zeile, 'label').value.trim(),
                x: x,
                y: y,
                w: begrenzt(zahl(feld(zeile, 'w').value, 400), MIN, buehneW - x),
                h: begrenzt(zahl(feld(zeile, 'h').value, 200), MIN, buehneH - y),
                color: feld(zeile, 'color').value,
                opacity: begrenzt(zahl(feld(zeile, 'opacity').value, 90), 0, 100),
                feather: begrenzt(zahl(feld(zeile, 'feather').value, 24), 0, 200),
                radius: begrenzt(zahl(feld(zeile, 'radius').value, 12), 0, 500)
            };
        }

        function schreiben(zeile, werte) {
            ['x', 'y', 'w', 'h'].forEach(function (name) {
                feld(zeile, name).value = String(werte[name]);
            });
        }

        function prozent(zeile) {
            var anzeige = zeile.querySelector('[data-bc-percent]');

            if (anzeige) {
                anzeige.textContent = feld(zeile, 'opacity').value + ' %';
            }
        }

        // ---------- Kasten ----------
        function kastenBauen() {
            var kasten = document.createElement('div');
            kasten.className = 'blur-chats-region bc-box';
            kasten.innerHTML =
                '<div class="blur-chats-fill"></div>' +
                '<div class="blur-chats-grain"></div>' +
                '<div class="bc-frame"></div>' +
                '<div class="bc-tag"></div>' +
                (darf ? '<div class="bc-handle"></div>' : '');
            buehne.appendChild(kasten);

            return kasten;
        }

        /** Dieselben Regeln wie overlay.js - die Vorschau soll nicht luegen. */
        function zeichnen(paar) {
            var b = lesen(paar.zeile);
            var k = paar.kasten;
            var flaeche = k.querySelector('.blur-chats-fill');

            k.style.left = b.x + 'px';
            k.style.top = b.y + 'px';
            k.style.width = b.w + 'px';
            k.style.height = b.h + 'px';
            k.style.borderRadius = b.radius + 'px';
            k.style.setProperty('--bc-feather', b.feather + 'px');
            flaeche.style.backgroundColor = rgba(b.color, b.opacity);
            flaeche.style.filter = b.feather > 0 ? 'blur(' + b.feather + 'px)' : 'none';

            var nummer = paare.indexOf(paar) + 1;
            k.querySelector('.bc-tag').textContent = b.label !== '' ? nummer + ' · ' + b.label : String(nummer);
        }

        function alleZeichnen() {
            paare.forEach(zeichnen);
        }

        // ---------- Auswahl ----------
        function waehlen(paar) {
            paare.forEach(function (p) {
                var an = p === paar;
                p.kasten.classList.toggle('is-active', an);
                p.zeile.classList.toggle('is-active', an);
            });

            // Der gewaehlte liegt oben, sonst greift man unter einem
            // anderen hindurch ins Leere.
            if (paar) {
                buehne.appendChild(paar.kasten);
            }
        }

        // ---------- Zustand ----------
        /*
         * Die Feldnamen nach der Reihenfolge auf der Seite. Der Server
         * sortiert nach diesen Nummern - so ist die Reihenfolge der
         * Zeilen auch die gespeicherte.
         */
        function nummerieren() {
            paare.forEach(function (paar, i) {
                Array.prototype.forEach.call(paar.zeile.querySelectorAll('[data-field]'), function (f) {
                    f.name = 'regions[' + i + '][' + f.dataset.field + ']';
                });
            });

            if (leer) {
                leer.hidden = paare.length > 0;
            }

            // Mehr nimmt der Server nicht an - also gar nicht erst
            // anbieten.
            if (neuKnopf) {
                neuKnopf.disabled = paare.length >= hoechstens;
            }
        }

        function geaendert() {
            if (ungespeichert) {
                ungespeichert.hidden = false;
            }
        }

        // ---------- Zeile einhaengen ----------
        function verbinden(zeile) {
            var paar = { zeile: zeile, kasten: kastenBauen() };
            paare.push(paar);

            zeile.addEventListener('input', function (ereignis) {
                if (ereignis.target.dataset.field === 'opacity') {
                    prozent(zeile);
                }
                zeichnen(paar);
                geaendert();
            });

            // Beim Verlassen eines Zahlenfelds auf die Buehne ziehen -
            // waehrend des Tippens nicht, sonst wird aus "1" vor "1200"
            // gleich etwas anderes.
            zeile.addEventListener('change', function () {
                schreiben(zeile, lesen(zeile));
                zeichnen(paar);
            });

            zeile.addEventListener('focusin', function () {
                waehlen(paar);
            });

            var weg = zeile.querySelector('[data-bc-remove]');
            if (weg) {
                weg.addEventListener('click', function () {
                    paare.splice(paare.indexOf(paar), 1);
                    paar.kasten.remove();
                    zeile.remove();
                    nummerieren();
                    alleZeichnen();
                    geaendert();
                });
            }

            if (darf) {
                ziehen(paar);
            }

            return paar;
        }

        // ---------- Ziehen und Groesse aendern ----------
        function ziehen(paar) {
            var kasten = paar.kasten;
            var griff = kasten.querySelector('.bc-handle');
            var start = null;

            kasten.addEventListener('pointerdown', function (ereignis) {
                if (ereignis.button !== 0) {
                    return;
                }

                ereignis.preventDefault();
                waehlen(paar);

                start = {
                    art: ereignis.target === griff ? 'groesse' : 'lage',
                    px: ereignis.clientX,
                    py: ereignis.clientY,
                    b: lesen(paar.zeile)
                };

                kasten.setPointerCapture(ereignis.pointerId);
            });

            kasten.addEventListener('pointermove', function (ereignis) {
                if (!start) {
                    return;
                }

                // Bildschirmpixel in Buehnenpixel.
                var dx = (ereignis.clientX - start.px) / massstab;
                var dy = (ereignis.clientY - start.py) / massstab;
                var b = start.b;
                var neu;

                if (start.art === 'lage') {
                    neu = {
                        x: begrenzt(Math.round(b.x + dx), 0, buehneW - b.w),
                        y: begrenzt(Math.round(b.y + dy), 0, buehneH - b.h),
                        w: b.w,
                        h: b.h
                    };
                } else {
                    neu = {
                        x: b.x,
                        y: b.y,
                        w: begrenzt(Math.round(b.w + dx), MIN, buehneW - b.x),
                        h: begrenzt(Math.round(b.h + dy), MIN, buehneH - b.y)
                    };
                }

                schreiben(paar.zeile, neu);
                zeichnen(paar);
                geaendert();
            });

            function los(ereignis) {
                if (!start) {
                    return;
                }
                start = null;

                if (kasten.hasPointerCapture(ereignis.pointerId)) {
                    kasten.releasePointerCapture(ereignis.pointerId);
                }
            }

            kasten.addEventListener('pointerup', los);
            kasten.addEventListener('pointercancel', los);
        }

        // ---------- Aufbau ----------
        Array.prototype.forEach.call(zeilen.querySelectorAll('[data-bc-row]'), verbinden);
        nummerieren();
        skalieren();
        alleZeichnen();

        if (neuKnopf && vorlage) {
            neuKnopf.addEventListener('click', function () {
                if (paare.length >= hoechstens) {
                    return;
                }

                var zeile = vorlage.content.querySelector('[data-bc-row]').cloneNode(true);
                zeilen.appendChild(zeile);
                var paar = verbinden(zeile);
                nummerieren();
                alleZeichnen();
                waehlen(paar);
                geaendert();
                feld(zeile, 'label').focus();
            });
        }

        // Klick ins Leere hebt die Auswahl auf.
        buehne.addEventListener('pointerdown', function (ereignis) {
            if (ereignis.target === buehne) {
                waehlen(null);
            }
        });

        var beobachter = null;
        if (window.ResizeObserver) {
            beobachter = new ResizeObserver(skalieren);
            beobachter.observe(rahmen);
        } else {
            window.addEventListener('resize', skalieren);
        }

        return {
            wurzel: wurzel,
            darf: darf,
            buehne: buehne,
            aufraeumen: function () {
                if (beobachter) {
                    beobachter.disconnect();
                } else {
                    window.removeEventListener('resize', skalieren);
                }
            }
        };
    }

    // ---------------------------------------------------------------
    //  Der Screenshot
    // ---------------------------------------------------------------
    /*
     * Auf hoechstens Buehnenbreite verkleinern und als JPEG schicken.
     * Ein PNG in voller Groesse waere mehrere Megabyte - fuer eine
     * Positionierhilfe reicht ein JPEG mit 0,85. Wird es trotzdem zu
     * gross, geht es mit weniger Qualitaet noch einmal.
     */
    var GRENZE = 3 * 1024 * 1024;

    function alsJpeg(datei, breiteMax) {
        return new Promise(function (fertig, fehler) {
            var adresse = URL.createObjectURL(datei);
            var bild = new Image();

            bild.onload = function () {
                URL.revokeObjectURL(adresse);

                var faktor = Math.min(1, breiteMax / bild.naturalWidth);
                var leinwand = document.createElement('canvas');
                leinwand.width = Math.max(1, Math.round(bild.naturalWidth * faktor));
                leinwand.height = Math.max(1, Math.round(bild.naturalHeight * faktor));
                leinwand.getContext('2d').drawImage(bild, 0, 0, leinwand.width, leinwand.height);

                var guete = [0.85, 0.7, 0.5];
                for (var i = 0; i < guete.length; i++) {
                    var daten = leinwand.toDataURL('image/jpeg', guete[i]);

                    // Base64: vier Zeichen sind drei Bytes.
                    if ((daten.length - daten.indexOf(',') - 1) * 3 / 4 <= GRENZE) {
                        fertig(daten);

                        return;
                    }
                }

                fehler(new Error('too big'));
            };

            bild.onerror = function () {
                URL.revokeObjectURL(adresse);
                fehler(new Error('no image'));
            };

            bild.src = adresse;
        });
    }

    function hochladen(stand, datei) {
        var w = stand.wurzel;
        var anzeige = w.querySelector('[data-bc-screen-status]');
        var entfernen = w.querySelector('[data-bc-screen-remove]');

        function melden(text) {
            if (anzeige) {
                anzeige.textContent = text;
            }
        }

        if (!datei || !/^image\//.test(datei.type)) {
            melden(w.dataset.textNoImage || '');

            return;
        }

        melden(w.dataset.textUploading || '');

        alsJpeg(datei, zahl(w.dataset.stageW, 1920))
            .then(function (daten) {
                var formular = new FormData();
                formular.append('csrf', w.dataset.csrf || '');
                formular.append('category', w.dataset.category || '');
                formular.append('category_name', w.dataset.categoryName || '');
                formular.append('image', daten);

                return fetch(w.dataset.uploadUrl, {
                    method: 'POST',
                    body: formular,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                }).then(function (antwort) {
                    return antwort.json().catch(function () {
                        return { ok: false };
                    });
                });
            })
            .then(function (antwort) {
                if (!antwort || !antwort.ok) {
                    melden((antwort && antwort.error) || w.dataset.textFailed || '');

                    return;
                }

                stand.buehne.style.backgroundImage = 'url("' + String(antwort.url).replace(/"/g, '%22') + '")';
                if (entfernen) {
                    entfernen.hidden = false;
                }
                melden(w.dataset.textUploaded || '');
            })
            .catch(function () {
                melden(w.dataset.textFailed || '');
            });
    }

    function screenshot(stand) {
        if (!stand.darf) {
            return;
        }

        var knopf = stand.wurzel.querySelector('[data-bc-file-trigger]');
        var wahl = stand.wurzel.querySelector('[data-bc-file]');

        if (knopf && wahl) {
            knopf.addEventListener('click', function () {
                wahl.click();
            });

            wahl.addEventListener('change', function () {
                hochladen(stand, wahl.files && wahl.files[0]);
                wahl.value = '';
            });
        }
    }

    /*
     * Strg+V auf der ganzen Seite. Am Dokument und darum nur EINMAL
     * eingehaengt, nicht in start(): das Dokument bleibt beim Austausch
     * der Seite stehen, und ein zweiter Zuhoerer luede jedes Bild
     * doppelt hoch. Der Editor von gerade steht in "aktuell".
     *
     * Nur ein Bild in der Zwischenablage zaehlt - Text, der in ein Feld
     * eingefuegt wird, geht seinen gewohnten Weg.
     */
    document.addEventListener('paste', function (ereignis) {
        if (!aktuell || !aktuell.darf || !document.body.contains(aktuell.wurzel)) {
            return;
        }

        var teile = (ereignis.clipboardData && ereignis.clipboardData.items) || [];

        for (var i = 0; i < teile.length; i++) {
            if (teile[i].kind === 'file' && /^image\//.test(teile[i].type)) {
                ereignis.preventDefault();
                hochladen(aktuell, teile[i].getAsFile());

                return;
            }
        }
    });

    // ---------------------------------------------------------------
    //  Die Kategoriesuche
    // ---------------------------------------------------------------
    function suche() {
        var feld = document.querySelector('[data-bc-search]');

        if (!feld) {
            return;
        }

        var formular = feld.form;
        var liste = formular.querySelector('[data-bc-suggest]');
        var idFeld = formular.querySelector('[data-bc-search-id]');
        var nameFeld = formular.querySelector('[data-bc-search-name]');
        var adresse = feld.dataset.url || '';

        if (!liste || !idFeld || !nameFeld || adresse === '') {
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

            // Mit createElement und textContent: die Namen kommen von
            // Twitch, also fremder Text.
            treffer.forEach(function (eintrag) {
                var zeile = document.createElement('li');
                zeile.dataset.id = eintrag.id;
                zeile.dataset.name = eintrag.name;

                if (eintrag.box_art_url) {
                    var bild = document.createElement('img');
                    bild.src = eintrag.box_art_url;
                    bild.alt = '';
                    zeile.appendChild(bild);
                }

                var name = document.createElement('span');
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
            nameFeld.value = zeile.dataset.name;
            feld.value = zeile.dataset.name;
            letzteFrage = zeile.dataset.name;
            zu();
        }

        function fragen(frage) {
            // Jede Antwort traegt die Nummer ihrer Frage - eine langsame
            // Antwort auf "min" soll die auf "minecraft" nicht
            // ueberschreiben.
            laufNummer += 1;
            var meine = laufNummer;

            fetch(adresse + '?q=' + encodeURIComponent(frage), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            })
                .then(function (antwort) {
                    return antwort.ok ? antwort.json() : [];
                })
                .then(function (daten) {
                    if (meine === laufNummer) {
                        zeigen(Array.isArray(daten) ? daten : []);
                    }
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

            // Getippt heisst noch nicht gewaehlt.
            idFeld.value = '';
            nameFeld.value = '';

            if (frage === '') {
                zu();

                return;
            }

            // Gewartet, weil jeder Tastendruck sonst einen Aufruf bei
            // Twitch kostet.
            window.clearTimeout(wartend);
            wartend = window.setTimeout(function () {
                fragen(frage);
            }, 220);
        });

        // mousedown und nicht click: click kommt erst nach blur, und
        // blur schliesst die Liste.
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

            var eintraege = liste.querySelectorAll('li');

            if (ereignis.key === 'ArrowDown') {
                ereignis.preventDefault();
                aktiv = Math.min(eintraege.length - 1, aktiv + 1);
            } else if (ereignis.key === 'ArrowUp') {
                ereignis.preventDefault();
                aktiv = Math.max(0, aktiv - 1);
            } else if (ereignis.key === 'Enter' && aktiv >= 0) {
                ereignis.preventDefault();
                nehmen(eintraege[aktiv]);

                return;
            } else if (ereignis.key === 'Escape') {
                zu();

                return;
            } else {
                return;
            }

            Array.prototype.forEach.call(eintraege, function (zeile, i) {
                zeile.classList.toggle('is-active', i === aktiv);
            });
        });

        feld.addEventListener('blur', function () {
            window.setTimeout(zu, 150);
        });
    }

    // ---------------------------------------------------------------
    //  Start
    // ---------------------------------------------------------------
    function start() {
        if (aktuell) {
            aktuell.aufraeumen();
            aktuell = null;
        }

        var wurzel = document.querySelector('[data-bc-editor]');

        if (wurzel) {
            aktuell = editor(wurzel);

            if (aktuell) {
                screenshot(aktuell);
            }
        }

        suche();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    // Nach einem Formular ohne Seitenwechsel sind die Elemente
    // ausgetauscht - und mit ihnen ihre Zuhoerer. Strg+V haengt am
    // Dokument und bleibt (siehe oben).
    document.addEventListener('overlay:swapped', start);
}());
