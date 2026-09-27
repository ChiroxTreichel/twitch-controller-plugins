# Tip-Goals - PayPal

**PayPal als Zahlungsweg** für die Spendenseite von **Tip-Goals**.

Braucht **Tip-Goals** — das hält die Seite unter `/tips`, die Ziele, die
Spenden, die Rechtstexte und den Alert. Dieses Plugin bringt nur den Weg
mit, auf dem das Geld fließt.

## Kommst du von 1.x?

Bis 1.x war hier die ganze Spendenseite drin. Ab 2.0 ist sie in
**Tip-Goals** umgezogen, und dieses Plugin meldet sich dort als
Zahlungsanbieter an.

Nach dem Update auf 2.0 **installiere Tip-Goals** — bis dahin ist
`/tips` zu. Beim Installieren zieht Tip-Goals alles herüber: Ziele mit
ihrem Stand, Spenden, Rechtstexte, Aussehen, Alert und Rechte. Hier
bleiben nur Zugang, Modus und Gebührensätze.

Die Rückkehr von PayPal liegt jetzt unter `/tips/paypal/return`. In 1.x
stand sie unter `/tips/return`, und dort kam sie nie an: die Adresse
beantwortete die Seite für die Rechtstexte, und der Spender sah
„Gerade geschlossen“, ohne dass eingezogen wurde.

## Bis zum Schluss fließt kein Geld

Der Weg hat drei Schritte, und zwischen zweien davon ist der Spender
woanders:

1. **Order anlegen** — PayPal antwortet mit einer Adresse
2. **Weiterleiten** — der Spender bezahlt bei PayPal
3. **Rückkehr → Capture** — und *jetzt* fließt Geld

Bricht jemand bei PayPal ab oder schließt den Tab, bleibt eine
genehmigte, unbezahlte Order stehen. Die läuft nach 30 Minuten ab und
wird weggeräumt. Unschön, aber harmlos — gefährlich wäre der umgekehrte
Fall.

### Genau einmal eingezogen

Derselbe Merker geht beim Anlegen und beim Einziehen als
`PayPal-Request-Id` mit — PayPal zieht auch bei einem zweiten Versuch
nur einmal ein. Dass nur einmal *gebucht* wird, sorgt Tip-Goals.

## Was auf dem Ziel landet

Der **Netto**betrag, wenn PayPal ihn nennt — also das, was wirklich
ankommt.

Wer „Gebühren übernehmen“ anhakt, meint den Betrag, der ankommen soll;
der Aufschlag wird aus den Sätzen hier gerechnet und auf Cent
aufgerundet. Vorgegeben sind **2,99 % + 0,39 €**, der Satz für Spenden
innerhalb Deutschlands. Gerechnet wird damit nur der *Vorschlag* im
Formular — was wirklich abgezogen wurde, sagt die Abrechnung.

## Die Zugangsdaten

Client-ID und Secret aus dem PayPal-Entwicklerkonto (*Apps &
Credentials*), **verschlüsselt** abgelegt und nie wieder angezeigt. Ein
leeres Feld heißt „nicht ändern“ — sonst würfe ein Speichern der
Gebührensätze nebenbei den Zugang zum Geldkonto weg. Zum Löschen gibt es
einen eigenen Knopf.

Ohne Zugang zählt PayPal als „nicht eingerichtet“: Tip-Goals bietet es
dem Spender dann nicht an.

**Testkonto ist die Vorgabe.** Erst sehen, dass der Weg funktioniert,
dann bewusst auf Echtbetrieb schalten. Sandbox und Live haben
verschiedene Schlüssel.

## Rechte

Die von Tip-Goals: `TipGoals.Global.View` sieht den Zugang,
`TipGoals.Global.Edit` darf ihn ändern.

## Was das Plugin speichert

Keine eigene Tabelle — Order- und Capture-Nummer stehen bei Tip-Goals an
der Spende. Im Bereich `plugin:paypal-tip-goals`: die verschlüsselten
Zugangsdaten, der Modus und die Gebührensätze. Beim Entfernen geht das
mit; die Spenden bleiben bei Tip-Goals, bei PayPal bleibt ohnehin alles
stehen.
