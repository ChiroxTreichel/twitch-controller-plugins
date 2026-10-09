/*
 * ===================================================================
 *  Umfrage-Seite fuer Zuschauer
 * ===================================================================
 *
 * Zwei Dinge aus dem alten System:
 *
 *   Countdown   "Noch 01:02:03", bei null "Abgelaufen" und neu laden -
 *               dann steht das Ergebnis da.
 *   Zaehler     "1 von 3 gewählt". Wer ein Kaestchen zu viel ankreuzt,
 *               dem wird es wieder abgenommen, und die Leiste wackelt.
 *
 * Ohne Skript bleibt beides beim Stand des Ladens, und der Server
 * prueft die Zahl der Kreuze ohnehin selbst.
 */
(function () {
    'use strict';

    function fuellen(text, werte) {
        return String(text || '').replace(/%\{(\w+)\}/g, function (ganz, name) {
            return Object.prototype.hasOwnProperty.call(werte, name) ? werte[name] : ganz;
        });
    }

    // -----------------------------------------------------------------
    //  Countdown
    // -----------------------------------------------------------------
    function zweistellig(n) {
        return (n < 10 ? '0' : '') + n;
    }

    /* "02:03:04:05", fuehrende Nullgruppen weg - wie damals. */
    function dauer(ms) {
        var s = Math.max(0, Math.floor(ms / 1000));
        var teile = [Math.floor(s / 86400), Math.floor((s % 86400) / 3600), Math.floor((s % 3600) / 60), s % 60];

        while (teile.length > 2 && teile[0] === 0) {
            teile.shift();
        }

        return teile.map(zweistellig).join(':');
    }

    var uhr = document.querySelector('[data-pp-countdown]');

    if (uhr) {
        var ende = parseInt(uhr.dataset.end, 10) * 1000;
        // Die Uhr des Servers zaehlt, nicht die des Geraets: ein Telefon,
        // das fuenf Minuten nachgeht, soll nicht fuenf Minuten zu lange
        // "Noch" anzeigen.
        var versatz = Date.now() - parseInt(uhr.dataset.now, 10) * 1000;
        var neuGeladen = false;
        var takt = null;

        var zeigen = function () {
            var rest = ende - (Date.now() - versatz);

            if (rest <= 0) {
                uhr.textContent = uhr.dataset.expired || '';

                if (!neuGeladen && document.querySelector('[data-pp-vote], .pp-login')) {
                    neuGeladen = true;
                    window.clearInterval(takt);
                    window.setTimeout(function () { window.location.reload(); }, 400);
                }

                return;
            }

            uhr.textContent = fuellen(uhr.dataset.left, { time: dauer(rest) });
        };

        if (isFinite(ende)) {
            zeigen();
            takt = window.setInterval(zeigen, 1000);
        }
    }

    // -----------------------------------------------------------------
    //  Zaehler
    // -----------------------------------------------------------------
    var formular = document.querySelector('[data-pp-vote]');

    if (!formular) {
        return;
    }

    var noetig = parseInt(formular.dataset.required, 10) || 1;
    var leiste = formular.querySelector('[data-pp-status]');
    var gewaehlt = formular.querySelector('[data-pp-picked]');
    var hinweis = formular.querySelector('[data-pp-hint]');

    function felder() {
        return Array.prototype.slice.call(formular.querySelectorAll('input[name="choice[]"]'));
    }

    function aktualisieren() {
        var alle = felder();
        var an = alle.filter(function (f) { return f.checked; }).length;

        alle.forEach(function (f) {
            f.closest('.pp-option').classList.toggle('is-selected', f.checked);
        });

        gewaehlt.textContent = fuellen(formular.dataset.picked, { count: an, max: noetig });
        hinweis.textContent = an < noetig
            ? fuellen(formular.dataset.missing, { count: noetig - an })
            : formular.dataset.ok || '';
    }

    function wackeln() {
        leiste.classList.remove('is-shaking');
        // Neu anstossen: ohne das Lesen dazwischen spielt der Browser
        // die Animation beim zweiten Mal nicht ab.
        void leiste.offsetWidth;
        leiste.classList.add('is-shaking');
    }

    formular.addEventListener('change', function (ereignis) {
        var feld = ereignis.target;

        if (feld && feld.type === 'checkbox' && feld.checked) {
            var an = felder().filter(function (f) { return f.checked; }).length;

            if (an > noetig) {
                feld.checked = false;
                wackeln();
            }
        }

        aktualisieren();
    });

    formular.addEventListener('submit', function (ereignis) {
        var an = felder().filter(function (f) { return f.checked; }).length;

        if (an !== noetig) {
            ereignis.preventDefault();
            wackeln();
        }
    });

    aktualisieren();
}());
