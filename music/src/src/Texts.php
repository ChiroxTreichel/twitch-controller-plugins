<?php

declare(strict_types=1);

namespace TwitchController\Plugin\Music;

/**
 * Texte, die von einem Fall abhaengen.
 *
 * Warum das hier steht statt "translate('music.public.denied.' . $grund)":
 * ein zusammengesetzter Schluessel ist fuer bin/lang.php unsichtbar.
 * Er sieht nur den Anfang, meldet ihn als fehlend und die fuenf echten
 * Schluessel als unbenutzt - und waere einer davon wirklich nicht
 * uebersetzt, faende es niemand.
 *
 * Ausgeschrieben findet der Pruefer jeden einzelnen. Der Preis ist
 * eine Zeile je Fall, und die ist ohnehin fuenf Zeichen laenger als
 * die Zusammensetzung.
 */
final class Texts
{
    /** Warum gerade nicht gewuenscht werden darf. */
    public static function denied(string $grund): string
    {
        return match ($grund) {
            'login'    => translate('music.public.denied.login'),
            'banned'   => translate('music.public.denied.banned'),
            'rules'    => translate('music.public.denied.rules'),
            'off'      => translate('music.public.denied.off'),
            'cooldown' => translate('music.public.denied.cooldown'),
            default    => translate('music.public.denied.off'),
        };
    }

    /** Woran die Sperre lag. */
    public static function banned(string $art, string $name): string
    {
        return match ($art) {
            'artist' => translate('music.public.banned.artist', ['name' => $name]),
            'genre'  => translate('music.public.banned.genre', ['name' => $name]),
            default  => translate('music.public.banned.track', ['name' => $name]),
        };
    }

    /**
     * Der Platzhalter im Eingabefeld der Bannliste.
     *
     * Vier Arten, vier Saetze - und die zwei fuer Titel und Interpret
     * nennen einen LINK, denn eine Spotify-Kennung tippt niemand ab.
     */
    public static function banPlaceholder(string $art): string
    {
        return match ($art) {
            'track'  => translate('music.ban.add_track'),
            'artist' => translate('music.ban.add_artist'),
            'twitch' => translate('music.ban.add_twitch'),
            default  => translate('music.ban.add_genre'),
        };
    }
}
