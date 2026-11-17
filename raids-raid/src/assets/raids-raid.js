/*
 * ===================================================================
 *  Raids - Raiden: der Stream des Ziels in einem eigenen Fenster
 * ===================================================================
 *
 * Nach einem bestaetigten Raid steht "watch" in der Adresse, und die
 * Seite bringt ein <dialog data-raid-watch> mit. Das wird hier gezeigt.
 *
 * "Oeffnen" macht ein eigenes Fenster statt eines Tabs auf: so bleibt
 * die Seite mit dem Abbruch-Knopf sichtbar, und der Stream liegt
 * daneben. Blockiert der Browser das Fenster trotzdem, bleibt die
 * Rueckfrage offen und sagt es - der Link geht dann als neuer Tab.
 */
(function () {
    'use strict';

    function start() {
        var dialog = document.querySelector('dialog[data-raid-watch]');

        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }

        // Einmal gefragt reicht: ohne das kaeme die Frage bei jedem
        // Neuladen der Seite wieder.
        try {
            var adresse = new URL(window.location.href);
            adresse.searchParams.delete('watch');
            window.history.replaceState(null, '', adresse.toString());
        } catch (e) {
            // Alte Browser: dann kommt die Frage eben noch einmal.
        }

        var oeffnen = dialog.querySelector('[data-raid-watch-open]');
        var schliessen = dialog.querySelector('[data-raid-watch-close]');
        var blockiert = dialog.querySelector('[data-raid-watch-blocked]');

        if (schliessen) {
            schliessen.addEventListener('click', function () {
                dialog.close();
            });
        }

        if (oeffnen) {
            oeffnen.addEventListener('click', function (ereignis) {
                var breite = Math.min(1280, window.screen.availWidth || 1280);
                var hoehe = Math.min(800, window.screen.availHeight || 800);

                var fenster = window.open(
                    oeffnen.href,
                    'raid-watch',
                    'popup=yes,width=' + breite + ',height=' + hoehe
                );

                if (!fenster) {
                    // Blockiert. Dann gilt der Link selbst - als Tab.
                    if (blockiert) {
                        blockiert.hidden = false;
                    }

                    return;
                }

                ereignis.preventDefault();
                fenster.focus();
                dialog.close();
            });
        }

        dialog.showModal();
    }

    // ---------------------------------------------------------------
    //  Modus "yes": das Fenster geht schon beim Klick auf "Raiden" auf
    // ---------------------------------------------------------------
    /*
     * Ein Fenster darf nur auf einen Klick hin aufgehen - nach dem
     * Neuladen der Seite waere es fuer den Browser Werbung. Also: beim
     * Klick ein leeres Fenster oeffnen, den Raid im Hintergrund
     * schicken, und erst wenn Twitch ihn angenommen hat, den Stream
     * hineinladen. Lehnt Twitch ab, geht das Fenster wieder zu.
     *
     * Woran die Annahme zu erkennen ist: die Antwort leitet auf die
     * Live-Seite um, und nur bei Erfolg steht dort "watch" in der
     * Adresse.
     *
     * Geht das Fenster nicht auf (blockiert, oder das Roulette hat den
     * Knopf per Skript gedrueckt), wird ganz normal abgeschickt - die
     * Live-Seite fragt dann wie bei "ask".
     */
    document.addEventListener('submit', function (ereignis) {
        var formular = ereignis.target;

        if (!formular || !formular.matches || !formular.matches('form[data-raid-watch-mode="yes"]')) {
            return;
        }

        if (typeof window.fetch !== 'function') {
            return;
        }

        var breite = Math.min(1280, window.screen.availWidth || 1280);
        var hoehe = Math.min(800, window.screen.availHeight || 800);
        var fenster = window.open('', 'raid-watch', 'popup=yes,width=' + breite + ',height=' + hoehe);

        if (!fenster) {
            return;
        }

        ereignis.preventDefault();

        try {
            fenster.document.title = 'Twitch …';
            fenster.document.body.style.cssText = 'background:#0e0e10;color:#efeff1;font:16px sans-serif;display:grid;place-items:center;height:100vh;margin:0;';
            fenster.document.body.textContent = '…';
        } catch (e) {
            // Nur Schmuck.
        }

        window.fetch(formular.action, {
            method: 'POST',
            body: new FormData(formular),
            credentials: 'same-origin'
        }).then(function (antwort) {
            var ziel = new URL(antwort.url);
            var login = ziel.searchParams.get('watch');

            if (login) {
                fenster.location.href = 'https://www.twitch.tv/' + encodeURIComponent(login);
                ziel.searchParams.delete('watch');
            } else {
                fenster.close();
            }

            window.location.href = ziel.toString();
        }).catch(function () {
            // Netzfehler: Fenster zu, und noch einmal ohne Umweg - dann
            // steht wenigstens die Meldung des Servers auf der Seite.
            fenster.close();
            formular.submit();
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
