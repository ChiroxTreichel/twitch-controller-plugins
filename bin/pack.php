#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Packt ein Plugin aus <slug>/src/ nach <slug>/<slug>.zip.
 *
 *   php bin/pack.php example        ein Plugin
 *   php bin/pack.php --all          alle
 *
 * Aufbau, den dieses Repository vorgibt:
 *
 *   <slug>/
 *       src/          der Quellcode - hier wird entwickelt
 *       plugin.json   Manifest, aus src/plugin.json herausgeschrieben
 *       README.md     Langtext fuer die Beschreibungsseite im Marktplatz
 *       <slug>.zip    das Paket, aus src/ gebaut
 *
 * Der Inhalt von src/ landet in der Wurzel des Archivs, nicht in einem
 * Unterordner: der Installer erwartet plugin.json und plugin.php dort.
 *
 * plugin.json wird bei jedem Packen aus src/ herauskopiert. So kann sie
 * nicht auseinanderlaufen - im Katalog stand sonst irgendwann eine
 * Version, die im Paket nicht drin ist.
 */

$root = dirname(__DIR__);

$slugs = [];
$alle = false;

for ($i = 1; $i < $argc; $i++) {
    $arg = (string) $argv[$i];

    if ($arg === '--all') {
        $alle = true;
        continue;
    }

    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "php bin/pack.php <slug> | --all\n");
        exit(0);
    }

    $slugs[] = strtolower($arg);
}

if ($alle) {
    foreach (glob($root . '/*/src/plugin.json') ?: [] as $treffer) {
        $slugs[] = basename(dirname(dirname($treffer)));
    }
}

if ($slugs === []) {
    fwrite(STDERR, "Kein Plugin angegeben. php bin/pack.php <slug> | --all\n");
    exit(1);
}

$fehler = 0;

foreach (array_unique($slugs) as $slug) {
    try {
        packe($root, $slug);
    } catch (Throwable $e) {
        fwrite(STDERR, "  ! {$slug}: " . $e->getMessage() . "\n");
        $fehler++;
    }
}

exit($fehler === 0 ? 0 : 1);

function packe(string $root, string $slug): void
{
    $ordner = $root . '/' . $slug;
    $quelle = $ordner . '/src';
    $ziel = $ordner . '/' . $slug . '.zip';

    if (!is_dir($quelle)) {
        throw new RuntimeException("Kein src/ in {$slug}/");
    }

    $manifestDatei = $quelle . '/plugin.json';
    if (!is_file($manifestDatei)) {
        throw new RuntimeException('src/plugin.json fehlt');
    }

    $manifest = json_decode((string) file_get_contents($manifestDatei), true);
    if (!is_array($manifest)) {
        throw new RuntimeException('src/plugin.json ist kein gueltiges JSON');
    }

    // Der Slug muss zum Ordner passen, sonst entpackt der Installer das
    // Paket unter einem anderen Namen, als der Katalog anbietet.
    if (($manifest['slug'] ?? '') !== $slug) {
        throw new RuntimeException(sprintf(
            'Slug in src/plugin.json ist "%s", der Ordner heisst "%s"',
            (string) ($manifest['slug'] ?? ''),
            $slug
        ));
    }

    if (!is_file($quelle . '/plugin.php')) {
        throw new RuntimeException('src/plugin.php fehlt');
    }

    $version = (string) ($manifest['version'] ?? '');
    if ($version === '') {
        throw new RuntimeException('version fehlt in src/plugin.json');
    }

    $dateien = sammle($quelle);
    if ($dateien === []) {
        throw new RuntimeException('src/ ist leer');
    }

    @unlink($ziel);
    schreibeArchiv($quelle, $dateien, $ziel);

    // Manifest herausschreiben, damit der Katalog es lesen kann, ohne
    // das Archiv zu oeffnen.
    copy($manifestDatei, $ordner . '/plugin.json');

    if (!is_file($ordner . '/README.md')) {
        fwrite(STDOUT, "  (Hinweis: {$slug}/README.md fehlt - die Beschreibungsseite bleibt leer)\n");
    }

    printf(
        "%-16s %-10s %6.1f KB  %d Datei(en)  sha256:%s\n",
        $slug,
        $version,
        filesize($ziel) / 1024,
        count($dateien),
        substr(hash_file('sha256', $ziel), 0, 16)
    );
}

/**
 * Alle Dateien unter src/, relativ und sortiert. Sortiert, damit zwei
 * Laeufe dieselbe Reihenfolge im Archiv ergeben.
 *
 * @return list<string>
 */
function sammle(string $quelle): array
{
    $ignoriert = ['.git', '.gitignore', '.DS_Store', 'Thumbs.db', 'node_modules'];

    $gefunden = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($quelle, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        if (!$item->isFile()) {
            continue;
        }

        $relativ = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($quelle) + 1));

        foreach ($ignoriert as $muster) {
            if ($relativ === $muster || str_starts_with($relativ, $muster . '/') || str_contains($relativ, '/' . $muster . '/')) {
                continue 2;
            }
        }

        $gefunden[] = $relativ;
    }

    sort($gefunden);

    return $gefunden;
}

/**
 * @param list<string> $dateien
 */
function schreibeArchiv(string $quelle, array $dateien, string $ziel): void
{
    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open($ziel, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Konnte {$ziel} nicht anlegen");
        }

        foreach ($dateien as $relativ) {
            $zip->addFile($quelle . '/' . $relativ, $relativ);
        }

        $zip->close();

        return;
    }

    // Ohne ext-zip: ein vorhandenes Kommandozeilenwerkzeug benutzen.
    // Auf dem Server ist die Erweiterung da, auf einem
    // Entwicklungsrechner nicht immer.
    foreach ([['zip', '-q -X -r'], ['7z', 'a -tzip -bso0 -bsp0']] as [$befehl, $argumente]) {
        if (!werkzeugDa($befehl)) {
            continue;
        }

        $liste = implode(' ', array_map('escapeshellarg', $dateien));
        $zeile = sprintf(
            'cd %s && %s %s %s %s',
            escapeshellarg($quelle),
            escapeshellarg($befehl),
            $argumente,
            escapeshellarg($ziel),
            $liste
        );

        exec($zeile, $ausgabe, $code);

        if ($code === 0 && is_file($ziel)) {
            return;
        }
    }

    throw new RuntimeException(
        'Kein Packer vorhanden. Entweder die PHP-Erweiterung zip aktivieren '
        . 'oder zip bzw. 7z installieren.'
    );
}

function werkzeugDa(string $befehl): bool
{
    $pruefung = stripos(PHP_OS_FAMILY, 'win') === 0
        ? 'where ' . escapeshellarg($befehl)
        : 'command -v ' . escapeshellarg($befehl);

    exec($pruefung . ' 2>&1', $ausgabe, $code);

    return $code === 0;
}
