/* -------------------------------------------------------------------
 *  /tips - Betragsrechner und Zielkarussell
 *
 *  Uebernommen aus dem alten spenden.talutah.de, dort stand es als
 *  <script> mitten in der Seite. Hier ist es eine Datei: sie laesst
 *  sich zwischenspeichern, und derselbe Code steht nicht ein zweites
 *  Mal in jeder Auslieferung.
 *
 *  Beide Teile sind Zugabe. Ohne sie steht der Betrag trotzdem im
 *  Feld, und die Ziele liegen untereinander mit sichtbaren
 *  Radioknoepfen - dafuer sorgt der noscript-Block im Kopf.
 * ------------------------------------------------------------------- */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     *  Was ankommt - und was man bezahlt
     * --------------------------------------------------------------- */
    (function () {
        var feld    = document.getElementById('amount-input');
        var anzeige = document.getElementById('net-amount');
        var kasten  = document.getElementById('net-info-block');
        var schild  = document.getElementById('net-label');
        var formel  = document.getElementById('net-formula');
        var warnung = document.getElementById('net-warn-block');
        var schalter = document.getElementById('cover-fees-toggle');

        if (!feld || !anzeige || !kasten) {
            return;
        }

        var prozent = parseFloat(kasten.getAttribute('data-fee-percent') || '0');
        var fest    = parseFloat(kasten.getAttribute('data-fee-fixed') || '0');

        // Die Texte kommen aus der Seite und nicht von hier: uebersetzt
        // wird in PHP, und eine zweite Sprachliste in JavaScript liefe
        // irgendwann auseinander.
        var textNetto = kasten.getAttribute('data-label-net') || '';
        var textBrutto = kasten.getAttribute('data-label-gross') || '';
        var formelNetto = kasten.getAttribute('data-formula-net') || '';
        var formelBrutto = kasten.getAttribute('data-formula-gross') || '';

        function euro(betrag) {
            return betrag.toFixed(2).replace('.', ',') + ' €';
        }

        function gebuehrText() {
            return prozent.toFixed(2).replace('.', ',') + ' % + ' + euro(fest);
        }

        // Aus dem Wunschbetrag den Bruttobetrag rechnen: die Gebuehr
        // haengt am Endbetrag, daher die Division. Aufgerundet auf
        // Cent, damit wirklich mindestens der Wunschwert ankommt -
        // dieselbe Rechnung wie in TipGoals::gross().
        function bruttoFuer(netto) {
            var nenner = 1 - (prozent / 100);

            if (nenner <= 0) {
                return netto;
            }

            return Math.ceil(((netto + fest) / nenner) * 100) / 100;
        }

        function aktualisiere() {
            var roh = String(feld.value || '').replace(',', '.');
            var wert = parseFloat(roh);
            var uebernimmt = !!(schalter && schalter.checked);

            if (schild) {
                schild.textContent = uebernimmt ? textBrutto : textNetto;
            }

            if (formel) {
                if (uebernimmt) {
                    var betragText = (isFinite(wert) && wert > 0) ? euro(wert) : '–';
                    formel.textContent = formelBrutto
                        .replace('%{amount}', betragText)
                        .replace('%{fee}', gebuehrText());
                } else {
                    formel.textContent = formelNetto.replace('%{fee}', gebuehrText());
                }
            }

            // Der Hinweis, dass Ziele und Alerts mit dem Nettobetrag
            // rechnen, gilt nur, wenn der Spender die Gebuehr NICHT
            // uebernimmt - sonst stimmt er nicht mehr.
            if (warnung) {
                warnung.classList.toggle('is-hidden', uebernimmt);
            }

            if (!isFinite(wert) || wert <= 0) {
                anzeige.textContent = '–';
                return;
            }

            if (uebernimmt) {
                anzeige.textContent = euro(bruttoFuer(wert));
                return;
            }

            var netto = wert - (wert * prozent / 100) - fest;
            anzeige.textContent = euro(netto < 0 ? 0 : netto);
        }

        Array.prototype.forEach.call(document.querySelectorAll('.preset-button'), function (knopf) {
            knopf.addEventListener('click', function () {
                var betrag = knopf.getAttribute('data-amount');

                if (betrag !== null) {
                    feld.value = betrag;
                }

                feld.focus();
                aktualisiere();
            });
        });

        feld.addEventListener('input', aktualisiere);

        if (schalter) {
            schalter.addEventListener('change', aktualisiere);
        }

        aktualisiere();
    }());

    /* ---------------------------------------------------------------
     *  Das Zielkarussell
     * --------------------------------------------------------------- */
    (function () {
        var karussell = document.querySelector('.goal-carousel');

        if (!karussell) {
            return;
        }

        var schiene = karussell.querySelector('.goal-carousel-track');
        var karten = Array.prototype.slice.call(karussell.querySelectorAll('.goal-slide'));
        var zurueck = karussell.querySelector('.goal-carousel-prev');
        var weiter = karussell.querySelector('.goal-carousel-next');
        var punkte = Array.prototype.slice.call(document.querySelectorAll('.goal-carousel-dot'));

        if (!schiene || karten.length === 0) {
            return;
        }

        var jetzt = parseInt(karussell.getAttribute('data-start-index') || '0', 10);

        if (!isFinite(jetzt) || jetzt < 0 || jetzt >= karten.length) {
            jetzt = 0;
        }

        function zeige(nummer) {
            if (nummer < 0) {
                nummer = karten.length - 1;
            }

            if (nummer >= karten.length) {
                nummer = 0;
            }

            jetzt = nummer;
            schiene.style.transform = 'translateX(-' + (nummer * 100) + '%)';

            karten.forEach(function (karte, i) {
                karte.classList.toggle('is-current', i === nummer);

                // Die sichtbare Karte IST die Auswahl - der Radioknopf
                // darin wird mitgesetzt. Er ist es auch, der abgeschickt
                // wird; ein verstecktes Feld daneben waere eine zweite
                // Wahrheit.
                var knopf = karte.querySelector('input[type="radio"]');

                if (knopf) {
                    knopf.checked = (i === nummer);
                }
            });

            punkte.forEach(function (punkt, i) {
                punkt.classList.toggle('is-current', i === nummer);
            });
        }

        if (zurueck) {
            zurueck.addEventListener('click', function () { zeige(jetzt - 1); });
        }

        if (weiter) {
            weiter.addEventListener('click', function () { zeige(jetzt + 1); });
        }

        punkte.forEach(function (punkt) {
            punkt.addEventListener('click', function () {
                var nummer = parseInt(punkt.getAttribute('data-index') || '0', 10);

                if (isFinite(nummer)) {
                    zeige(nummer);
                }
            });
        });

        // Wer mit der Tastatur durch die Radioknoepfe geht, blaettert
        // damit auch das Karussell - sonst waehlte er eine Karte, die
        // er nicht sieht.
        karten.forEach(function (karte, i) {
            var knopf = karte.querySelector('input[type="radio"]');

            if (knopf) {
                knopf.addEventListener('focus', function () { zeige(i); });
            }
        });

        // Die erste Stellung ohne Uebergang setzen, sonst fliegt die
        // Schiene beim Laden einmal durchs Bild. Zwei Bilder warten,
        // damit das transform durch ist, bevor die Uebergaenge wieder
        // angehen.
        schiene.style.transition = 'none';
        zeige(jetzt);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                schiene.style.transition = '';
            });
        });
    }());
}());
