/*
 * ===================================================================
 *  Der laufende Titel im Overlay
 * ===================================================================
 *
 * Bekommt vom Worker, was gerade laeuft, und zeigt es an: Bild, Titel,
 * Interpret, wer es sich gewuenscht hat, und ein Fortschrittsbalken.
 *
 * Der Balken laeuft HIER weiter und nicht im Takt der Nachrichten.
 * Der Worker meldet sich alle fuenfzehn Sekunden und nur bei
 * Aenderung - ein Balken, der so nachgefuehrt wuerde, stuende
 * dreizehn Sekunden still und sprang dann. Er bekommt Dauer und
 * Stand und rechnet zwischen zwei Nachrichten selbst.
 *
 * Genau so machte es das alte obs.php, nur mit einem eigenen Aufruf
 * alle paar Sekunden statt einer Leitung.
 */
(function () {
    'use strict';

    var SLOT = 'music';

    if (!window.Overlay) {
        console.error('[music] Das Overlay ist nicht geladen - ohne das geht nichts.');

        return;
    }

    var zustand = {
        uri: '',
        name: '',
        artists: '',
        image: '',
        wishedBy: '',
        duration: 0,
        progress: 0,
        playing: false
    };

    /*
     * Der Anfangszustand kommt MIT der Seite - von /display/music/state.js,
     * das vor dieser Datei geladen wird.
     *
     * Er muss von dort kommen: die Leitung ins Overlay beginnt bei der
     * hoechsten bekannten Nachrichtennummer und spielt nichts nach, und
     * der Worker meldet sich nur bei Aenderung. Ohne ihn blieb der
     * Platz leer, bis der Titel wechselte - bei einem langen Lied
     * minutenlang.
     */
    if (window.MUSIC_STATE && typeof window.MUSIC_STATE === 'object') {
        var anfang = Object.keys(window.MUSIC_STATE);

        for (var a = 0; a < anfang.length; a++) {
            zustand[anfang[a]] = window.MUSIC_STATE[anfang[a]];
        }
    }

    /* Wann der Stand zuletzt gemeldet wurde - davon aus wird gezaehlt. */
    var seit = Date.now();

    var kasten = null;
    var teile = {};

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
        kasten.className = 'music-bar';

        /*
         * Hell oder dunkel - steht in den Einstellungen und kommt mit
         * /display/music/state.js. Es aendert sich nicht, waehrend
         * etwas laeuft; wer es umstellt, laedt die Browserquelle neu.
         */
        if (window.MUSIC_THEME === 'light') {
            kasten.classList.add('is-light');
        }

        var reihe = document.createElement('div');
        reihe.className = 'music-row';

        teile.bild = document.createElement('img');
        teile.bild.className = 'music-cover';
        teile.bild.alt = '';

        var text = document.createElement('div');
        text.className = 'music-text';

        teile.titel = document.createElement('span');
        teile.titel.className = 'music-title';

        teile.interpret = document.createElement('span');
        teile.interpret.className = 'music-artist';

        teile.wunsch = document.createElement('span');
        teile.wunsch.className = 'music-wish';

        text.appendChild(teile.titel);
        text.appendChild(teile.interpret);
        text.appendChild(teile.wunsch);

        /*
         * Das Logo rechts in der Leiste - wie im alten obs.php. Es
         * steht dort nicht zur Zierde: wer die Daten von Spotify
         * anzeigt, soll sagen, woher sie kommen. Welches Bild es ist,
         * steht im Stylesheet.
         */
        var marke = document.createElement('span');
        marke.className = 'music-brand';

        reihe.appendChild(teile.bild);
        reihe.appendChild(text);
        reihe.appendChild(marke);

        var balken = document.createElement('div');
        balken.className = 'music-bar-progress';

        teile.fuellung = document.createElement('span');
        teile.fuellung.className = 'music-bar-fill';
        balken.appendChild(teile.fuellung);

        kasten.appendChild(reihe);
        kasten.appendChild(balken);
        platz.appendChild(kasten);

        return true;
    }

    function zeichnen() {
        if (!bauen()) {
            return;
        }

        var etwas = zustand.uri !== '' && zustand.name !== '';

        kasten.classList.toggle('is-on', etwas);
        kasten.dataset.duration = String(zustand.duration || 0);

        if (!etwas) {
            return;
        }

        teile.titel.textContent = zustand.name;
        teile.interpret.textContent = zustand.artists;

        // "gewuenscht von" nur, wenn es jemand war. Ein Titel aus der
        // eigenen Wiedergabeliste hat keinen Wuenschenden.
        teile.wunsch.textContent = zustand.wishedBy
            ? 'gewünscht von ' + zustand.wishedBy
            : '';

        if (teile.bild.getAttribute('src') !== zustand.image) {
            if (zustand.image) {
                teile.bild.setAttribute('src', zustand.image);
                teile.bild.hidden = false;
            } else {
                teile.bild.removeAttribute('src');
                teile.bild.hidden = true;
            }
        }

        fortschritt();
    }

    function fortschritt() {
        if (!teile.fuellung || !zustand.duration) {
            return;
        }

        /*
         * Zwischen zwei Nachrichten selbst weiterzaehlen - aber nur,
         * wenn auch gespielt wird. Bei Pause bleibt der Balken stehen,
         * sonst liefe er weiter, waehrend nichts passiert.
         */
        var stand = zustand.progress;

        if (zustand.playing) {
            stand += Date.now() - seit;
        }

        var anteil = Math.max(0, Math.min(1, stand / zustand.duration));

        teile.fuellung.style.width = (anteil * 100).toFixed(2) + '%';
    }

    Overlay.on(SLOT, function (daten) {
        if (!daten || typeof daten !== 'object') {
            return;
        }

        var namen = Object.keys(daten);

        for (var i = 0; i < namen.length; i++) {
            zustand[namen[i]] = daten[namen[i]];
        }

        seit = Date.now();
        zeichnen();
    });

    // Einmal je Sekunde nachrechnen. Nur der Balken - alles andere
    // aendert sich erst mit der naechsten Nachricht.
    window.setInterval(fortschritt, 1000);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', zeichnen, { once: true });
    } else {
        zeichnen();
    }
}());
