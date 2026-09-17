/*
 * Das Raid-Roulette.
 *
 * Es laeuft durch die Kacheln, wird langsamer und bleibt auf einer
 * stehen. Alles im Browser: die Kacheln sind schon da, und der Zufall
 * ist ein Aufruf von Math.random() - ein Server-Aufruf waere Arbeit
 * fuer etwas, das hier umsonst zu haben ist.
 *
 * Der Knopf steht mit hidden in der Seite und wird HIER eingeblendet.
 * Ohne dieses Skript tut er nichts, und ein Knopf, der nichts tut, ist
 * schlimmer als kein Knopf.
 *
 * Das Ziehen selbst ist nicht die Spannung - die entsteht durch die
 * Verzoegerung. Der Gewinner steht vom ersten Tick an fest; die
 * Animation laeuft nur so lange, bis sie bei ihm angekommen ist.
 * Anders herum - jeden Schritt neu wuerfeln - koennte sie auf dem
 * ersten Kanal enden, und das sieht nach Fehler aus.
 */
(function () {
    'use strict';

    /* Der erste Schritt, in Millisekunden. */
    var START = 70;

    /* Wie viel jeder Schritt langsamer wird, und wie langsam er
       hoechstens werden darf. Ohne Obergrenze wuerde die letzte Runde
       bei zwanzig Kacheln quaelend lang. */
    var BREMSE = 18;
    var LANGSAMSTENS = 420;

    function aufraeumen(kacheln) {
        kacheln.forEach(function (kachel) {
            kachel.classList.remove('is-spinning', 'is-winner');
        });
    }

    function gewinner(kacheln, stelle, knopf) {
        aufraeumen(kacheln);

        var kachel = kacheln[stelle];
        kachel.classList.add('is-winner');
        kachel.scrollIntoView({ behavior: 'smooth', block: 'center' });

        /*
         * Und jetzt raiden - falls das Plugin dafuer da ist.
         *
         * Gesucht wird ein Knopf mit data-raid-start in der
         * Gewinnerkachel. Dieses Skript kennt das Raid-Plugin nicht und
         * muss es nicht kennen: ist der Knopf da, wird er gedrueckt und
         * die Seite laedt neu; ist keiner da - weil das Plugin fehlt
         * oder weil das Recht fehlt - bleibt es beim Hervorheben, genau
         * wie im alten System.
         *
         * Ein abgeschalteter Knopf wird NICHT gedrueckt: dann fehlt die
         * Twitch-Freigabe, und der Grund steht in seinem title.
         */
        var raiden = kachel.querySelector('[data-raid-start]:not([disabled])');
        if (raiden) {
            raiden.click();

            return;
        }

        knopf.disabled = false;
    }

    function start() {
        var knopf = document.querySelector('[data-roulette]');
        if (!knopf) {
            return;
        }

        var gitter = document.getElementById(knopf.dataset.grid || '');
        if (!gitter) {
            return;
        }

        // Jetzt gibt es das Skript, also darf der Knopf sichtbar sein.
        knopf.hidden = false;

        knopf.addEventListener('click', function () {
            var kacheln = Array.prototype.slice.call(
                gitter.querySelectorAll('[data-raid-login]')
            );

            if (kacheln.length === 0) {
                return;
            }

            aufraeumen(kacheln);
            knopf.disabled = true;

            var ziel = Math.floor(Math.random() * kacheln.length);

            // Wer die Animation abbestellt hat, bekommt das Ergebnis
            // ohne sie. Ein Roulette ist Zierde; das Ergebnis ist es
            // nicht.
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                gewinner(kacheln, ziel, knopf);

                return;
            }

            // Bei wenigen Kacheln mehr Runden: zwei Runden ueber drei
            // Kacheln waeren sechs Schritte, und das ist kein Roulette,
            // das ist ein Blinken.
            var runden = kacheln.length < 6 ? 4 : 2;
            var schritte = runden * kacheln.length + ziel + 1;

            var stelle = 0;
            var getan = 0;
            var pause = START;

            function tick() {
                kacheln.forEach(function (kachel) {
                    kachel.classList.remove('is-spinning');
                });

                kacheln[stelle].classList.add('is-spinning');
                kacheln[stelle].scrollIntoView({ block: 'nearest', inline: 'nearest' });

                stelle = (stelle + 1) % kacheln.length;
                getan++;

                if (getan < schritte) {
                    pause = Math.min(LANGSAMSTENS, pause + BREMSE);
                    window.setTimeout(tick, pause);

                    return;
                }

                gewinner(kacheln, (stelle - 1 + kacheln.length) % kacheln.length, knopf);
            }

            tick();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    /*
     * Noch einmal, wenn ein Formular ohne Seitenwechsel abgeschickt
     * wurde: die Elemente von eben sind dann ausgetauscht, und mit
     * ihnen ihre Zuhoerer. Was am Dokument haengt, braucht das nicht -
     * das haelt, und ein zweites Anhaengen taete alles doppelt.
     */
    document.addEventListener('overlay:swapped', start);
}());
