/*
 * ===================================================================
 *  Subathon im Overlay
 * ===================================================================
 *
 * Dieselbe Anzeige wie im Programm (overlay.html + die
 * javascript-Ressource), nur ohne jQuery und ohne den kleinen
 * Webserver auf Port 55555.
 *
 * Der wichtigste Unterschied: dort fragte die Seite jede Sekunde
 * "/calc" und bekam done/todo/possible ausgerechnet zurueck. Hier
 * kommt der Zustand einmal - Start, Ende, Obergrenze, Pause - und der
 * Browser rechnet selbst weiter. Er kann das, und eine Anfrage je
 * Sekunde je Browserquelle ist eine Anfrage je Sekunde zu viel.
 *
 * Aenderungen (ein Abo, eine Spende, eine neue Farbe) kommen ueber
 * die Leitung des Overlays.
 */
(function () {
    'use strict';

    var SLOT = 'subathon';

    if (!window.Overlay) {
        console.error('[subathon] Das Overlay ist nicht geladen - ohne das geht nichts.');

        return;
    }

    /** Alle zehn Sekunden eine neue Nachricht - wie im Programm. */
    var NACHRICHT_MS = 10000;

    /*
     * Die Legende zeigt sich alle fuenf Minuten fuer fuenfzehn
     * Sekunden. Sie erklaert die drei Farben, und das liest man
     * einmal - im Bild stehen muss sie deshalb nicht.
     */
    var LEGENDE_AN_MS = 15000;
    var LEGENDE_AUS_MS = 285000;

    var zustand = {
        now: 0,
        start: 0,
        end: 0,
        max: 0,
        break: 0,
        paused: false,
        status: 'idle',
        colors: {},
        messages: [],
        labels: {}
    };

    if (window.SUBATHON_STATE && typeof window.SUBATHON_STATE === 'object') {
        Object.keys(window.SUBATHON_STATE).forEach(function (name) {
            zustand[name] = window.SUBATHON_STATE[name];
        });
    }

    /*
     * Der Unterschied zwischen der Uhr des Servers und der des
     * Rechners, auf dem OBS laeuft. Beide gehen selten gleich, und ein
     * Timer, der um vier Minuten danebenliegt, faellt im Stream auf.
     */
    var versatz = zustand.now > 0 ? (Date.now() / 1000) - zustand.now : 0;

    function jetzt() {
        return Math.floor(Date.now() / 1000 - versatz);
    }

    var kasten = null;
    var teile = {};
    var nachrichtIndex = 0;

    // -----------------------------------------------------------------
    //  Aufbau
    // -----------------------------------------------------------------
    function bauen() {
        var platz = Overlay.slot(SLOT);

        if (!platz) {
            return false;
        }

        if (kasten && platz.contains(kasten)) {
            return true;
        }

        platz.textContent = '';

        kasten = document.createElement('div');
        kasten.className = 'sub-wrap';

        var balken = document.createElement('div');
        balken.className = 'sub-bars';

        teile.abschnitt = {};
        teile.fahne = {};
        teile.pfeil = {};

        ['done', 'todo', 'possible'].forEach(function (name) {
            var teil = document.createElement('div');
            teil.className = 'sub-part ' + name;

            var text = document.createElement('span');
            teil.appendChild(text);

            balken.appendChild(teil);

            teile.abschnitt[name] = { kasten: teil, text: text };

            // Die Ausweich-Fahne mit ihrem Zeiger - fuer den Fall,
            // dass der Abschnitt zu schmal fuer seine Zahl ist.
            var fahne = document.createElement('div');
            fahne.className = 'sub-flag ' + name;

            var pfeil = document.createElement('div');
            pfeil.className = 'sub-arrow ' + name;

            teile.fahne[name] = fahne;
            teile.pfeil[name] = pfeil;
        });

        kasten.appendChild(balken);

        ['done', 'todo', 'possible'].forEach(function (name) {
            kasten.appendChild(teile.fahne[name]);
            kasten.appendChild(teile.pfeil[name]);
        });

        var legende = document.createElement('div');
        legende.className = 'sub-legend';

        ['done', 'todo', 'possible'].forEach(function (name) {
            var zeile = document.createElement('div');
            zeile.className = name;
            zeile.textContent = (zustand.labels || {})[name] || '';
            legende.appendChild(zeile);
        });

        teile.legende = legende;
        kasten.appendChild(legende);

        teile.nachricht = document.createElement('div');
        teile.nachricht.className = 'sub-message';
        kasten.appendChild(teile.nachricht);

        platz.appendChild(kasten);

        return true;
    }

    // -----------------------------------------------------------------
    //  Rechnen
    // -----------------------------------------------------------------
    /**
     * Die drei Zahlen - dieselbe Rechnung wie im Programm
     * (OverlayServer.ServeCalc), nur hier statt dort.
     *
     * null heisst "nichts anzuzeigen": kein Start, oder er liegt noch
     * vor uns. Dann blendet sich die Leiste aus.
     */
    function rechnen() {
        var n = jetzt();

        if (!zustand.start || n < zustand.start) {
            return null;
        }

        /*
         * Waehrend einer Pause steht die Zeit. Der Server schickt das
         * Ende ohne die laufende Pause - also bleibt hier stehen, was
         * beim Beginn der Pause galt.
         */
        var bezug = zustand.paused ? Math.min(n, zustand.end) : n;

        var done = bezug - zustand.start - (zustand.break || 0);
        var todo = zustand.end - bezug;

        if (!zustand.max || zustand.max <= 0) {
            return { done: done, todo: todo, possible: -1, total: -1 };
        }

        var maxEnde = zustand.start + zustand.max + (zustand.break || 0);

        return { done: done, todo: todo, possible: maxEnde - zustand.end, total: zustand.max };
    }

    /** Sekunden als 1:02:03 - 1:1 aus secondsToTime des Programms. */
    function alsZeit(sekunden) {
        sekunden = Math.max(0, sekunden);

        var stunden = Math.floor(sekunden / 3600);
        var minuten = Math.floor((sekunden - stunden * 3600) / 60);
        var rest = Math.floor(sekunden - stunden * 3600 - minuten * 60);

        var text = '';

        if (stunden !== 0) {
            text = (stunden < 10 ? '0' + stunden : String(stunden)) + ':';
        }

        var mm = ('0' + minuten).slice(-2);

        if (mm !== '00' || text !== '') {
            text += mm + ':';
        }

        return text + ('0' + rest).slice(-2);
    }

    // -----------------------------------------------------------------
    //  Zeichnen
    // -----------------------------------------------------------------
    /**
     * Einen Abschnitt setzen: Breite, Zahl - und wenn er zu schmal
     * ist, die Fahne darunter.
     *
     * Die Grenze von 80 Pixeln ist die des Programms.
     */
    function abschnitt(name, sekunden, gesamt) {
        var teil = teile.abschnitt[name];
        var fahne = teile.fahne[name];
        var pfeil = teile.pfeil[name];

        var breite = gesamt > 0 ? (sekunden * 100.0 / gesamt) : 0;
        teil.kasten.style.width = Math.max(0, breite) + '%';

        var text = alsZeit(sekunden);
        var gemessen = teil.kasten.offsetWidth;

        if (gemessen > 80) {
            teil.text.textContent = text;
            teil.text.style.display = '';
            fahne.classList.remove('is-on');
            pfeil.classList.remove('is-on');

            return;
        }

        teil.text.style.display = 'none';
        fahne.textContent = text;
        fahne.classList.add('is-on');
        pfeil.classList.add('is-on');

        var unten = teil.kasten.offsetTop + teil.kasten.offsetHeight;
        var mitte = teil.kasten.offsetLeft + gemessen / 2;

        fahne.style.left = Math.max(0, mitte - fahne.offsetWidth / 2) + 'px';
        fahne.style.top = (unten + 10) + 'px';

        pfeil.style.left = (mitte - pfeil.offsetWidth / 2) + 'px';
        pfeil.style.top = unten + 'px';
    }

    function zeichnen() {
        if (!bauen()) {
            return;
        }

        var werte = rechnen();

        if (werte === null) {
            kasten.classList.remove('is-on');

            return;
        }

        kasten.classList.add('is-on');

        var farben = zustand.colors || {};

        Object.keys(farben).forEach(function (name) {
            kasten.style.setProperty('--sub-' + name.replace('_', '-'), farben[name]);
        });

        if (werte.total > 0) {
            kasten.classList.remove('no-limit');

            abschnitt('done', werte.done, werte.total);
            abschnitt('possible', werte.possible, werte.total);
            abschnitt('todo', werte.todo, werte.total);

            return;
        }

        /*
         * Ohne Obergrenze gibt es nur eine Zahl: wie lange noch. Die
         * Balken ergeben ohne "wovon" nichts, und im Programm wurde
         * dort dasselbe ausgeblendet.
         */
        kasten.classList.add('no-limit');

        ['done', 'possible'].forEach(function (name) {
            teile.fahne[name].classList.remove('is-on');
            teile.pfeil[name].classList.remove('is-on');
        });

        teile.abschnitt.todo.text.style.display = '';
        teile.abschnitt.todo.text.textContent = alsZeit(werte.todo);
    }

    // -----------------------------------------------------------------
    //  Die Laufschrift
    // -----------------------------------------------------------------
    function naechsteNachricht() {
        if (!teile.nachricht) {
            return;
        }

        var liste = zustand.messages || [];

        if (liste.length === 0) {
            teile.nachricht.textContent = '';

            return;
        }

        if (nachrichtIndex >= liste.length) {
            nachrichtIndex = 0;
        }

        var text = liste[nachrichtIndex];
        nachrichtIndex = (nachrichtIndex + 1) % liste.length;

        // Aus- und wieder einblenden, wie im Programm mit fadeOut /
        // fadeIn - dort 500 Millisekunden, hier auch.
        teile.nachricht.style.opacity = '0';

        window.setTimeout(function () {
            teile.nachricht.textContent = text;
            teile.nachricht.style.opacity = '1';
        }, 500);
    }

    // -----------------------------------------------------------------
    //  Die Legende
    // -----------------------------------------------------------------
    /**
     * Zeigen, warten, verstecken, warten - und wieder von vorn.
     *
     * Der erste Durchlauf zeigt sie sofort: wer das Overlay gerade
     * eingerichtet hat, soll sehen, dass sie da ist, und nicht fuenf
     * Minuten warten, um das zu pruefen.
     */
    function legendeUmlauf() {
        if (!teile.legende) {
            return;
        }

        teile.legende.classList.add('is-on');

        window.setTimeout(function () {
            if (teile.legende) {
                teile.legende.classList.remove('is-on');
            }

            window.setTimeout(legendeUmlauf, LEGENDE_AUS_MS);
        }, LEGENDE_AN_MS);
    }

    // -----------------------------------------------------------------
    Overlay.on(SLOT, function (daten) {
        if (!daten || typeof daten !== 'object') {
            return;
        }

        Object.keys(daten).forEach(function (name) {
            zustand[name] = daten[name];
        });

        if (zustand.now > 0) {
            versatz = (Date.now() / 1000) - zustand.now;
        }

        // Die Legende kann sich geaendert haben (andere Sprache), die
        // Farben auch - beides steckt im Zeichnen.
        if (teile.legende) {
            ['done', 'todo', 'possible'].forEach(function (name, i) {
                teile.legende.children[i].textContent = (zustand.labels || {})[name] || '';
            });
        }

        zeichnen();
    });

    zeichnen();
    naechsteNachricht();
    legendeUmlauf();

    window.setInterval(zeichnen, 1000);
    window.setInterval(naechsteNachricht, NACHRICHT_MS);
}());
