/*
 * ===================================================================
 *  Die oeffentliche Musikseite
 * ===================================================================
 *
 * Uebernommen aus dem alten musik.talutah.de (public/assets/app.js) -
 * Regelfenster, Reiter, Suche, Favoriten, Warteschlange, Kurzmeldungen
 * und der Zaehler bis zum naechsten Wunsch. Dieselben Knoepfe an
 * denselben Stellen.
 *
 * Zwei Unterschiede zum Original:
 *
 *   - Die Adressen stehen nicht im Skript ("api.php?a=queue"), sondern
 *     im Block #music_config. Dieses System kann in einem
 *     Unterverzeichnis liegen.
 *   - Die Warteschlange kommt als Daten und wird hier gebaut. Das alte
 *     System schickte fertiges HTML aus api.php; HTML aus einer
 *     Antwort in die Seite zu setzen ist eine Tuer, die man nicht
 *     aufmachen muss.
 *
 * Ohne dieses Skript bleibt die Seite bedienbar: das Formular unter
 * "Manuell eintragen" ist ein Formular und kommt ohne aus.
 */
(function () {
    'use strict';

    /*
     * Alle zehn Sekunden - wie im alten System (setInterval 10000).
     * Die Warteschlange ist kein Fortschrittsbalken, und jeder Aufruf
     * geht ueber unseren Server zu Spotify.
     */
    var TAKT_MS = 10000;

    var konfiguration = (function () {
        var block = document.getElementById('music_config');

        if (!block) {
            return null;
        }

        try {
            return JSON.parse(block.textContent || '{}');
        } catch (e) {
            return null;
        }
    }());

    if (!konfiguration) {
        return;
    }

    var texte = konfiguration.texts || {};
    var adressen = konfiguration.urls || {};

    /** Merkliste als Menge von Kennungen - fuer "Favorit" oder nicht. */
    var gemerkt = {};

    /*
     * Was im Fenstertitel steht: der laufende Titel und die Restzeit
     * bis zum naechsten Wunsch, beides vor dem Namen der Seite.
     *
     * Ueber eine Funktion und nicht mit document.title += : das alte
     * System schrieb den Songnamen in einen Titel, der schon einen
     * Songnamen enthielt, und nach zehn Sekunden stand er zweimal da.
     */
    var grundTitel = document.title;
    var laufendeZeile = '';
    var restZeile = '';

    function titelPflegen() {
        document.title = (restZeile !== '' ? restZeile + ' - ' : '')
            + (laufendeZeile !== '' ? laufendeZeile + ' - ' : '')
            + grundTitel;
    }

    // -----------------------------------------------------------------
    //  Kurzmeldungen
    // -----------------------------------------------------------------
    function toastBehaelter() {
        var behaelter = document.querySelector('.toast-container');

        if (!behaelter) {
            behaelter = document.createElement('div');
            behaelter.className = 'toast-container';
            document.body.appendChild(behaelter);
        }

        return behaelter;
    }

    function zeigeToast(nachricht, art) {
        if (!nachricht) {
            return;
        }

        var erlaubt = ['success', 'error', 'info'];
        var variante = erlaubt.indexOf(art) >= 0 ? art : 'info';
        var toast = document.createElement('div');

        toast.className = 'toast toast-' + variante;
        toast.setAttribute('role', 'status');
        toast.textContent = nachricht;
        toastBehaelter().appendChild(toast);

        window.requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        window.setTimeout(function () {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', function () {
                toast.remove();
            }, { once: true });
        }, 4000);
    }

    // -----------------------------------------------------------------
    //  Reiter
    // -----------------------------------------------------------------
    function reiterUmschalten(gruppe, name) {
        if (!gruppe || !name) {
            return;
        }

        var knoepfe = gruppe.querySelector(':scope > .tab-buttons');
        var flaechen = gruppe.querySelector(':scope > .tab-panels');

        if (!knoepfe || !flaechen) {
            return;
        }

        knoepfe.querySelectorAll(':scope > .tab-button[data-tab]').forEach(function (knopf) {
            knopf.classList.toggle('active', knopf.dataset.tab === name);
        });

        flaechen.querySelectorAll(':scope > .tab-panel[data-tab-panel]').forEach(function (flaeche) {
            flaeche.classList.toggle('active', flaeche.dataset.tabPanel === name);
        });
    }

    function reiterZeigen(gruppenName, name) {
        var gruppe = document.querySelector('[data-tab-group="' + gruppenName + '"]');

        if (gruppe) {
            reiterUmschalten(gruppe, name);
        }
    }

    function reiterStarten() {
        document.querySelectorAll('[data-tab-group]').forEach(function (gruppe) {
            var knoepfe = gruppe.querySelector(':scope > .tab-buttons');

            if (!knoepfe) {
                return;
            }

            knoepfe.querySelectorAll(':scope > .tab-button[data-tab]').forEach(function (knopf) {
                knopf.addEventListener('click', function () {
                    reiterUmschalten(gruppe, knopf.dataset.tab);
                });
            });
        });
    }

    // -----------------------------------------------------------------
    //  Das Regelfenster
    // -----------------------------------------------------------------
    function regelfenster() {
        var flaeche = document.getElementById('rules_modal');

        if (!flaeche) {
            return;
        }

        var fenster = flaeche.querySelector('.modal');
        var knopf = document.getElementById('open_rules_btn');
        var verschlossen = flaeche.dataset.locked === 'true';
        var vonSelbst = flaeche.dataset.show === 'true';
        var zuletztFokussiert = null;

        function aufmachen() {
            if (flaeche.classList.contains('open')) {
                return;
            }

            zuletztFokussiert = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            flaeche.classList.add('open');
            document.body.classList.add('modal-open');

            if (fenster) {
                var erstes = fenster.querySelector('[autofocus], button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');

                if (erstes) {
                    erstes.focus();
                }
            }
        }

        function zumachen() {
            /*
             * Verschlossen heisst verschlossen: wer die Regeln noch
             * nicht angenommen hat, klickt sie nicht weg. So war es im
             * alten System, und es ist der einzige Grund, warum das
             * Fenster ueberhaupt eines ist.
             */
            if (verschlossen || !flaeche.classList.contains('open')) {
                return;
            }

            flaeche.classList.remove('open');
            document.body.classList.remove('modal-open');

            if (zuletztFokussiert) {
                zuletztFokussiert.focus();
            }
        }

        if (knopf) {
            knopf.addEventListener('click', function () {
                if (!verschlossen) {
                    aufmachen();
                }
            });
        }

        flaeche.querySelectorAll('[data-close-modal]').forEach(function (k) {
            k.addEventListener('click', zumachen);
        });

        flaeche.addEventListener('click', function (ereignis) {
            if (ereignis.target === flaeche) {
                zumachen();
            }
        });

        document.addEventListener('keydown', function (ereignis) {
            if (ereignis.key === 'Escape') {
                zumachen();
            }
        });

        if (vonSelbst) {
            aufmachen();
        }
    }

    // -----------------------------------------------------------------
    //  Eine Medienzeile
    // -----------------------------------------------------------------
    /**
     * Baut eine Zeile, wie sie das alte System baute
     * (createMediaItem): Bild, Text, Knoepfe.
     */
    function medienZeile(daten) {
        var zeile = document.createElement('div');
        zeile.className = daten.itemClass || 'media-item';

        var bild = document.createElement('div');
        bild.className = 'media-cover';

        if (daten.image) {
            var img = document.createElement('img');
            img.src = daten.image;
            img.alt = '';
            img.loading = 'lazy';
            bild.appendChild(img);
        } else if (daten.fallback) {
            // Zwei Buchstaben statt eines leeren Kastens - so fuellt
            // das alte System die Luecke, wenn Spotify kein Bild hat.
            bild.textContent = String(daten.fallback).substring(0, 2).toUpperCase();
        }

        var inhalt = document.createElement('div');
        inhalt.className = 'media-content';

        if (daten.title) {
            var titel = document.createElement('div');
            titel.className = 'media-title';
            titel.textContent = daten.title;

            if (daten.titleId) {
                titel.id = daten.titleId;
            }

            inhalt.appendChild(titel);
        }

        if (daten.subtitle) {
            var unter = document.createElement('div');
            unter.className = 'media-subtitle';
            unter.textContent = daten.subtitle;
            inhalt.appendChild(unter);
        }

        if (daten.meta) {
            var meta = document.createElement('div');
            meta.className = 'media-meta';
            meta.textContent = daten.meta;
            inhalt.appendChild(meta);
        }

        /*
         * In der Warteschlange umschliesst ein Verweis Bild und Text -
         * als Element und nicht als Klick per Skript, damit
         * Mittelklick, Kontextmenue und Tastatur von selbst gehen.
         */
        if (daten.href) {
            var verweis = document.createElement('a');
            verweis.className = 'queue-link';
            verweis.href = daten.href;
            verweis.target = '_blank';
            verweis.rel = 'noopener';
            verweis.appendChild(bild);
            verweis.appendChild(inhalt);
            zeile.appendChild(verweis);
        } else {
            zeile.appendChild(bild);
            zeile.appendChild(inhalt);
        }

        var knoepfe = daten.actions || [];

        if (knoepfe.length) {
            var leiste = document.createElement('div');
            leiste.className = 'media-actions';

            knoepfe.forEach(function (eintrag) {
                var knopf = document.createElement('button');
                knopf.type = 'button';
                knopf.textContent = eintrag.label;

                (eintrag.className || '').split(' ').forEach(function (klasse) {
                    if (klasse) {
                        knopf.classList.add(klasse);
                    }
                });

                if (typeof eintrag.onClick === 'function') {
                    knopf.addEventListener('click', eintrag.onClick);
                }

                leiste.appendChild(knopf);
            });

            zeile.appendChild(leiste);
        }

        return zeile;
    }

    function leerText(text) {
        var kasten = document.createElement('div');
        kasten.className = 'empty-state';
        kasten.textContent = text || '';

        return kasten;
    }

    // -----------------------------------------------------------------
    //  Wuenschen ueber das Formular
    // -----------------------------------------------------------------
    /**
     * Setzt die Adresse ins Feld und schickt das Formular ab - genau
     * wie "Auswaehlen" und "Senden" im alten System.
     *
     * Ueber das Formular und nicht per fetch: der Server entscheidet,
     * ob ein Wunsch durchgeht, und seine Antwort steht danach oben auf
     * der Seite.
     */
    function wuenschen(url) {
        var feld = document.getElementById('spotify_link');
        var formular = document.getElementById('wish_form');

        if (!feld || !formular) {
            return;
        }

        feld.value = url;
        formular.submit();
    }

    function anfrage(adresse, daten) {
        var koerper = new FormData();
        koerper.append('csrf', konfiguration.csrf || '');

        Object.keys(daten || {}).forEach(function (schluessel) {
            koerper.append(schluessel, daten[schluessel]);
        });

        return fetch(adresse, {
            method: 'POST',
            credentials: 'same-origin',
            body: koerper
        }).then(function (antwort) {
            return antwort.json();
        });
    }

    // -----------------------------------------------------------------
    //  Favoriten
    // -----------------------------------------------------------------
    function favoritenLaden() {
        var kasten = document.getElementById('favorites');

        if (!kasten || konfiguration.isUserBanned) {
            return Promise.resolve();
        }

        return fetch(adressen.favorites, { credentials: 'same-origin' })
            .then(function (antwort) { return antwort.json(); })
            .then(function (daten) {
                var liste = (daten && daten.favorites) || [];

                gemerkt = {};
                liste.forEach(function (eintrag) {
                    if (eintrag.id) {
                        gemerkt[eintrag.id] = true;
                    }
                });

                kasten.textContent = '';

                if (!liste.length) {
                    kasten.appendChild(leerText(texte.favoritesEmpty));

                    return;
                }

                liste.forEach(function (eintrag) {
                    var knoepfe = [];

                    if (konfiguration.allowAdding) {
                        knoepfe.push({
                            label: texte.send,
                            className: 'primary',
                            onClick: function () {
                                wuenschen(eintrag.url);
                            }
                        });
                    }

                    knoepfe.push({
                        label: texte.delete,
                        className: 'secondary',
                        onClick: function () {
                            favoritVergessen(eintrag.id);
                        }
                    });

                    kasten.appendChild(medienZeile({
                        image: eintrag.image,
                        fallback: eintrag.name || eintrag.artist,
                        title: eintrag.name,
                        subtitle: eintrag.artist,
                        actions: knoepfe
                    }));
                });
            })
            .catch(function () {
                kasten.textContent = '';
                kasten.appendChild(leerText(texte.favoritesError));
            });
    }

    function favoritMerken(url) {
        if (!url) {
            return;
        }

        anfrage(adressen.favorites, { action: 'add', link: url })
            .then(function (daten) {
                if (daten && daten.error) {
                    zeigeToast(daten.error, 'error');

                    return;
                }

                return favoritenLaden().then(function () {
                    var feld = document.getElementById('spotify_search');
                    var treffer = document.getElementById('spotify_results');

                    if (feld) {
                        feld.value = '';
                    }

                    if (treffer) {
                        treffer.textContent = '';
                    }

                    // Nach dem Merken dorthin, wo das Gemerkte liegt -
                    // so macht es das alte System auch.
                    reiterZeigen('wish-tabs', 'favorites');
                });
            })
            .catch(function () {
                zeigeToast(texte.favoritesError, 'error');
            });
    }

    function favoritVergessen(id) {
        if (!id) {
            return;
        }

        anfrage(adressen.favorites, { action: 'remove', track: id })
            .then(function () {
                return favoritenLaden();
            })
            .catch(function () {
                zeigeToast(texte.favoritesError, 'error');
            });
    }

    // -----------------------------------------------------------------
    //  Die Suche
    // -----------------------------------------------------------------
    /*
     * Der wichtigste Reiter: wer selbst kein Spotify hat, kann keinen
     * Teilen-Link kopieren - er tippt hier den Namen und nimmt, was
     * gefunden wird.
     */
    function suchen() {
        var feld = document.getElementById('spotify_search');
        var treffer = document.getElementById('spotify_results');

        if (!feld || !treffer) {
            return;
        }

        var begriff = feld.value.trim();

        if (begriff === '') {
            treffer.textContent = '';

            return;
        }

        fetch(adressen.search + '?q=' + encodeURIComponent(begriff), { credentials: 'same-origin' })
            .then(function (antwort) { return antwort.json(); })
            .then(function (daten) {
                treffer.textContent = '';

                var titel = (daten && daten.tracks) || [];

                if (!titel.length) {
                    treffer.appendChild(leerText(texte.searchEmpty));

                    return;
                }

                titel.forEach(function (eines) {
                    var knoepfe = [];

                    if (konfiguration.allowAdding) {
                        knoepfe.push({
                            label: texte.choose,
                            className: 'primary',
                            onClick: function () {
                                wuenschen(eines.url);
                            }
                        });
                    }

                    knoepfe.push({
                        label: texte.favorite,
                        className: 'secondary fav',
                        onClick: function () {
                            favoritMerken(eines.url);
                        }
                    });

                    treffer.appendChild(medienZeile({
                        image: eines.image,
                        fallback: eines.name || eines.artist,
                        title: eines.name,
                        subtitle: eines.artist,
                        actions: knoepfe
                    }));
                });
            })
            .catch(function () {
                treffer.textContent = '';
                treffer.appendChild(leerText(texte.searchFailed));
            });
    }

    function sucheStarten() {
        var feld = document.getElementById('spotify_search');

        if (!feld) {
            return;
        }

        var wecker;

        feld.addEventListener('input', function () {
            window.clearTimeout(wecker);
            wecker = window.setTimeout(suchen, 250);
        });

        feld.addEventListener('keydown', function (ereignis) {
            if (ereignis.key === 'Enter') {
                // Sonst schickt die Eingabetaste das Formular ab, das
                // zufaellig daneben steht.
                ereignis.preventDefault();
                window.clearTimeout(wecker);
                suchen();
            }
        });
    }

    // -----------------------------------------------------------------
    //  Sperren
    // -----------------------------------------------------------------
    function sperren(id, name, interpreten) {
        if (!id) {
            return;
        }

        var frage = name ? texte.banConfirm.replace('%{name}', name) : texte.banConfirmAny;

        if (!window.confirm(frage)) {
            return;
        }

        anfrage(adressen.ban, { track: id, name: name || '', artists: interpreten || '' })
            .then(function (daten) {
                if (daten && daten.error) {
                    zeigeToast(daten.error, 'error');

                    return;
                }

                zeigeToast((daten && daten.message) || texte.banned, 'success');
                warteschlangeHolen();
            })
            .catch(function () {
                zeigeToast(texte.banFailed, 'error');
            });
    }

    // -----------------------------------------------------------------
    //  Die Warteschlange
    // -----------------------------------------------------------------
    var warteschlangeHolen = function () {};

    function warteschlangeStarten() {
        var kasten = document.getElementById('queue');

        if (!kasten || !window.fetch) {
            return;
        }

        function abschnitt(ueberschrift, eintraege, laufend) {
            var bereich = document.createElement('section');
            bereich.className = 'queue-section';

            var kopf = document.createElement('h3');
            kopf.textContent = ueberschrift;
            bereich.appendChild(kopf);

            var liste = document.createElement('div');
            liste.className = 'queue-sublist';

            eintraege.forEach(function (titel) {
                var knoepfe = [];

                /*
                 * "Favorit" faellt weg, wenn der Titel schon gemerkt
                 * ist - im alten System entschied das die Liste der
                 * gemerkten Adressen, hier die der Kennungen.
                 */
                if (konfiguration.canFavorite && !konfiguration.isUserBanned
                    && titel.id && !gemerkt[titel.id] && titel.url) {
                    knoepfe.push({
                        label: texte.favorite,
                        className: 'secondary fav',
                        onClick: function () {
                            favoritMerken(titel.url);
                        }
                    });
                }

                if (laufend && konfiguration.canBan && titel.id) {
                    knoepfe.push({
                        label: texte.ban,
                        className: 'danger ban-track',
                        onClick: function () {
                            sperren(titel.id, titel.name, titel.artists);
                        }
                    });
                }

                liste.appendChild(medienZeile({
                    itemClass: 'queue-item',
                    href: titel.url || '',
                    image: titel.image,
                    fallback: titel.name || titel.artists,
                    title: titel.name,
                    titleId: laufend ? 'current_name' : '',
                    subtitle: titel.artists,
                    meta: titel.wishedBy ? texte.wishedBy + ': ' + titel.wishedBy : '',
                    actions: knoepfe
                }));
            });

            bereich.appendChild(liste);

            return bereich;
        }

        warteschlangeHolen = function () {
            fetch(adressen.queue, { credentials: 'same-origin' })
                .then(function (antwort) { return antwort.json(); })
                .then(function (daten) {
                    if (!daten) {
                        return;
                    }

                    var neu = document.createElement('div');

                    if (daten.current) {
                        neu.appendChild(abschnitt(texte.now, [daten.current], true));
                    }

                    if ((daten.recent || []).length) {
                        neu.appendChild(abschnitt(texte.recent, daten.recent, false));
                    }

                    if ((daten.items || []).length) {
                        neu.appendChild(abschnitt(texte.next, daten.items, false));
                    }

                    if (!neu.childNodes.length) {
                        neu.appendChild(leerText(texte.queueEmpty));
                    }

                    /*
                     * Nur austauschen, wenn sich etwas geaendert hat -
                     * sonst verliert alle zehn Sekunden jeder Knopf
                     * seinen Fokus. Genau die Pruefung machte auch das
                     * alte System.
                     */
                    if (kasten.innerHTML !== neu.innerHTML) {
                        kasten.textContent = '';

                        while (neu.firstChild) {
                            kasten.appendChild(neu.firstChild);
                        }
                    }

                    var abzeichen = document.getElementById('queue_status');

                    if (abzeichen) {
                        abzeichen.textContent = daten.enabled ? texte.queueActive : texte.queuePaused;
                    }

                    var karte = document.getElementById('queuediv');

                    if (karte) {
                        karte.classList.toggle('disabled', !daten.enabled);
                    }

                    /*
                     * Der laufende Titel im Fenstertitel - so sieht
                     * man in der Leiste, was gerade laeuft, ohne die
                     * Seite hervorzuholen. Aus dem alten System.
                     */
                    var name = document.getElementById('current_name');
                    laufendeZeile = name ? name.textContent : '';
                    titelPflegen();
                })
                .catch(function () {
                    // Ein Aussetzer ist kein Grund, die Liste zu
                    // leeren: was dasteht, stimmte vor zehn Sekunden
                    // noch, und beim naechsten Versuch steht es wieder.
                });
        };

        warteschlangeHolen();
        window.setInterval(warteschlangeHolen, TAKT_MS);
    }

    // -----------------------------------------------------------------
    //  Die Wartezeit
    // -----------------------------------------------------------------
    function wartezeit() {
        var feld = document.getElementById('wishtimer');

        if (!feld || !feld.dataset.time) {
            return;
        }

        var ziel = parseInt(feld.dataset.time, 10);

        if (!isFinite(ziel)) {
            return;
        }

        var takt = window.setInterval(function () {
            var rest = Math.max(0, ziel - Math.floor(Date.now() / 1000));

            if (rest <= 0) {
                /*
                 * Fertig - aber das Formular fehlt noch. Es hier
                 * hinzuzubauen waere eine zweite Wahrheit neben dem
                 * Server; der entscheidet, ob ein Wunsch durchgeht.
                 * Also neu laden und die Antwort von dort holen.
                 */
                window.clearInterval(takt);
                window.location.reload();

                return;
            }

            feld.textContent = rest > 59
                ? Math.floor(rest / 60) + ':' + ('0' + (rest % 60)).slice(-2)
                : String(rest);

            // Auch im Fenstertitel - im alten System stand er dort,
            // damit man die Seite nicht offen halten muss.
            restZeile = feld.textContent;
            titelPflegen();
        }, 1000);
    }

    // -----------------------------------------------------------------
    function start() {
        regelfenster();
        reiterStarten();
        sucheStarten();
        favoritenLaden();
        warteschlangeStarten();
        wartezeit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}());
