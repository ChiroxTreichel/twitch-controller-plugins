# Live-Benachrichtigung – Chat

Meldet im eigenen Twitch-Chat, wenn ein beobachteter Kanal live geht.
Ein Ziel wie Discord, nur anderswohin.

Braucht **Live-Benachrichtigung – Discord ab 1.0.0**: dort liegen die
Kanalliste, die Live-Erkennung und die Einhängepunkte.

## Nur während der eigene Stream läuft

Das ist die eine Regel, die dieses Ziel von den anderen unterscheidet —
und sie stand schon im alten System, aus demselben Grund: ein Post in
einen Chat, in dem niemand ist, geht nicht verloren. Er steht dort, wenn
der nächste Stream anfängt, und wirkt dann wie eine Meldung von gerade.

Läuft der eigene Stream nicht, steht der Grund im Log
(`docker compose logs -f web worker`) und nicht als Fehlermeldung in der
Oberfläche: es ist kein Fehler, sondern der gewollte Verzicht.

## Gesendet wird über die Kernfähigkeit

`Chat::send()`, nicht über einen eigenen Weg zu Helix. Sie kennt den
richtigen Absender — das Bot-Konto, wenn eines verbunden ist, sonst den
Kanalinhaber —, kürzt auf Twitchs 500 Zeichen und merkt sich die eigene
Nachricht, damit kein Chatbefehl auf sie anspringt.

Ist kein Absender verbunden, meldet das Ziel das von sich aus: der Grund
steht dann oben auf der Kanalseite, statt dass der Haken stillschweigend
nichts tut.

## Die Vorlage

Unter **Plugins → Einstellungen → Live-Benachrichtigung – Chat**. Die
Platzhalter sind die des Basis-Plugins:

`{{login}}` · `{{display_name}}` · `{{title}}` · `{{game_name}}` ·
`{{url}}`

Die Vorlage darf 300 Zeichen haben — knapper als Twitchs 500, weil Titel
und Spielname noch hineinkommen. Eine Vorlage, die schon allein zu lang
ist, ergibt nie eine vollständige Nachricht.

## Rechte

| Recht | Bedeutung |
| --- | --- |
| `LiveNotifyChat.Global.Edit` | darf die Nachrichtenvorlage ändern |

Wer den **Haken** je Kanal setzen darf, entscheidet das Basis-Plugin mit
`LiveNotify.Global.Edit`. Eine eigene Erlaubnis dafür wäre ein zweites
Schloss an derselben Tür.

## Was das Plugin speichert

Die Nachrichtenvorlage, unter `message` im Bereich
`plugin:live-notify-chat`. Keine Tabelle.

Welche Kanäle dieses Ziel benutzen, steht **nicht** hier — das ist der
Haken in der Kanalzeile des Basis-Plugins. Ein zweiter Ort dafür wäre
sofort uneinig mit dem ersten. Wird dieses Plugin entfernt, bleibt der
Haken stehen und wirkt nach einer Neuinstallation sofort wieder.
