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
 *
 * -------------------------------------------------------------------
 *  Zum Ton
 * -------------------------------------------------------------------
 *
 * Ein Alert hat seinen Ton entweder in einer eigenen Datei ODER im
 * Video - nicht beides. Danach richtet sich, was stumm geschaltet
 * wird; siehe zeige().
 *
 * Und beide haengen im Dokument, auch der reine Ton. Ein Element, das
 * nur im Speicher steht (new Audio(...)), spielt im Browser zwar - die
 * Browserquelle in OBS nimmt es aber nicht auf. Der Alert lief dann
 * mit Bild und blieb still.
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

        /*
         * Bringt dieser Alert eine eigene Tondatei mit?
         *
         * Davon haengt ab, ob das Video stumm laeuft. Vorher war es
         * IMMER stumm, mit der Begruendung, es wuerde sich sonst mit
         * der Tondatei ueberlagern - die Begruendung stimmt, gilt aber
         * nur, wenn es eine gibt. Wo der Ton im Video steckt und keine
         * Datei daneben liegt (Subs, die grossen Spendenstufen), hat
         * das jeden Alert stumm gemacht.
         */
        var eigeneTondatei = !!daten.audio;

        if (daten.video) {
            var medien = document.createElement('div');
            medien.className = 'alert-media';

            video = document.createElement('video');
            video.className = 'alert-video';
            video.src = daten.video;
            video.autoplay = true;
            video.loop = true;
            video.playsInline = true;

            // Stumm nur dann, wenn der Ton aus einer eigenen Datei
            // kommt - sonst laege er ueber dem des Videos.
            video.muted = eigeneTondatei;

            medien.appendChild(video);
            alert.appendChild(medien);
        }

        if (eigeneTondatei) {
            audio = document.createElement('audio');
            audio.className = 'alert-audio';
            audio.src = daten.audio;
            audio.autoplay = true;
            audio.preload = 'auto';

            // In den Alert und damit ins Dokument - siehe Kopf dieser
            // Datei. Zu sehen ist nichts: ohne "controls" zeichnet ein
            // Audioelement nicht, und das Stylesheet blendet es
            // zusaetzlich aus.
            alert.appendChild(audio);
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

        /*
         * Erst jetzt abspielen - beide haengen im Dokument.
         *
         * "autoplay" allein reicht nicht: es greift nur beim ersten
         * Anzeigen zuverlaessig, und ein abgelehnter Versuch bliebe
         * unbemerkt. Ein ausdrueckliches play() sagt, ob es geklappt
         * hat.
         */
        starte(audio, 'Ton');
        starte(video, 'Video');

        var dauer = Math.max(1, Number(daten.duration) || 8) * 1000;

        window.setTimeout(function () {
            alert.classList.remove('is-visible');

            if (audio) {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
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

    /**
     * Wiedergabe anstossen und melden, wenn sie abgelehnt wird.
     *
     * In einer Browserquelle startet OBS den Browser so, dass Ton ohne
     * Zutun laufen darf. Im Browser-Dock und im gewoehnlichen Browser
     * gilt das nicht - dort lehnt der Browser ab, solange niemand die
     * Seite angeklickt hat. Der Alert laeuft dann trotzdem, nur still,
     * und im Log steht warum.
     */
    function starte(element, was) {
        if (!element) {
            return;
        }

        var versuch = element.play();

        if (versuch && typeof versuch.catch === 'function') {
            versuch.catch(function (fehler) {
                console.warn('[alerts] ' + was + ' konnte nicht abgespielt werden:', fehler);
            });
        }
    }

    Overlay.queue('alerts', zeige);
}());
