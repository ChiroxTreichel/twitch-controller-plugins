/**
 * Dateiauswahl in den Verwaltungsseiten.
 *
 * Ein Feld für eine Adresse mit einem Knopf daneben: der Knopf öffnet
 * die verborgene Dateiauswahl. Gewählt wird eine Datei, die auf dem
 * Server unter /uploads/ liegt - hochgeladen wird hier nichts, das
 * Feld bekommt nur den Namen eingesetzt.
 *
 * Ohne JavaScript bleibt das Textfeld bedienbar. Deshalb ist der Knopf
 * eine Zugabe und keine Voraussetzung.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (ereignis) {
        var knopf = ereignis.target.closest('[data-file-trigger]');
        if (!knopf) {
            return;
        }

        var auswahl = document.getElementById(knopf.dataset.fileTrigger);
        if (auswahl) {
            auswahl.click();
        }
    });

    document.addEventListener('change', function (ereignis) {
        var auswahl = ereignis.target;
        if (!auswahl.classList || !auswahl.classList.contains('file-field-native')) {
            return;
        }

        if (!auswahl.files || auswahl.files.length === 0) {
            return;
        }

        var feld = auswahl.closest('.file-field');
        var text = feld ? feld.querySelector('input[type="text"]') : null;
        if (!text) {
            return;
        }

        // Nur der Name. Den Pfad kennt der Browser nicht, und hochladen
        // tut diese Datei niemand - sie muss schon auf dem Server
        // liegen. Deshalb der Hinweis daneben.
        text.value = '/uploads/alerts/' + auswahl.files[0].name;
        text.dispatchEvent(new Event('input', { bubbles: true }));
    });
}());
