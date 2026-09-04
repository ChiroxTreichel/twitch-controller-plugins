/*
 * "Zeile hinzufuegen" haengt die Zeile im Browser an, statt dafuer zum
 * Server zu gehen.
 *
 * Warum ueberhaupt: der Knopf ist ein Absende-Knopf. Ohne dieses Skript
 * schickt er das Formular ab, der Server haengt eine leere Zeile an und
 * die Seite laedt neu - richtig, aber es SPEICHERT dabei. Wer eine
 * Zeile tippt und dann "hinzufuegen" drueckt, verliert Fokus und Stelle
 * im Formular, und ein halb fertiger Eintrag liegt schon in der
 * Datenbank.
 *
 * Mit dem Skript haengt der Knopf nur ein Feld an, und Speichern
 * speichert alles auf einmal - so, wie man es von einem Formular
 * erwartet.
 *
 * Ohne das Skript bleibt der Weg ueber den Server. Beides ist richtig,
 * eines ist bequemer - darum ist das hier eine Zugabe und keine
 * Voraussetzung.
 *
 * Ein gemeinsames Skript fuer beide Erweiterungen waere eine
 * Verabredung, die keines von ihnen kuendigen kann. Darum steht
 * dasselbe kleine Stueck in beiden - siehe
 * streaminfo-tags/assets/rows.js.
 */
(function () {
    'use strict';

    /* Welche Knoepfe mitmachen und welches Feld sie meinen, steht am
       Knopf - nicht hier. */
    var MARKE = 'data-add-row';

    function start() {
        document.addEventListener('click', function (ereignis) {
            var knopf = ereignis.target.closest('[' + MARKE + ']');
            if (!knopf) {
                return;
            }

            var formular = knopf.form || knopf.closest('form');
            var name = knopf.getAttribute(MARKE);
            if (!formular || !name) {
                return;
            }

            var vorlage = formular.querySelector('template[data-row-template]');
            if (!vorlage) {
                // Keine Vorlage zum Nachbilden - dann soll der Knopf
                // tun, was er ohne dieses Skript tut: zum Server gehen.
                return;
            }

            var felder = formular.querySelectorAll('input[name="' + name + '"]');
            var grenze = parseInt(knopf.dataset.max, 10) || 0;

            if (grenze > 0 && felder.length >= grenze) {
                return;
            }

            ereignis.preventDefault();

            /*
             * Aus einer <template> im Formular und nicht hier gebaut:
             * sie bringt Klassen, Grenzen und den Platzhalter mit, ohne
             * dass dieses Skript sie kennen muss. Ein hier
             * zusammengesetztes <input> waere eine zweite Wahrheit
             * neben der Vorlage - und liefe mit der Zeit auseinander.
             */
            var neu = vorlage.content.cloneNode(true);
            var feld = neu.querySelector('input[name="' + name + '"]');

            // Vor die Vorlage: die steht hinter der letzten Zeile, also
            // landet die neue Zeile am Ende der Liste.
            vorlage.parentNode.insertBefore(neu, vorlage);

            if (feld) {
                feld.focus();
            }

            // Der Hinweis "noch keine Zeile" stimmt jetzt nicht mehr.
            var leer = formular.querySelector('[data-empty-hint]');
            if (leer) {
                leer.hidden = true;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
