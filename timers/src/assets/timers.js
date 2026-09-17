/*
 * "Neue Nachricht" und "Loeschen" wirken im Browser, nicht ueber den
 * Server.
 *
 * Beide Knoepfe sind Absende-Knoepfe. Ohne dieses Skript schicken sie
 * das Formular ab, der Server haengt eine Zeile an oder nimmt eine
 * weg, und die Seite laedt neu - richtig, aber es SPEICHERT dabei. Wer
 * eine Nachricht halb getippt hat und "Neue Nachricht" drueckt, hat
 * sie damit gespeichert, der aufgeklappte Timer faellt zu, und man
 * sucht seine Stelle wieder.
 *
 * Im alten System war das Skript die einzige Art, eine Zeile
 * hinzuzufuegen. Hier ist es eine Zugabe: ist es nicht da, bleibt der
 * Weg ueber den Server. Darum bauen die Knoepfe auch nichts auf, was
 * ohne sie fehlen wuerde.
 *
 * Die neue Zeile kommt aus einer <template> im Formular und wird nicht
 * hier zusammengesetzt: sie bringt Klassen, Grenzen und den
 * Platzhalter schon mit. Ein hier gebautes <textarea> waere eine
 * zweite Wahrheit neben der Vorlage und liefe mit der Zeit auseinander.
 */
(function () {
    'use strict';

    /*
     * Die letzte Nachricht bleibt stehen: ein Timer ohne Nachricht
     * haette nichts zu posten. Der Knopf verschwindet dabei nicht, er
     * wird nur stumpf - ein Knopf, der weggeht und wiederkommt, laesst
     * die Zeile springen.
     */
    function knoepfePruefen(liste) {
        var zeilen = liste.querySelectorAll('[data-message-row]');

        Array.prototype.forEach.call(zeilen, function (zeile) {
            var knopf = zeile.querySelector('[data-remove-message]');

            if (knopf) {
                knopf.disabled = zeilen.length <= 1;
            }
        });
    }

    function listeZu(knopf) {
        var formular = knopf.form || knopf.closest('form');

        return formular ? formular.querySelector('[data-message-list]') : null;
    }

    function start() {
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-message-list]'),
            knoepfePruefen
        );

        document.addEventListener('click', function (ereignis) {
            var ziel = ereignis.target;

            if (!ziel || typeof ziel.closest !== 'function') {
                return;
            }

            var hinzu = ziel.closest('[data-add-message]');
            if (hinzu) {
                var liste = listeZu(hinzu);
                var vorlage = liste
                    ? liste.querySelector('template[data-message-template]')
                    : null;

                // Ohne Vorlage soll der Knopf tun, was er ohne dieses
                // Skript taete: zum Server gehen.
                if (!liste || !vorlage) {
                    return;
                }

                ereignis.preventDefault();

                var neu = vorlage.content.cloneNode(true);
                var feld = neu.querySelector('textarea');

                // Vor die Vorlage: die steht hinter der letzten Zeile,
                // also landet die neue Zeile am Ende.
                vorlage.parentNode.insertBefore(neu, vorlage);
                knoepfePruefen(liste);

                if (feld) {
                    feld.focus();
                }

                return;
            }

            var weg = ziel.closest('[data-remove-message]');
            if (!weg) {
                return;
            }

            var zeile = weg.closest('[data-message-row]');
            var wessen = zeile ? zeile.parentNode : null;

            if (!zeile || !wessen) {
                return;
            }

            ereignis.preventDefault();

            if (wessen.querySelectorAll('[data-message-row]').length <= 1) {
                return;
            }

            zeile.parentNode.removeChild(zeile);
            knoepfePruefen(wessen);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
