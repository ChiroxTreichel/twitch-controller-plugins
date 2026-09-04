/*
 * ===================================================================
 *  Goals im Overlay
 * ===================================================================
 *
 * Zwei Aufgaben:
 *
 *   1. Das Geruest einsetzen. Es kommt aus den Einstellungen und
 *      steht in window.GOALS_HTML - geliefert von der Route
 *      /display/goals/markup.js, die vor dieser Datei geladen wird.
 *
 *   2. Werte daran binden. Der Vertrag ist derselbe wie im alten
 *      System, damit ein von dort kopiertes Geruest sofort passt:
 *
 *        data-bind="follower_current"   Wert wird als Text eingesetzt
 *        data-format="int"              wie er geschrieben wird
 *        data-fill="follower"           Breite in Prozent des Ziels
 *
 * Der Zustand wird ergaenzt, nicht ersetzt: eine Nachricht kann nur
 * "follower_current" enthalten, ohne die uebrigen Werte zu kennen.
 * Genau deshalb muss er hier liegen und nicht in der Nachricht.
 */
(function () {
    'use strict';

    var SLOT = 'goals';

    /*
     * Zahlenformate wie im alten System: Tausenderpunkte nach
     * deutscher Schreibweise, Euro mit zwei Stellen.
     *
     * "euro" bleibt drin, obwohl es erst das Spenden-Plugin braucht.
     * Ein von dort kopiertes Geruest mit data-format="euro" soll nicht
     * daran scheitern, dass der Rahmen es noch nicht kennt.
     */
    var nfInt = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 });
    var nfEuro = new Intl.NumberFormat('de-DE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    var formate = {
        raw: function (v) { return v === null || v === undefined ? '' : String(v); },
        int: function (v) { return nfInt.format(Number(v || 0)); },
        euro: function (v) { return nfEuro.format(Number(v || 0)) + '€'; }
    };

    var zustand = {};

    function anteil(aktuell, ziel) {
        var z = Number(ziel || 0);
        var a = Number(aktuell || 0);

        if (z <= 0) {
            return 0;
        }

        // Auf zwei Stellen, und nie ueber 100 - ein Ziel, das
        // uebererfuellt ist, soll den Balken nicht ueberlaufen lassen.
        return Math.round(Math.max(0, Math.min(1, a / z)) * 10000) / 100;
    }

    function kasten() {
        return Overlay.slot(SLOT);
    }

    function zeichnen() {
        var wurzel = kasten();
        if (!wurzel) {
            return;
        }

        // Jedes Mal neu suchen: das Geruest kommt aus den
        // Einstellungen und kann sich zwischen zwei Ladevorgaengen
        // vollstaendig geaendert haben. Eine gemerkte Liste waere
        // nach der ersten Aenderung falsch.
        var felder = wurzel.querySelectorAll('[data-bind]');
        for (var i = 0; i < felder.length; i++) {
            var el = felder[i];
            var name = el.getAttribute('data-bind');
            var format = formate[el.getAttribute('data-format') || 'raw'] || formate.raw;

            el.textContent = format(zustand[name]);
        }

        var balken = wurzel.querySelectorAll('[data-fill]');
        for (var j = 0; j < balken.length; j++) {
            var bar = balken[j];
            var ziel = bar.getAttribute('data-fill');

            // data-fill="follower" rechnet mit follower_current und
            // follower_goal. Das verallgemeinert, was im alten System
            // drei fest verdrahtete Faelle waren.
            bar.style.width = anteil(zustand[ziel + '_current'], zustand[ziel + '_goal']) + '%';
        }
    }

    function einsetzen() {
        var wurzel = kasten();
        if (!wurzel) {
            return;
        }

        var html = typeof window.GOALS_HTML === 'string' ? window.GOALS_HTML : '';
        if (html === '') {
            return;
        }

        wurzel.innerHTML = html;
        zeichnen();
    }

    /*
     * Der Kern schickt einen Ausschnitt und keinen vollstaendigen
     * Zustand - siehe Goals::send().
     */
    Overlay.on(SLOT, function (daten) {
        if (!daten || typeof daten !== 'object') {
            return;
        }

        var namen = Object.keys(daten);
        for (var i = 0; i < namen.length; i++) {
            zustand[namen[i]] = daten[namen[i]];
        }

        zeichnen();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', einsetzen);
    } else {
        einsetzen();
    }
})();
