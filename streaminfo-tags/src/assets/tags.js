/*
 * Haelt den Beitrag dieser Erweiterung zum fertigen Titel aktuell.
 *
 * Das ist ein verborgenes Feld mit data-title-prefix. Die Vorschau
 * darunter gehoert Streaminfo - der fertige Titel ist seine Sache - und
 * liest alle solchen Felder in der Reihenfolge des Dokuments.
 *
 * Warum ueber ein Feld und nicht ueber eine Verabredung in JavaScript:
 * ein window.Streaminfo.irgendwas waere eine Abhaengigkeit von der
 * Ladereihenfolge und davon, dass es das andere Skript ueberhaupt gibt.
 * Ein Feld im Formular ist beides nicht.
 *
 * Eine Zugabe: ohne dieses Skript bleibt das Feld leer, die Vorschau
 * zeigt dann den Titel ohne Vorsatz. Gerechnet wird ohnehin auf dem
 * Server - was gilt, sind die Haken (siehe Tags::prefixList()).
 *
 * Gerechnet wird in der Reihenfolge der Kaestchen im Dokument, und die
 * ist die der Liste - genau wie auf dem Server. Zwei Rechenwege fuer
 * dasselbe Ergebnis liefen auseinander, und die Vorschau zeigte
 * irgendwann etwas anderes als das Gespeicherte.
 */
(function () {
    'use strict';

    function start() {
        var kasten = document.getElementById('si-tags');
        if (!kasten) {
            return;
        }

        var feld = kasten.querySelector('input[data-title-prefix]');
        if (!feld) {
            return;
        }

        function nachfuehren() {
            var vorsatz = '';

            Array.prototype.forEach.call(
                kasten.querySelectorAll('input[type="checkbox"]'),
                function (kaestchen) {
                    if (kaestchen.checked) {
                        // Dicht aneinander, kein Leerzeichen dazwischen -
                        // genau wie Tags::prefixList() es auf dem Server
                        // macht. Das Leerzeichen vor dem Titel setzt die
                        // Vorschau.
                        vorsatz += '[' + kaestchen.value + ']';
                    }
                }
            );

            if (feld.value === vorsatz) {
                return;
            }

            feld.value = vorsatz;

            // Damit die Vorschau es mitbekommt. Sie hoert auf das
            // Formular, und ein von Hand gesetzter Wert loest von sich
            // aus kein Ereignis aus.
            feld.dispatchEvent(new Event('input', { bubbles: true }));
        }

        kasten.addEventListener('change', nachfuehren);
        nachfuehren();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
