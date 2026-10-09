/*
 * ===================================================================
 *  Giveaway - der Takt der Ziehung auf der Verwaltungsseite
 * ===================================================================
 *
 * Nach "Gewinner von … ziehen" dreht das Rad im Overlay. Die Seite
 * weiss, wann es steht (data-announce-in), und sagt dann den Gewinner
 * im Chat an. Danach laedt sie neu (data-reload-in) - erst dann steht
 * der Knopf fuer den naechsten Preis da.
 *
 * Ohne dieses Skript passiert dasselbe, nur langsamer: der Worker sagt
 * an, und wer neu laedt, sieht den naechsten Knopf.
 */
(function () {
    'use strict';

    var wecker = [];

    function aufraeumen() {
        wecker.forEach(window.clearTimeout);
        wecker = [];
    }

    function start() {
        aufraeumen();

        var feld = document.querySelector('[data-giveaway-timer]');

        if (!feld) {
            return;
        }

        var ansageIn = parseInt(feld.dataset.announceIn, 10);
        var neuIn = parseInt(feld.dataset.reloadIn, 10);

        if (isFinite(ansageIn) && feld.dataset.announceUrl) {
            wecker.push(window.setTimeout(function () {
                var daten = new FormData();
                daten.append('csrf', feld.dataset.csrf || '');

                fetch(feld.dataset.announceUrl, {
                    method: 'POST',
                    body: daten,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                }).catch(function () {
                    // Kein Grund zur Meldung: der Worker sagt dann an,
                    // ein paar Sekunden spaeter.
                });
            }, Math.max(0, ansageIn)));
        }

        if (isFinite(neuIn)) {
            wecker.push(window.setTimeout(function () {
                window.location.reload();
            }, Math.max(0, neuIn)));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    // Nach einem Formular ohne Seitenwechsel steht ein neuer Stand da -
    // mit neuen Zeiten. start() raeumt die alten Wecker vorher ab.
    document.addEventListener('overlay:swapped', start);
}());
