/**
 * ===================================================================
 *  Alerts im Overlay
 * ===================================================================
 *
 * Ein Alert nach dem anderen. Dafuer gibt es Overlay.queue(): der
 * naechste wartet, bis dieser fertig gemeldet hat - sonst laegen bei
 * einem Raid fuenf Alerts uebereinander.
 *
 * Was ankommt, hat der Server schon fertig gemacht: "html" ist
 * gerendert und escaped (siehe src/Alerts.php), "video" und "audio"
 * sind geprueft. Hier wird nur noch angezeigt.
 */
(function () {
    'use strict';

    if (typeof window.Overlay !== 'object') {
        console.error('[alerts] Das Overlay ist nicht geladen - ohne das geht nichts.');
        return;
    }

    var kasten = Overlay.slot('alerts');
    if (!kasten) {
        return;
    }

    /**
     * Zeigt einen Alert und meldet sich, wenn er durch ist.
     *
     * Die Dauer entscheidet, wie lange er steht - nicht die Laenge des
     * Videos. Ein Video laeuft in Schleife, damit ein kurzer Clip
     * einen langen Alert nicht mit einem Standbild beendet.
     */
    function zeige(daten, fertig) {
        var alert = document.createElement('div');
        alert.className = 'alert';

        var video = null;
        var audio = null;

        if (daten.video) {
            var medien = document.createElement('div');
            medien.className = 'alert-media';

            video = document.createElement('video');
            video.className = 'alert-video';
            video.src = daten.video;
            video.autoplay = true;
            video.loop = true;
            video.playsInline = true;

            // Stumm: der Ton kommt aus der Audiodatei. Ein Video mit
            // eigenem Ton wuerde sich damit ueberlagern.
            video.muted = true;

            medien.appendChild(video);
            alert.appendChild(medien);
        }

        if (daten.html) {
            var text = document.createElement('p');
            text.className = 'alert-copy';

            // Vom Server gerendert und dort escaped. Deshalb hier
            // innerHTML - die Akzent-Spans sollen wirken.
            text.innerHTML = daten.html;

            alert.appendChild(text);
        }

        kasten.appendChild(alert);

        // Ein Bildaufbau abwarten, sonst greift der Uebergang nicht.
        window.requestAnimationFrame(function () {
            alert.classList.add('is-visible');
        });

        if (daten.audio) {
            audio = new Audio(daten.audio);

            // Autoplay mit Ton ist in einer Browserquelle erlaubt, im
            // Browser-Dock nicht immer. Scheitert es, laeuft der Alert
            // trotzdem - nur still.
            var versuch = audio.play();
            if (versuch && typeof versuch.catch === 'function') {
                versuch.catch(function (fehler) {
                    console.warn('[alerts] Ton konnte nicht abgespielt werden:', fehler);
                });
            }
        }

        var dauer = Math.max(1, Number(daten.duration) || 8) * 1000;

        window.setTimeout(function () {
            alert.classList.remove('is-visible');

            if (audio) {
                audio.pause();
            }
            if (video) {
                // Ohne das laedt die Quelle im Hintergrund weiter.
                video.pause();
                video.removeAttribute('src');
                video.load();
            }

            // Erst nach dem Ausblenden entfernen, sonst verschwindet
            // er ruckartig.
            window.setTimeout(function () {
                alert.remove();
                fertig();
            }, 260);
        }, dauer);
    }

    Overlay.queue('alerts', zeige);
}());
