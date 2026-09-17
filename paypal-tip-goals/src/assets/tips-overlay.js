/*
 * ===================================================================
 *  Durch die Spendenziele rotieren
 * ===================================================================
 *
 * Wer drei Ziele pflegt, sah im Overlay nur das erste. Im alten System
 * wanderte der Balken alle 60 Sekunden zum naechsten weiter
 * (TIP_ROTATE_MS in overlay-ws.js), und genau das fehlte.
 *
 * Warum hier und nicht im Goals-Plugin: auf DIESER Spendenseite waehlt
 * der Spender sein Ziel aus, also ist jedes Ziel in der Liste eines,
 * auf das gerade eingezahlt werden kann. Bei StreamElements und
 * Streamlabs kommt die Spende aus einer Schnittstelle und weiss nichts
 * von Zielen - sie landet immer auf dem obersten. Dort waere ein
 * rotierender Balken eine Behauptung: er zeigte ein Ziel, auf das
 * nichts einzahlen kann.
 *
 * Die Entscheidung gehoert also in das Plugin, das sie trifft, und
 * nicht in den gemeinsamen Unterbau. Goals reicht dafuer nur die Tuer
 * (GOALS.patch) - den Zustand haelt es, weil eine Nachricht immer nur
 * einen Ausschnitt enthaelt.
 *
 * Ohne dieses Skript bleibt der Balken auf dem ersten Ziel stehen:
 * genau so, wie es vorher war.
 */
(function () {
    'use strict';

    /* Wie im alten System. */
    var ROTATION_MS = 60000;

    if (!window.Overlay || !window.GOALS || typeof window.GOALS.patch !== 'function') {
        // Goals laedt vor diesem Skript - es ist eine harte
        // Voraussetzung im Manifest. Fehlt es trotzdem, ist Schweigen
        // richtig: der Balken steht dann eben still.
        return;
    }

    /** @return {Array} die Ziele, so wie TipGoals::values() sie schickt */
    function liste(quelle) {
        var roh = quelle && quelle.tip_goals;

        return Object.prototype.toString.call(roh) === '[object Array]' ? roh : null;
    }

    var ziele = liste(window.GOALS_STATE) || [];
    var stelle = 0;

    /*
     * Den gerade sichtbaren Eintrag in die flachen Werte schreiben.
     *
     * "tip_title", "tip_current" und "tip_goal" sind der Vertrag, an
     * dem das Geruest haengt - data-bind und data-fill lesen genau
     * diese Namen. Ein aus dem alten System kopiertes Geruest rotiert
     * damit mit, ohne geaendert zu werden.
     */
    function zeigen() {
        if (ziele.length === 0) {
            return;
        }

        // Die Liste kann kuerzer geworden sein - ein geloeschtes Ziel
        // darf nicht in eine leere Anzeige laufen.
        var eintrag = ziele[stelle % ziele.length];

        if (!eintrag) {
            return;
        }

        window.GOALS.patch({
            tip_title: eintrag.title,
            tip_current: eintrag.current,
            tip_goal: eintrag.goal
        });
    }

    function weiter() {
        // Bei einem einzigen Ziel gibt es nichts zu drehen.
        if (ziele.length <= 1) {
            return;
        }

        stelle = (stelle + 1) % ziele.length;
        zeigen();
    }

    /*
     * Auch bei jeder Nachricht, nicht nur beim Weiterdrehen.
     *
     * Kommt eine Spende herein, aendert sich der Betrag in der Liste -
     * und Goals hat gerade die flachen Werte aus der Nachricht
     * eingesetzt, also das ERSTE Ziel. Der sichtbare Balken zeigte
     * sonst bis zur naechsten Minute das falsche.
     *
     * Overlay.on erlaubt mehrere Zuhoerer je Platz; Goals ist schon
     * einer davon und hat vorher gezeichnet.
     */
    window.Overlay.on('goals', function (daten) {
        var neu = liste(daten);

        if (neu === null) {
            return;
        }

        ziele = neu;
        zeigen();
    });

    window.setInterval(weiter, ROTATION_MS);

    // Und einmal gleich zu Beginn: der Anfangszustand nennt das erste
    // Ziel, und das ist auch das, wo wir stehen - aber die Liste kann
    // andere Betraege tragen als die flachen Werte.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', zeigen, { once: true });
    } else {
        zeigen();
    }
}());
