/*
 * ===================================================================
 *  Giveaway im Overlay: das Gluecksrad
 * ===================================================================
 *
 * Drei Nachrichten, eine nach der anderen (Overlay.queue):
 *
 *   show  Rad zeigen - wer draufsteht, wie gross, um welchen Preis es
 *         geht. Mit "delay" erst nach einer Pause: so bleibt der
 *         letzte Gewinner im Bild, bevor das Rad fuer den naechsten
 *         Preis kommt.
 *   spin  Rad drehen, bis der Zeiger auf dem Gewinner steht.
 *   hide  Rad weg.
 *
 * Wer gewinnt, steht in der Nachricht ("winner" ist der Platz in
 * "entries"). Das Rad lost nicht selbst - es zeigt nur, was der Server
 * entschieden hat. Sonst gingen zwei Browserquellen verschieden aus.
 *
 * Die Stuecke sind so gross wie die Tickets. Wo genau im Stueck des
 * Gewinners der Zeiger landet, ist Zufall - immer genau in der Mitte
 * saehe abgesprochen aus.
 */
(function () {
    'use strict';

    var SLOT = 'giveaway';

    if (!window.Overlay) {
        console.error('[giveaway] Das Overlay ist nicht geladen - ohne das geht nichts.');

        return;
    }

    var kasten = Overlay.slot(SLOT);

    if (!kasten) {
        // Auf dieser Seite gibt es den Platz nicht (?view=…).
        return;
    }

    /** So viele volle Umdrehungen vor dem Ziel - darunter wirkt es lustlos. */
    var RUNDEN = 6;

    var FARBEN = [
        '#9146ff', '#ff6b6b', '#ffb547', '#3ecf8e', '#4dabf7',
        '#f06595', '#20c997', '#845ef7', '#fcc419', '#5c7cfa'
    ];

    var VOLL = Math.PI * 2;

    // ---------------------------------------------------------------
    //  Aufbau
    // ---------------------------------------------------------------
    var huelle = document.createElement('div');
    huelle.className = 'gw';
    huelle.innerHTML =
        '<div class="gw-label"></div>' +
        '<div class="gw-stage">' +
        '  <canvas class="gw-wheel" width="1280" height="1280"></canvas>' +
        '  <div class="gw-pointer"></div>' +
        '  <div class="gw-hub"></div>' +
        '</div>' +
        '<div class="gw-banner"></div>';
    kasten.appendChild(huelle);

    var beschriftung = huelle.querySelector('.gw-label');
    var leinwand = huelle.querySelector('.gw-wheel');
    var fahne = huelle.querySelector('.gw-banner');
    var stift = leinwand.getContext('2d');

    var eintraege = [];
    var drehung = 0;

    // ---------------------------------------------------------------
    //  Zeichnen
    // ---------------------------------------------------------------
    function summe() {
        return eintraege.reduce(function (s, e) { return s + Math.max(0, e.tickets || 0); }, 0);
    }

    /*
     * Reihum aus der Palette. Ginge die Anzahl so auf, dass das letzte
     * Stueck dieselbe Farbe bekaeme wie das erste, laegen zwei gleiche
     * nebeneinander - dann eine andere.
     */
    function farbe(i, anzahl) {
        var f = FARBEN[i % FARBEN.length];

        if (anzahl > 1 && i === anzahl - 1 && f === FARBEN[0]) {
            f = FARBEN[(i + 3) % FARBEN.length];
        }

        return f;
    }

    /** Ein Name, der nicht ins Stueck passt, wird mit … gekuerzt. */
    function kuerzen(text, breite) {
        if (stift.measureText(text).width <= breite) {
            return text;
        }

        while (text.length > 1 && stift.measureText(text + '…').width > breite) {
            text = text.slice(0, -1);
        }

        return text + '…';
    }

    function zeichnen() {
        var groesse = leinwand.width;
        var mitte = groesse / 2;
        var radius = mitte - 12;
        var gesamt = summe();

        stift.clearRect(0, 0, groesse, groesse);

        if (gesamt <= 0) {
            stift.beginPath();
            stift.arc(mitte, mitte, radius, 0, VOLL);
            stift.fillStyle = 'rgba(30, 33, 41, 0.9)';
            stift.fill();
        } else {
            var winkel = drehung;

            eintraege.forEach(function (eintrag, i) {
                var anteil = Math.max(0, eintrag.tickets || 0) / gesamt * VOLL;

                stift.beginPath();
                stift.moveTo(mitte, mitte);
                stift.arc(mitte, mitte, radius, winkel, winkel + anteil);
                stift.closePath();
                stift.fillStyle = farbe(i, eintraege.length);
                stift.fill();

                if (eintraege.length > 1) {
                    stift.strokeStyle = 'rgba(0, 0, 0, 0.25)';
                    stift.lineWidth = 3;
                    stift.stroke();
                }

                // Die Schrift so hoch, wie das Stueck aussen breit ist -
                // und gar keine, wo sie nicht mehr lesbar waere.
                var schrift = Math.min(54, anteil * radius * 0.62);

                if (schrift >= 18) {
                    stift.save();
                    stift.translate(mitte, mitte);
                    stift.rotate(winkel + anteil / 2);
                    stift.font = '700 ' + Math.round(schrift) + 'px system-ui, -apple-system, "Segoe UI", sans-serif';
                    stift.textAlign = 'right';
                    stift.textBaseline = 'middle';
                    stift.fillStyle = '#ffffff';
                    stift.shadowColor = 'rgba(0, 0, 0, 0.55)';
                    stift.shadowBlur = 6;
                    stift.fillText(kuerzen(String(eintrag.name || ''), radius * 0.72), radius - 36, 0);
                    stift.restore();
                }

                winkel += anteil;
            });
        }

        stift.beginPath();
        stift.arc(mitte, mitte, radius, 0, VOLL);
        stift.lineWidth = 14;
        stift.strokeStyle = '#ffffff';
        stift.stroke();
    }

    // ---------------------------------------------------------------
    //  Zeigen, drehen, verstecken
    // ---------------------------------------------------------------
    function banner(text) {
        fahne.textContent = text || '';
        huelle.classList.toggle('has-banner', !!text);
    }

    function zeigen(daten) {
        eintraege = Array.isArray(daten.entries) ? daten.entries : [];
        beschriftung.textContent = daten.label || '';
        banner(daten.banner || '');
        zeichnen();
        huelle.classList.add('is-on');
    }

    function verstecken() {
        huelle.classList.remove('is-on');
    }

    function drehen(daten, fertig) {
        zeigen({ entries: daten.entries, label: daten.label });

        var gesamt = summe();
        var platz = parseInt(daten.winner, 10);

        if (gesamt <= 0 || !isFinite(platz) || !eintraege[platz]) {
            banner(daten.banner || '');
            fertig();

            return;
        }

        // Wo das Stueck des Gewinners liegt, bei Drehung 0.
        var anfang = 0;
        for (var i = 0; i < platz; i++) {
            anfang += Math.max(0, eintraege[i].tickets || 0) / gesamt * VOLL;
        }
        var anteil = Math.max(0, eintraege[platz].tickets || 0) / gesamt * VOLL;

        // Irgendwo im Stueck, aber nicht auf der Kante - dort waere es
        // fuer das Auge Glueckssache, auf welcher Seite er steht.
        var punkt = anfang + anteil * (0.15 + Math.random() * 0.7);

        // Der Zeiger steht oben, bei -90 Grad. Dort soll der Punkt
        // landen - nach ein paar vollen Runden.
        var von = drehung;
        var rest = ((-Math.PI / 2 - punkt - von) % VOLL + VOLL) % VOLL;
        var nach = von + rest + RUNDEN * VOLL;
        var dauer = Math.max(1000, parseInt(daten.duration, 10) || 9000);
        var beginn = null;
        var vorbei = false;

        function ende() {
            if (vorbei) {
                return;
            }

            vorbei = true;
            drehung = nach % VOLL;
            zeichnen();
            banner(daten.banner || '');
            fertig();
        }

        function schritt(jetzt) {
            if (vorbei) {
                return;
            }

            if (beginn === null) {
                beginn = jetzt;
            }

            var t = Math.min(1, (jetzt - beginn) / dauer);
            // Schnell los, lange auslaufen - wie ein echtes Rad.
            var weg = 1 - Math.pow(1 - t, 4);

            drehung = von + (nach - von) * weg;
            zeichnen();

            if (t < 1) {
                window.requestAnimationFrame(schritt);
            } else {
                ende();
            }
        }

        window.requestAnimationFrame(schritt);

        /*
         * Netz unter dem Netz: ist die Quelle in OBS gerade nicht im
         * Bild, laeuft requestAnimationFrame womoeglich nicht. Ohne das
         * hier stuende die Warteschlange dann still, und das naechste
         * Rad kaeme nie.
         */
        window.setTimeout(ende, dauer + 500);
    }

    Overlay.queue(SLOT, function (daten, fertig) {
        daten = daten || {};

        if (daten.kind === 'spin') {
            drehen(daten, fertig);

            return;
        }

        if (daten.kind === 'show') {
            window.setTimeout(function () {
                zeigen(daten);
                fertig();
            }, Math.max(0, parseInt(daten.delay, 10) || 0));

            return;
        }

        if (daten.kind === 'hide') {
            verstecken();
        }

        fertig();
    });

    // Der Stand beim Laden der Quelle - siehe state.js.
    var stand = window.GIVEAWAY_STATE;

    if (stand && stand.visible) {
        zeigen(stand);
    }
}());
