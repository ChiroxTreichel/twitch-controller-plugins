/*
 * ===================================================================
 *  Umfrage im Overlay
 * ===================================================================
 *
 * Aufbau wie im alten admin/obs.php: "Live-Umfrage" und Restzeit oben,
 * die Frage gross, darunter die Zahl der Stimmen und je Eintrag ein
 * Balken - gemessen am Eintrag mit den meisten Stimmen.
 *
 * Das alte Overlay fragte alle zehn Sekunden nach. Hier kommt der
 * Stand ueber die Leitung, bei jeder Stimme ("poll"), und "hide" nimmt
 * die Umfrage heraus.
 *
 * Gebaut wird mit textContent, nie mit innerHTML: Eintraege koennen
 * Vorschlaege von Zuschauern sein.
 */
(function () {
    'use strict';

    var SLOT = 'polls';

    if (!window.Overlay) {
        console.error('[polls] Das Overlay ist nicht geladen - ohne das geht nichts.');

        return;
    }

    var kasten = Overlay.slot(SLOT);

    if (!kasten) {
        return;
    }

    var huelle = document.createElement('div');
    huelle.className = 'pl';
    kasten.appendChild(huelle);

    var stand = null;
    var versatz = 0;
    var uhr = null;

    function el(tag, klasse, text) {
        var knoten = document.createElement(tag);

        if (klasse) {
            knoten.className = klasse;
        }

        if (text !== undefined) {
            knoten.textContent = text;
        }

        return knoten;
    }

    function zweistellig(n) {
        return (n < 10 ? '0' : '') + n;
    }

    /* Wie damals: ganze Minuten, aufgerundet - "1:02:05" oder "02:05". */
    function rest(endeSek) {
        var ms = endeSek * 1000 - (Date.now() - versatz);

        if (ms <= 0) {
            return stand.expired;
        }

        var minuten = Math.max(1, Math.ceil(ms / 60000));
        var tage = Math.floor(minuten / 1440);
        var stunden = Math.floor((minuten % 1440) / 60);
        var mins = minuten % 60;

        return tage > 0
            ? tage + ':' + zweistellig(stunden) + ':' + zweistellig(mins)
            : zweistellig(stunden) + ':' + zweistellig(mins);
    }

    function zeichnen() {
        huelle.textContent = '';

        if (!stand || !stand.visible) {
            huelle.classList.remove('is-on');

            return;
        }

        var kopf = el('div', 'pl-kicker');
        kopf.appendChild(el('span', 'pl-label', stand.kicker));
        uhr = el('span', 'pl-timer', rest(stand.ends_ts));
        kopf.appendChild(uhr);

        huelle.appendChild(kopf);
        huelle.appendChild(el('div', 'pl-question', stand.title));
        huelle.appendChild(el('div', 'pl-summary', stand.summary));

        var zeilen = el('div', 'pl-rows');
        var meiste = (stand.rows || []).reduce(function (m, r) { return Math.max(m, r.votes || 0); }, 0);

        (stand.rows || []).forEach(function (zeile) {
            var reihe = el('div', 'pl-row');
            var oben = el('div', 'pl-row-head');
            oben.appendChild(el('span', '', zeile.label));
            oben.appendChild(el('span', 'pl-votes', String(zeile.votes || 0)));
            reihe.appendChild(oben);

            var balken = el('div', 'pl-bar');
            var fuellung = el('span');
            fuellung.style.width = (meiste > 0 ? (zeile.votes || 0) / meiste * 100 : 0) + '%';
            balken.appendChild(fuellung);
            reihe.appendChild(balken);

            zeilen.appendChild(reihe);
        });

        huelle.appendChild(zeilen);
        huelle.classList.add('is-on');
    }

    function uebernehmen(daten) {
        stand = daten;

        if (stand && stand.visible !== false && stand.now) {
            // Die Uhr des Servers zaehlt - die Restzeit soll im Stream
            // stimmen, auch wenn der Rechner mit OBS falsch geht.
            versatz = Date.now() - stand.now * 1000;
        }

        zeichnen();
    }

    Overlay.on(SLOT, function (daten) {
        if (!daten || daten.kind === 'hide') {
            uebernehmen({ visible: false });

            return;
        }

        if (daten.kind === 'poll') {
            daten.visible = true;
            uebernehmen(daten);
        }
    });

    window.setInterval(function () {
        if (stand && stand.visible && uhr) {
            uhr.textContent = rest(stand.ends_ts);
        }
    }, 1000);

    // Der Stand beim Laden der Quelle - siehe state.js.
    if (window.POLLS_STATE) {
        uebernehmen(window.POLLS_STATE);
    }
}());
