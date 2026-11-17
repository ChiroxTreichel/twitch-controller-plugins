/*
 * ===================================================================
 *  Blur Chats im Overlay: Milchglas ueber Stellen im Bild
 * ===================================================================
 *
 * Jede Nachricht traegt die ganze Liste der Bereiche fuer die
 * laufende Kategorie - nie eine Aenderung. Eine leere Liste heisst:
 * nichts abdecken (Plugin aus, offline, Kategorie ohne Bereiche).
 *
 * Beim Laden kommt der Stand aus state.js (window.BLUR_CHATS_STATE),
 * danach ueber die Leitung. Die Leitung spielt nichts nach - ohne
 * state.js stuende nach einem Neuladen der Quelle der Ingame-Chat
 * offen im Bild, bis jemand etwas aendert.
 *
 * Alle Zahlen sind Pixel der Buehne, von oben links. Der Platz deckt
 * die ganze Buehne (position "fill").
 */
(function () {
    'use strict';

    var SLOT = 'blur-chats';
    var BLENDE = 300;

    if (!window.Overlay) {
        console.error('[blur-chats] Das Overlay ist nicht geladen - ohne das geht nichts.');

        return;
    }

    var kasten = Overlay.slot(SLOT);

    if (!kasten) {
        // Auf dieser Seite gibt es den Platz nicht (?view=…).
        return;
    }

    /** Was gerade steht - zum Vergleichen, damit Gleiches nicht flackert. */
    var stand = null;

    function zahl(wert, vorgabe) {
        var n = parseInt(wert, 10);

        return isFinite(n) ? n : vorgabe;
    }

    function rgba(farbe, deckkraft) {
        var treffer = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(String(farbe || ''));
        var teile = treffer ? treffer.slice(1).map(function (h) { return parseInt(h, 16); }) : [30, 30, 36];
        var a = Math.max(0, Math.min(100, zahl(deckkraft, 90))) / 100;

        return 'rgba(' + teile.join(', ') + ', ' + a + ')';
    }

    /** Ein Bereich als Element - dieselben Klassen wie im Editor (region.css). */
    function bereich(b) {
        var rand = Math.max(0, zahl(b.feather, 24));
        var el = document.createElement('div');
        el.className = 'blur-chats-region';
        el.style.left = zahl(b.x, 0) + 'px';
        el.style.top = zahl(b.y, 0) + 'px';
        el.style.width = Math.max(1, zahl(b.w, 10)) + 'px';
        el.style.height = Math.max(1, zahl(b.h, 10)) + 'px';
        el.style.borderRadius = Math.max(0, zahl(b.radius, 12)) + 'px';
        el.style.setProperty('--bc-feather', rand + 'px');

        var flaeche = document.createElement('div');
        flaeche.className = 'blur-chats-fill';
        flaeche.style.backgroundColor = rgba(b.color, b.opacity);
        flaeche.style.filter = rand > 0 ? 'blur(' + rand + 'px)' : 'none';

        var korn = document.createElement('div');
        korn.className = 'blur-chats-grain';

        el.appendChild(flaeche);
        el.appendChild(korn);

        return el;
    }

    function render(daten) {
        var liste = daten && Array.isArray(daten.regions) ? daten.regions : [];
        var neu = JSON.stringify(liste);

        if (neu === stand) {
            return;
        }

        // Beim ersten Mal ohne Blende: nach einem Neuladen der Quelle
        // mitten im Stream soll der Chat sofort verdeckt sein, nicht
        // erst nach einer Drittelsekunde.
        var erstesMal = stand === null;
        stand = neu;

        // Die alten ausblenden und danach entfernen.
        Array.prototype.forEach.call(kasten.querySelectorAll('.blur-chats-region'), function (alt) {
            if (alt.dataset.gehend) {
                return;
            }
            alt.dataset.gehend = '1';
            alt.classList.remove('is-on');
            window.setTimeout(function () {
                if (alt.parentNode) {
                    alt.parentNode.removeChild(alt);
                }
            }, BLENDE + 50);
        });

        var neue = liste.map(bereich);
        neue.forEach(function (el) {
            if (erstesMal) {
                el.classList.add('is-on');
            }
            kasten.appendChild(el);
        });

        if (erstesMal) {
            return;
        }

        // Erst im naechsten Bild einschalten - sonst setzt der Browser
        // die Endopazitaet sofort und es gibt keinen Uebergang.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                neue.forEach(function (el) {
                    el.classList.add('is-on');
                });
            });
        });
    }

    Overlay.on(SLOT, render);

    // Der Stand beim Laden der Quelle - siehe state.js.
    render(window.BLUR_CHATS_STATE);
}());
