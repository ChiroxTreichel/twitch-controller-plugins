/*
 * Umfragen in der Verwaltung: Link kopieren, Zeitmodus umschalten,
 * Eintragszeilen anlegen und entfernen.
 *
 * Alles haengt am Dokument und greift ueber data-Attribute - darum
 * kein Neustart nach einem Austausch durch admin.js. Ein zweiter
 * Zuhoerer taete jeden Klick doppelt.
 *
 * Ohne Skript: der Link steht im Feld zum Markieren, beide Zeitfelder
 * sind sichtbar, und es bleibt bei den Zeilen, die dastehen.
 */
(function () {
    'use strict';

    // -----------------------------------------------------------------
    //  Eintragszeilen
    // -----------------------------------------------------------------

    /* Eine neue, leere Zeile hinter "nach" (oder am Ende). */
    function zeileAnlegen(liste, nach) {
        var vorlage = liste.querySelector('[data-polls-option-row]');

        if (!vorlage) {
            return null;
        }

        var neu = vorlage.cloneNode(true);
        neu.querySelector('[data-polls-option]').value = '';

        if (nach && nach.parentNode === liste) {
            liste.insertBefore(neu, nach.nextSibling);
        } else {
            liste.appendChild(neu);
        }

        neu.querySelector('[data-polls-option]').focus();

        return neu;
    }

    document.addEventListener('click', function (ereignis) {
        var hinzu = ereignis.target.closest('[data-polls-add]');

        if (hinzu) {
            var liste = hinzu.form && hinzu.form.querySelector('[data-polls-options]');

            if (liste) {
                zeileAnlegen(liste, null);
            }

            return;
        }

        var weg = ereignis.target.closest('[data-polls-remove]');

        if (weg) {
            var zeile = weg.closest('[data-polls-option-row]');
            var alle = zeile.parentNode.querySelectorAll('[data-polls-option-row]');

            // Die letzte Zeile bleibt stehen und wird nur geleert - sonst
            // gaebe es nichts mehr, was "+ Eintrag" kopieren koennte.
            if (alle.length > 1) {
                zeile.remove();
            } else {
                zeile.querySelector('[data-polls-option]').value = '';
            }
        }
    });

    /*
     * Enter in einer Eintragszeile legt die naechste an. Beim Abtippen
     * einer Liste druecken alle nach jedem Eintrag Enter - und sonst
     * ginge die halbe Umfrage ab.
     */
    document.addEventListener('keydown', function (ereignis) {
        var feld = ereignis.target;

        if (ereignis.key !== 'Enter' || ereignis.isComposing || !feld.matches || !feld.matches('[data-polls-option]')) {
            return;
        }

        ereignis.preventDefault();

        var zeile = feld.closest('[data-polls-option-row]');
        var naechste = zeile.nextElementSibling;

        // Steht darunter schon eine leere Zeile, dorthin - nicht noch eine.
        if (naechste && naechste.querySelector('[data-polls-option]').value === '') {
            naechste.querySelector('[data-polls-option]').focus();
        } else {
            zeileAnlegen(zeile.parentNode, zeile);
        }
    });

    // -----------------------------------------------------------------
    //  Link kopieren
    // -----------------------------------------------------------------
    document.addEventListener('click', function (ereignis) {
        var knopf = ereignis.target.closest('[data-copy]');

        if (!knopf || !navigator.clipboard) {
            return;
        }

        var vorher = knopf.textContent;

        navigator.clipboard.writeText(knopf.dataset.copy).then(function () {
            knopf.textContent = knopf.dataset.copied || vorher;
            window.setTimeout(function () { knopf.textContent = vorher; }, 1200);
        }).catch(function () {
            // Ohne Erlaubnis zum Kopieren steht der Link ja im Feld daneben.
        });
    });

    document.addEventListener('change', function (ereignis) {
        var wahl = ereignis.target;

        if (!wahl.matches || !wahl.matches('[data-polls-mode]')) {
            return;
        }

        var formular = wahl.form;

        Array.prototype.forEach.call(formular.querySelectorAll('[data-polls-show]'), function (teil) {
            teil.hidden = teil.dataset.pollsShow !== wahl.value;
        });
    });
}());
