/*
 * "+" und "x" bei den Aus-Bedingungen.
 *
 * Beide wirken im Browser. Ohne dieses Skript bleibt am Ende jeder
 * Liste eine leere Zeile stehen - damit laesst sich auch dann eine
 * weitere Ausnahme eintragen, nur eben eine je Speichern. Das Skript
 * ist also eine Zugabe und keine Voraussetzung; es baut nichts auf,
 * was ohne es fehlen wuerde.
 *
 * Die neue Zeile kommt aus einer <template> in der Liste und wird
 * nicht hier zusammengesetzt: sie bringt Klassen, Namen und Grenzen
 * schon mit. Eine hier gebaute Zeile waere eine zweite Wahrheit neben
 * der Vorlage und liefe mit der Zeit auseinander.
 */
(function () {
    'use strict';

    /**
     * Die leeren Zeilen einer Liste - ohne die aus der Vorlage.
     *
     * template.content liegt in einem eigenen Dokumentbruchstueck,
     * querySelectorAll der Liste findet es also ohnehin nicht. Der
     * Vollstaendigkeit halber steht es hier trotzdem: wer die Vorlage
     * einmal woanders hinlegt, soll nicht ratlos suchen.
     */
    function zeilen(liste) {
        return liste.querySelectorAll('[data-off-row]');
    }

    function listeZu(knopf) {
        return knopf.closest('[data-off-list]');
    }

    function start() {
        document.addEventListener('click', function (ereignis) {
            var ziel = ereignis.target;

            if (!ziel || typeof ziel.closest !== 'function') {
                return;
            }

            // --- Eine Zeile mehr ---------------------------------------
            var hinzu = ziel.closest('[data-add-off]');

            if (hinzu) {
                var liste = listeZu(hinzu);
                var vorlage = liste
                    ? liste.querySelector('template[data-off-template]')
                    : null;

                // Ohne Vorlage soll der Knopf nichts tun - er ist
                // type="button", also passiert dann auch nichts
                // Ueberraschendes.
                if (!liste || !vorlage) {
                    return;
                }

                ereignis.preventDefault();

                var neu = vorlage.content.cloneNode(true);
                var feld = neu.querySelector('input');

                // Vor die Vorlage: die steht hinter der letzten Zeile,
                // also landet die neue Zeile am Ende.
                vorlage.parentNode.insertBefore(neu, vorlage);

                if (feld) {
                    feld.focus();
                }

                return;
            }

            // --- Eine Zeile weniger ------------------------------------
            var weg = ziel.closest('[data-remove-off]');

            if (!weg) {
                return;
            }

            var zeile = weg.closest('[data-off-row]');
            var wessen = zeile ? zeile.closest('[data-off-list]') : null;

            if (!zeile || !wessen) {
                return;
            }

            ereignis.preventDefault();

            /*
             * Eine Liste darf leer werden - anders als bei den
             * Nachrichten eines Timers ist "keine Ausnahme" ein
             * sinnvoller Zustand. Die letzte Zeile wird deshalb nicht
             * entfernt, sondern nur geleert: so bleibt ein Feld zum
             * Tippen stehen.
             */
            if (zeilen(wessen).length <= 1) {
                var einziges = zeile.querySelector('input');

                if (einziges) {
                    einziges.value = '';
                    einziges.focus();
                }

                return;
            }

            zeile.parentNode.removeChild(zeile);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
