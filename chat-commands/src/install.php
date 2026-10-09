<?php

declare(strict_types=1);

/**
 * Chatbefehle legt keine Tabelle an.
 *
 * Die Grundbefehle liegen als "builtin.<name>" im Scope
 * "plugin:chat-commands", die eigenen Befehle gesammelt unter
 * "custom". Den Scope loescht der Kern beim Entfernen des Plugins mit.
 *
 * Vorgaben stehen in src/Commands.php und greifen beim ersten Lesen -
 * nach der Installation ist alles benutzbar, ohne dass eine Zeile in
 * der Datenbank steht. Nur der Anmeldelink fuer !discord fehlt, und
 * den kann niemand erraten.
 *
 * @var \TwitchController\Core\Database\Db $db
 * @var \TwitchController\Core\Config\Settings $settings
 * @var string|null $fromVersion
 */

/*
 * 1.3.0: {USER} heisst jetzt {{ username }}, wie bei den Alerts.
 *
 * Die alte Schreibweise wird nicht mehr ersetzt - also einmal alle
 * gespeicherten Befehle umschreiben. Ohne das stuende nach dem Update
 * in jeder Antwort ein nacktes "{USER}" im Chat.
 *
 * Aeltere Staende hielten statt des Textes ein Array mit 'response'
 * (siehe Commands::custom()); beide Formen werden umgeschrieben.
 */
if ($fromVersion !== null && version_compare($fromVersion, '1.3.0', '<')) {
    $scope = \TwitchController\Core\Config\Settings::pluginScope('chat-commands');
    $befehle = $settings->get('custom', null, $scope);

    if (is_array($befehle)) {
        foreach ($befehle as $name => $antwort) {
            if (is_array($antwort) && isset($antwort['response'])) {
                $befehle[$name]['response'] = str_replace('{USER}', '{{ username }}', (string) $antwort['response']);
            } elseif (is_string($antwort)) {
                $befehle[$name] = str_replace('{USER}', '{{ username }}', $antwort);
            }
        }

        $settings->set('custom', $befehle, $scope);
    }
}
