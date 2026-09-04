/*
 * Das Suchfeld filtert die Kacheln, die schon auf der Seite stehen.
 *
 * Ueber den Server waere es ein Aufruf je Tastendruck fuer etwas, das
 * der Browser umsonst kann - die ganze Liste ist ohnehin da.
 *
 * Eine Zugabe: ohne dieses Skript ist das Feld ein Feld, das nichts
 * tut, und die Liste steht vollstaendig da. Nichts hier ist
 * Voraussetzung.
 *
 * Was verglichen wird, steht an den Kacheln (data-search) und nicht
 * hier: so muss dieses Skript nicht wissen, WAS durchsucht wird -
 * Anzeigename und Login - und der Vergleich ist eine Zeichenkette
 * gegen eine.
 */
(function () {
    'use strict';

    function start() {
        var feld = document.getElementById('raid-search');
        if (!feld) {
            return;
        }

        var gitter = document.getElementById(feld.dataset.grid || '');
        if (!gitter) {
            return;
        }

        var leer = document.getElementById('raid-empty');
        var kacheln = gitter.querySelectorAll('[data-search]');

        function filtern() {
            var frage = feld.value.trim().toLowerCase();
            var sichtbar = 0;

            Array.prototype.forEach.call(kacheln, function (kachel) {
                var passt = frage === '' || (kachel.dataset.search || '').indexOf(frage) !== -1;

                kachel.hidden = !passt;

                if (passt) {
                    sichtbar++;
                }
            });

            // "Nichts gefunden" nur, wenn wirklich gesucht wurde: bei
            // leerem Feld und leerer Liste steht schon ein anderer,
            // passenderer Satz da.
            if (leer) {
                leer.hidden = frage === '' || sichtbar > 0;
            }
        }

        feld.addEventListener('input', filtern);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
