/*
 * Die Auswahlliste schreibt ihren Wert in das Titelfeld.
 *
 * Eine Zugabe: ohne dieses Skript ist die Liste eine Liste, die nichts
 * tut, und man tippt den Titel wie sonst. Nichts hier ist Voraussetzung
 * fuers Speichern.
 *
 * Welches Feld gemeint ist, steht am Element (data-target) und nicht
 * hier: so kann Streaminfo seine Feld-Kennung aendern, ohne dass dieses
 * Plugin davon weiss.
 */
(function () {
    'use strict';

    function start() {
        var auswahl = document.getElementById('streaminfo-preset');
        if (!auswahl) {
            return;
        }

        var feld = document.getElementById(auswahl.dataset.target || '');
        if (!feld) {
            return;
        }

        /*
         * Steht eine echte Vorlage in der Auswahl, verschwindet das
         * Titelfeld - man hat den Titel dann schon gewaehlt, und ein
         * Feld daneben ist eine zweite Wahrheit.
         *
         * Verborgen und NICHT abgeschaltet: ein abgeschaltetes Feld
         * schickt der Browser nicht mit, und dann kaeme beim Speichern
         * ein leerer Titel an - also "nicht anfassen", und die Vorlage
         * waere ohne Wirkung.
         */
        var zeile = feld.closest('.row');

        function zeigen() {
            if (!zeile) {
                return;
            }

            zeile.hidden = auswahl.value !== '';
        }

        auswahl.addEventListener('change', function () {
            zeigen();

            if (auswahl.value === '') {
                /*
                 * "Eigener Text": das Feld behaelt, was darin steht, und
                 * bekommt den Fokus. Leeren waere hier der Verlust von
                 * Text, den jemand getippt hat - und das Ergebnis eines
                 * Klicks, den man auch aus Versehen macht.
                 */
                feld.focus();
                feld.select();
                return;
            }

            feld.value = auswahl.value;

            // Andere Plugins hoeren auf dieses Feld - die Tags zeigen
            // eine Vorschau des fertigen Titels. Ohne diese Meldung
            // bliebe die Vorschau stehen, waehrend der Titel schon ein
            // anderer ist.
            feld.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // Tippt jemand von Hand, passt die Auswahl nicht mehr - dann
        // soll sie auch nicht mehr behaupten, eine Vorlage zu zeigen.
        feld.addEventListener('input', function () {
            var i;

            for (i = 0; i < auswahl.options.length; i++) {
                if (auswahl.options[i].value === feld.value && feld.value !== '') {
                    auswahl.selectedIndex = i;
                    return;
                }
            }

            auswahl.selectedIndex = 0;
        });

        // Beim Laden: passt der Titel zu einer Vorlage, ist das Feld
        // von Anfang an verborgen.
        zeigen();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
