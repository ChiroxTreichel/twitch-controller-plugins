/*
 * ===================================================================
 *  Der Moderatorenchat, live
 * ===================================================================
 *
 * Fragt alle zwei Sekunden nach, was seit der letzten bekannten Nummer
 * dazukam, und haengt es unten an. Schickt eine Nachricht ab, ohne die
 * Seite zu wechseln.
 *
 * Ohne dieses Skript bleibt die Seite benutzbar: das Formular ist ein
 * Formular, und neu laden zeigt den neuen Stand.
 *
 * Laeuft auf der Seite im Verwaltungsbereich und im Dock fuer OBS.
 * Woran es haengt, sagt [data-mod-chat]; was es dort nicht findet -
 * im Dock das Formular -, laesst es aus.
 */
(function () {
    'use strict';

    var TAKT_MS = 2000;

    /*
     * "Unten" heisst: hoechstens so weit davon weg. Wer ganz unten
     * liest, will die neue Nachricht sehen; wer hochgescrollt hat, um
     * etwas nachzulesen, will nicht weggerissen werden.
     */
    var NAH_PX = 60;

    /** Adressen in einer Nachricht. Nur http und https - kein javascript:. */
    var ADRESSE = /\bhttps?:\/\/[^\s<>"]+/g;

    var lauf = null;
    var ungelesen = 0;

    /*
     * Ohne die Zahl davor. Nach einem Austausch durch admin.js stuende
     * dort sonst "(3) (3) Chat".
     */
    function grundtitel() {
        return document.title.replace(/^\(\d+\) /, '');
    }

    function titelPflegen() {
        var titel = grundtitel();

        document.title = ungelesen > 0 ? '(' + ungelesen + ') ' + titel : titel;
    }

    function verlinken(element) {
        var text = element.textContent;

        ADRESSE.lastIndex = 0;
        if (!ADRESSE.test(text)) {
            return;
        }

        ADRESSE.lastIndex = 0;
        element.textContent = '';

        var stelle = 0;
        var treffer;

        while ((treffer = ADRESSE.exec(text)) !== null) {
            // Ein Satzzeichen am Ende gehoert zum Satz, nicht zur
            // Adresse: "schau mal https://clips.twitch.tv/abc."
            var adresse = treffer[0].replace(/[.,!?;:'")\]]+$/, '');

            if (treffer.index > stelle) {
                element.appendChild(document.createTextNode(text.slice(stelle, treffer.index)));
            }

            var link = document.createElement('a');
            link.href = adresse;
            link.textContent = adresse;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            element.appendChild(link);

            stelle = treffer.index + adresse.length;
        }

        if (stelle < text.length) {
            element.appendChild(document.createTextNode(text.slice(stelle)));
        }
    }

    /* Dasselbe Markup wie views/_messages.php. */
    function tagZeile(tag) {
        var zeile = document.createElement('div');
        var text = document.createElement('span');

        zeile.className = 'modchat-day';
        zeile.dataset.day = tag;
        text.textContent = tag;
        zeile.appendChild(text);

        return zeile;
    }

    function nachrichtZeile(nachricht) {
        var zeile = document.createElement('div');
        var zeit = document.createElement('time');
        var name = document.createElement('strong');
        var text = document.createElement('span');

        zeile.className = 'modchat-line' + (nachricht.own ? ' is-own' : '');
        zeile.dataset.id = String(nachricht.id);

        zeit.className = 'modchat-time';
        zeit.textContent = nachricht.time;

        name.className = 'modchat-name';
        name.style.color = nachricht.color;
        name.textContent = nachricht.name;

        text.className = 'modchat-text';
        text.textContent = nachricht.text;
        verlinken(text);

        // Leerzeichen dazwischen wie in der Vorlage, sonst kleben Zeit,
        // Name und Text beim Markieren und Kopieren aneinander.
        zeile.appendChild(zeit);
        zeile.appendChild(document.createTextNode(' '));
        zeile.appendChild(name);
        zeile.appendChild(document.createTextNode(' '));
        zeile.appendChild(text);

        return zeile;
    }

    function chat(wurzel) {
        var konfiguration;

        try {
            konfiguration = JSON.parse(wurzel.querySelector('[data-config]').textContent || '{}');
        } catch (e) {
            return null;
        }

        var texte = konfiguration.texts || {};
        var adresse = (konfiguration.urls || {}).messages;
        var letzte = parseInt(konfiguration.last, 10) || 0;

        var verlauf = wurzel.querySelector('[data-log]');
        var sprung = wurzel.querySelector('[data-jump]');
        var meldung = wurzel.querySelector('[data-error]');
        var formular = wurzel.querySelector('[data-form]');

        var tage = verlauf.querySelectorAll('[data-day]');
        var letzterTag = tage.length ? tage[tage.length - 1].dataset.day : '';

        var takt = null;
        var holtGerade = false;
        var nochEinmal = false;
        var vorbei = false;

        function untenNah() {
            return verlauf.scrollHeight - verlauf.scrollTop - verlauf.clientHeight < NAH_PX;
        }

        function nachUnten() {
            verlauf.scrollTop = verlauf.scrollHeight;
            sprung.hidden = true;
        }

        function fehler(text) {
            meldung.textContent = text || '';
            meldung.hidden = !text;
        }

        /*
         * Abgemeldet: aufhoeren und es sagen. Weiter nachzufragen
         * braechte nichts ausser einer Anfrage alle zwei Sekunden, die
         * jedes Mal dasselbe 401 bekommt.
         */
        function abgemeldet() {
            stop();
            fehler(texte.sessionLost);
        }

        function anhaengen(nachrichten) {
            var warUnten = untenNah();
            var eigene = false;
            var dazu = 0;

            nachrichten.forEach(function (nachricht) {
                // Doppelt kann nichts kommen - nachgefragt wird ab der
                // letzten Nummer -, aber zwei Abfragen koennen sich
                // ueberholen. Dann kaeme dieselbe Zeile zweimal.
                if (nachricht.id <= letzte) {
                    return;
                }

                letzte = nachricht.id;
                dazu++;

                if (nachricht.day !== letzterTag) {
                    letzterTag = nachricht.day;
                    verlauf.appendChild(tagZeile(nachricht.day));
                }

                verlauf.appendChild(nachrichtZeile(nachricht));

                if (nachricht.own) {
                    eigene = true;
                } else if (document.hidden) {
                    ungelesen++;
                }
            });

            if (dazu === 0) {
                return;
            }

            var leer = verlauf.querySelector('[data-empty]');
            if (leer) {
                leer.remove();
            }

            // Die eigene Nachricht will man sehen, egal wo man gerade
            // stand - man hat sie eben abgeschickt.
            if (warUnten || eigene) {
                nachUnten();
            } else {
                sprung.hidden = false;
            }

            titelPflegen();
        }

        /*
         * Hoechstens eine Abfrage zur Zeit. Kommt eine dazwischen - der
         * Takt oder ein Absenden -, laeuft danach genau eine weitere:
         * so geht nichts verloren, und eine langsame Leitung stapelt
         * keine Anfragen auf.
         */
        function holen() {
            if (vorbei) {
                return;
            }

            if (holtGerade) {
                nochEinmal = true;

                return;
            }

            holtGerade = true;

            fetch(adresse + '?after=' + encodeURIComponent(String(letzte)), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            })
                .then(function (antwort) {
                    if (antwort.status === 401) {
                        abgemeldet();

                        return null;
                    }

                    return antwort.ok ? antwort.json() : null;
                })
                .then(function (daten) {
                    if (daten && Array.isArray(daten.messages)) {
                        anhaengen(daten.messages);
                    }
                })
                .catch(function () {
                    // Ein Aussetzer ist kein Grund zur Meldung: der
                    // naechste Takt fragt ab derselben Nummer, und
                    // verloren ist nichts.
                })
                .then(function () {
                    holtGerade = false;

                    if (nochEinmal) {
                        nochEinmal = false;
                        holen();
                    }
                });
        }

        function stop() {
            vorbei = true;

            if (takt !== null) {
                window.clearInterval(takt);
                takt = null;
            }
        }

        // Was der Server gerendert hat, bekommt seine Links hier - eine
        // zweite Fassung der Erkennung in PHP liefe irgendwann anders.
        Array.prototype.forEach.call(verlauf.querySelectorAll('.modchat-text'), verlinken);
        nachUnten();

        verlauf.addEventListener('scroll', function () {
            if (untenNah()) {
                sprung.hidden = true;
            }
        });

        sprung.addEventListener('click', nachUnten);

        if (formular) {
            var feld = formular.elements.text;
            var knopf = formular.querySelector('button[type="submit"]');
            var sendetGerade = false;

            feld.addEventListener('keydown', function (ereignis) {
                // Waehrend einer Eingabehilfe (z. B. japanisch) ist Enter
                // die Bestaetigung des Wortes, nicht das Absenden.
                if (ereignis.key !== 'Enter' || ereignis.shiftKey || ereignis.isComposing) {
                    return;
                }

                ereignis.preventDefault();

                if (typeof formular.requestSubmit === 'function') {
                    formular.requestSubmit();
                } else {
                    formular.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });

            formular.addEventListener('submit', function (ereignis) {
                // admin.js sieht defaultPrevented und laesst das
                // Formular in Ruhe - sonst tauschte es die Seite aus.
                ereignis.preventDefault();

                if (sendetGerade || vorbei || feld.value.trim() === '') {
                    return;
                }

                sendetGerade = true;
                knopf.disabled = true;

                fetch(adresse, {
                    method: 'POST',
                    body: new FormData(formular),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                })
                    .then(function (antwort) {
                        return antwort.json()
                            .catch(function () { return {}; })
                            .then(function (daten) {
                                return { status: antwort.status, daten: daten || {} };
                            });
                    })
                    .then(function (ergebnis) {
                        if (ergebnis.status === 401) {
                            abgemeldet();

                            return;
                        }

                        if (!ergebnis.daten.ok) {
                            fehler(ergebnis.daten.error || texte.sendFailed);

                            return;
                        }

                        feld.value = '';
                        fehler('');
                        holen();
                    })
                    .catch(function () {
                        // Der Text bleibt im Feld. Nochmal schickt das
                        // Skript nicht von sich aus: ob der Server die
                        // Nachricht schon hat, ist von hier nicht zu
                        // sehen - der naechste Takt zeigt es.
                        fehler(texte.sendFailed);
                    })
                    .then(function () {
                        sendetGerade = false;
                        knopf.disabled = false;
                        feld.focus();
                    });
            });
        }

        takt = window.setInterval(holen, TAKT_MS);

        return { stop: stop };
    }

    function start() {
        if (lauf !== null) {
            lauf.stop();
            lauf = null;
        }

        var wurzel = document.querySelector('[data-mod-chat]');

        if (wurzel) {
            lauf = chat(wurzel);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    /*
     * Noch einmal, wenn admin.js die Seite ausgetauscht hat - etwa nach
     * einem Schnellschalter im Menue: Verlauf und Formular sind dann
     * neue Elemente. start() haelt vorher den alten Takt an, sonst
     * fragten zwei.
     */
    document.addEventListener('overlay:swapped', start);

    /*
     * Am Dokument und deshalb nur einmal, nicht in start(): ein zweiter
     * Zuhoerer nach jedem Austausch taete dasselbe doppelt.
     */
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && ungelesen > 0) {
            ungelesen = 0;
            titelPflegen();
        }
    });
}());
