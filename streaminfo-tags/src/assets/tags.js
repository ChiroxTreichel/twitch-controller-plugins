/*
 * Die Vorschau des fertigen Titels.
 *
 * Sie zeigt, was zu Twitch geht: die angehakten Tags in eckigen
 * Klammern, dann der Titel. Und sie warnt, wenn beides zusammen ueber
 * Twitchs 140 Zeichen kommt - ohne sie merkt man das erst am
 * gekuerzten Ergebnis.
 *
 * Eine Zugabe. Ohne dieses Skript sind die Haken gewoehnliche
 * Kaestchen: das Anhaken wirkt beim Speichern trotzdem, gerechnet wird
 * auf dem Server (siehe Tags::prefix()). Nur die Vorschau bleibt dann
 * leer.
 *
 * Gerechnet wird hier NICHT mit einer eigenen Regel, sondern mit
 * derselben Reihenfolge wie auf dem Server: die der Liste, also die der
 * Kaestchen im HTML. Zwei Rechenwege fuer dasselbe Ergebnis liefen
 * auseinander, und die Vorschau zeigte irgendwann etwas anderes als das
 * Gespeicherte.
 */
(function () {
    'use strict';

    function start() {
        var kasten = document.getElementById('si-tags');
        var vorschau = document.getElementById('si-tags-preview');

        if (!kasten || !vorschau) {
            return;
        }

        var feld = document.getElementById(vorschau.dataset.title || '');
        if (!feld) {
            return;
        }

        var grenze = parseInt(vorschau.dataset.max, 10) || 140;
        var warnung = vorschau.dataset.over || '';

        function zeichnen() {
            var kaesten = kasten.querySelectorAll('input[type="checkbox"]');
            var vorsatz = '';

            // In der Reihenfolge der Kaestchen, und die ist die der
            // Liste - genau wie auf dem Server.
            Array.prototype.forEach.call(kaesten, function (kaestchen) {
                if (kaestchen.checked) {
                    vorsatz += '[' + kaestchen.value + ']';
                }
            });

            // Dicht aneinander, ein Leerzeichen erst vor dem Titel -
            // genau wie Tags::prefixList() es auf dem Server macht.
            var ganz = (vorsatz + ' ' + feld.value).trim();

            // Zeichen zaehlen, nicht Bytes: [...ganz] zerlegt nach
            // Zeichen, length auf der Zeichenkette waere bei Umlauten
            // und Emoji zu hoch.
            var laenge = Array.from(ganz).length;

            vorschau.textContent = ganz === ''
                ? ''
                : ganz + '  (' + laenge + '/' + grenze + ')';

            if (laenge > grenze) {
                vorschau.classList.add('is-over');
                vorschau.textContent += '  ' + warnung;
            } else {
                vorschau.classList.remove('is-over');
            }
        }

        // Auf Aenderungen an den Kaestchen und am Titelfeld. Das
        // Titelfeld gehoert Streaminfo, und die Vorlagen-Auswahl loest
        // beim Setzen ein input-Ereignis aus - damit zieht die Vorschau
        // auch dann mit, wenn der Titel nicht getippt wurde.
        kasten.addEventListener('change', zeichnen);
        feld.addEventListener('input', zeichnen);

        zeichnen();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
