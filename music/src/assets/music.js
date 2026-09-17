/*
 * ===================================================================
 *  Die oeffentliche Musikseite
 * ===================================================================
 *
 * Zwei Aufgaben, beide eine Zugabe: ohne dieses Skript funktioniert
 * die Seite weiter, nur ohne sich selbst zu erneuern.
 *
 *   1. Die Warteschlange nachladen. Im alten System holte sie ein
 *      Klick - hier kommt sie von selbst, denn sie aendert sich,
 *      waehrend die Seite offen ist.
 *   2. Die Wartezeit herunterzaehlen. Sonst steht "du darfst in 12
 *      Minuten wieder" auch dann noch da, wenn es laengst zwoelf
 *      Minuten her ist.
 */
(function () {
    'use strict';

    /*
     * Alle zehn Sekunden.
     *
     * Das alte System fragte bei jedem Klick, der Cronjob alle fuenf.
     * Zehn sind hier der Kompromiss: die Warteschlange ist kein
     * Fortschrittsbalken, und jeder Aufruf geht ueber unseren Server
     * zu Spotify.
     */
    var TAKT_MS = 10000;

    function text(el, wert) {
        el.textContent = wert === null || wert === undefined ? '' : String(wert);
    }

    /** Eine Zeile der Warteschlange. */
    function zeile(titel) {
        var reihe = document.createElement('div');
        reihe.className = 'track';

        var bild = document.createElement('img');
        bild.className = 'track-cover';
        bild.alt = '';
        bild.loading = 'lazy';

        if (titel.image) {
            bild.src = titel.image;
        }

        var text_ = document.createElement('div');
        text_.className = 'track-text grow';

        var name = document.createElement('span');
        name.className = 'track-title';
        text(name, titel.name);

        var wer = document.createElement('span');
        wer.className = 'track-sub';
        text(wer, titel.artists);

        text_.appendChild(name);
        text_.appendChild(wer);

        /*
         * Wer sich das gewuenscht hat - der Sinn der ganzen Seite.
         * Steht nur da, wenn es jemand war: ein Titel aus der eigenen
         * Wiedergabeliste hat keinen Wuenschenden, und "gewuenscht von
         * (leer)" waere schlechter als nichts.
         */
        if (titel.wishedBy) {
            var wunsch = document.createElement('span');
            wunsch.className = 'track-wish';
            text(wunsch, 'Gewünscht von ' + titel.wishedBy);
            text_.appendChild(wunsch);
        }

        reihe.appendChild(bild);
        reihe.appendChild(text_);

        /*
         * Der Link zu Spotify umschliesst die Zeile, wenn es einen
         * gibt. Als Element um die Zeile herum und nicht als Klick
         * per Skript: so funktioniert Mittelklick, Kontextmenue und
         * Tastatur, ohne dass hier etwas nachgebaut wird.
         */
        if (!titel.url) {
            return reihe;
        }

        var verweis = document.createElement('a');
        verweis.href = titel.url;
        verweis.target = '_blank';
        verweis.rel = 'noopener';
        verweis.style.textDecoration = 'none';
        verweis.style.display = 'block';
        verweis.appendChild(reihe);

        return verweis;
    }

    function warteschlange() {
        var kasten = document.getElementById('queue');

        if (!kasten || !window.fetch) {
            return;
        }

        var leer = kasten.querySelector('[data-empty]');
        var jetzt = kasten.querySelector('[data-current]');
        var liste = kasten.querySelector('[data-items]');

        function holen() {
            fetch(kasten.dataset.src, { credentials: 'same-origin' })
                .then(function (antwort) { return antwort.json(); })
                .then(function (daten) {
                    if (!daten || !daten.ok) {
                        return;
                    }

                    jetzt.textContent = '';
                    liste.textContent = '';

                    if (daten.current) {
                        var kopf = document.createElement('p');
                        kopf.className = 'queue-label';
                        text(kopf, 'Läuft gerade');

                        var kasten2 = document.createElement('div');
                        kasten2.className = 'track-current';
                        kasten2.appendChild(zeile(daten.current));

                        jetzt.appendChild(kopf);
                        jetzt.appendChild(kasten2);
                    }

                    (daten.items || []).forEach(function (titel) {
                        liste.appendChild(zeile(titel));
                    });

                    var nichts = !daten.current && (daten.items || []).length === 0;

                    if (leer) {
                        leer.hidden = !nichts;
                        text(leer, nichts ? 'Gerade ist nichts in der Warteschlange.' : '');
                    }
                })
                .catch(function () {
                    // Ein Aussetzer ist kein Grund, die Liste zu
                    // leeren: was dasteht, stimmte vor zehn Sekunden
                    // noch, und beim naechsten Versuch steht es wieder.
                });
        }

        holen();
        window.setInterval(holen, TAKT_MS);
    }

    /** Die Wartezeit herunterzaehlen. */
    function wartezeit() {
        var zeile_ = document.getElementById('deny');

        if (!zeile_ || !zeile_.dataset.until) {
            return;
        }

        var bis = parseInt(zeile_.dataset.until, 10);

        if (!isFinite(bis)) {
            return;
        }

        var vorlage = zeile_.textContent;

        function zeigen() {
            var rest = bis - Math.floor(Date.now() / 1000);

            if (rest <= 0) {
                /*
                 * Fertig - aber das Formular steht noch auf "stumpf".
                 * Es hier freizuschalten waere eine zweite Wahrheit
                 * neben dem Server; der entscheidet, ob ein Wunsch
                 * durchgeht. Also neu laden und die Antwort von dort
                 * holen.
                 */
                window.location.reload();

                return;
            }

            var min = Math.floor(rest / 60);
            var sek = rest % 60;

            zeile_.textContent = vorlage + ' (' + min + ':' + (sek < 10 ? '0' : '') + sek + ')';
        }

        zeigen();
        window.setInterval(zeigen, 1000);
    }

    function start() {
        warteschlange();
        wartezeit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
