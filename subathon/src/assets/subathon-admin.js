/*
 * ===================================================================
 *  Die Uhr auf der Uebersicht
 * ===================================================================
 *
 * Die grosse Zahl zaehlt herunter, solange die Seite offen ist. Der
 * Server liefert das Ende und seine eigene Uhrzeit; daraus laesst sich
 * die Restzeit jederzeit ausrechnen - ohne eine Anfrage je Sekunde.
 *
 * Ohne dieses Skript steht dort die Zahl, die beim Laden galt. Das ist
 * nicht falsch, nur langweilig: man sieht nicht, dass etwas laeuft.
 */
(function () {
    'use strict';

    var takt = null;

    /** Sekunden als 1:02:03 - wie auf der Seite und im Overlay. */
    function alsZeit(sekunden) {
        sekunden = Math.max(0, sekunden);

        var stunden = Math.floor(sekunden / 3600);
        var minuten = Math.floor((sekunden % 3600) / 60);
        var rest = sekunden % 60;

        return stunden + ':' + ('0' + minuten).slice(-2) + ':' + ('0' + rest).slice(-2);
    }

    function start() {
        if (takt !== null) {
            window.clearInterval(takt);
            takt = null;
        }

        var uhr = document.getElementById('subathon-clock');

        if (!uhr) {
            return;
        }

        var ende = parseInt(uhr.dataset.end, 10);
        var damals = parseInt(uhr.dataset.now, 10);

        if (!isFinite(ende) || !isFinite(damals)) {
            return;
        }

        /*
         * Pausiert heisst: die Zahl steht. Sie herunterzuzaehlen waere
         * eine Anzeige, die etwas anderes sagt als der Zustand
         * darunter.
         */
        if (uhr.dataset.paused === '1') {
            return;
        }

        /*
         * Der Unterschied zwischen der Uhr des Servers und der des
         * Browsers. Beide gehen selten gleich, und eine Anzeige, die
         * um Minuten danebenliegt, faellt auf.
         */
        var versatz = (Date.now() / 1000) - damals;

        function zeigen() {
            var rest = Math.floor(ende - (Date.now() / 1000 - versatz));

            if (rest <= 0) {
                window.clearInterval(takt);
                takt = null;

                uhr.textContent = alsZeit(0);

                /*
                 * Die Zeit ist um - jetzt steht auf der Seite noch
                 * "Läuft" und ein Knopf "Pausieren". Das entscheidet
                 * der Server, also holen wir uns die Seite von dort.
                 */
                window.location.reload();

                return;
            }

            uhr.textContent = alsZeit(rest);
        }

        zeigen();
        takt = window.setInterval(zeigen, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    /*
     * Noch einmal, wenn ein Formular ohne Seitenwechsel abgeschickt
     * wurde: die Uhr von eben ist dann ausgetauscht. Was am Dokument
     * haengt, braucht das nicht - das haelt.
     */
    document.addEventListener('overlay:swapped', start);
}());
