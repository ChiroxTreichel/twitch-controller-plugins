<?php

declare(strict_types=1);

namespace TwitchController\Plugin\BlurChats;

use TwitchController\Core\App;

/**
 * Ein Screenshot je Kategorie - nur als Hilfe im Editor.
 *
 * Er ist nie im Overlay zu sehen. Man legt ihn unter die Flaeche, um
 * den Ingame-Chat genau zu treffen, statt Pixel zu raten.
 *
 * Angenommen wird nur JPEG, und das ist keine Einschraenkung fuer den
 * Benutzer: der Browser verkleinert jedes Bild vorher auf die Breite
 * der Buehne und schickt es als JPEG (blur-chats.js). Ein Screenshot
 * in 1080p als PNG waere mehrere Megabyte gross, als JPEG ein paar
 * hundert Kilobyte - und fuer eine Positionierhilfe reicht das.
 *
 * In der Datenbank als BYTEA. Hinein und heraus geht es als Base64
 * ueber decode()/encode() in Postgres: Db::run() bindet alles als
 * Zeichenkette, und rohe Bytes darin lehnt Postgres als ungueltiges
 * UTF-8 ab.
 */
final class Screens
{
    /** Hoechstens so viele Bytes, nach dem Dekodieren. */
    public const MAX_BYTES = 3 * 1024 * 1024;

    private const PREFIX = 'data:image/jpeg;base64,';

    /**
     * Pruefen, ohne zu speichern. Rein, ohne Datenbank.
     *
     * @return array{0: string, 1: string} Fehler (leer = in Ordnung) und die Bytes des Bildes
     */
    public static function validate(string $dataUrl): array
    {
        $dataUrl = trim($dataUrl);

        if ($dataUrl === '' || $dataUrl === self::PREFIX) {
            return [translate('blur_chats.error.screen_empty'), ''];
        }

        if (!str_starts_with($dataUrl, self::PREFIX)) {
            return [translate('blur_chats.error.screen_type'), ''];
        }

        $base64 = substr($dataUrl, strlen(self::PREFIX));

        // Vor dem Dekodieren schaetzen: vier Zeichen sind drei Bytes.
        // Ein Bild weit ueber der Grenze soll nicht erst ganz im
        // Speicher landen, nur um dann abgelehnt zu werden.
        if (intdiv(strlen($base64), 4) * 3 > self::MAX_BYTES + 3) {
            return [translate('blur_chats.error.screen_big', ['max' => self::maxLabel()]), ''];
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false || $bytes === '') {
            return [translate('blur_chats.error.screen_empty'), ''];
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            return [translate('blur_chats.error.screen_big', ['max' => self::maxLabel()]), ''];
        }

        // Die ersten drei Bytes jedes JPEG. Der Typ in der Data-URL ist
        // nur eine Behauptung des Browsers.
        if (!str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return [translate('blur_chats.error.screen_type'), ''];
        }

        return ['', $bytes];
    }

    /**
     * Pruefen und speichern. Ein neuer Screenshot ersetzt den alten.
     *
     * @return string leer bei Erfolg, sonst der Grund
     */
    public static function store(App $app, string $categoryId, string $dataUrl, string $categoryName = ''): string
    {
        if ($categoryId === '') {
            return translate('blur_chats.error.no_category');
        }

        [$fehler, $bytes] = self::validate($dataUrl);

        if ($fehler !== '') {
            return $fehler;
        }

        // Der Name, damit die Kategorie auch ohne Bereiche in der Liste
        // steht. Kommt keiner mit, gilt der aus den Bereichen.
        if (trim($categoryName) === '') {
            $categoryName = (string) $app->db->value(
                'SELECT category_name FROM blur_chats_regions WHERE category_id = :id LIMIT 1',
                ['id' => $categoryId]
            );
        }

        $app->db->run(
            'INSERT INTO blur_chats_screens (category_id, category_name, image, updated_at)
             VALUES (:id, :name, decode(:image, \'base64\'), now())
             ON CONFLICT (category_id) DO UPDATE
                SET image = EXCLUDED.image,
                    category_name = CASE WHEN EXCLUDED.category_name <> \'\'
                                         THEN EXCLUDED.category_name
                                         ELSE blur_chats_screens.category_name END,
                    updated_at = now()',
            ['id' => $categoryId, 'name' => trim($categoryName), 'image' => base64_encode($bytes)]
        );

        return '';
    }

    /** Die Bytes des Bildes, oder null, wenn es keines gibt. */
    public static function get(App $app, string $categoryId): ?string
    {
        $base64 = $app->db->value(
            'SELECT encode(image, \'base64\') FROM blur_chats_screens WHERE category_id = :id',
            ['id' => $categoryId]
        );

        if (!is_string($base64) || $base64 === '') {
            return null;
        }

        // Nicht streng: Postgres bricht Base64 alle 76 Zeichen um, und
        // der strenge Modus nimmt den Zeilenumbruch nicht.
        $bytes = base64_decode($base64);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }

    public static function has(App $app, string $categoryId): bool
    {
        return $categoryId !== '' && $app->db->value(
            'SELECT 1 FROM blur_chats_screens WHERE category_id = :id',
            ['id' => $categoryId]
        ) !== null;
    }

    /**
     * Wann der Screenshot zuletzt ersetzt wurde - als Stempel fuer die
     * Bildadresse, damit der Browser nach dem Ersetzen nicht das alte
     * aus seinem Speicher zeigt.
     */
    public static function stamp(App $app, string $categoryId): int
    {
        return (int) $app->db->value(
            'SELECT EXTRACT(EPOCH FROM updated_at)::BIGINT FROM blur_chats_screens WHERE category_id = :id',
            ['id' => $categoryId]
        );
    }

    public static function remove(App $app, string $categoryId): void
    {
        $app->db->run('DELETE FROM blur_chats_screens WHERE category_id = :id', ['id' => $categoryId]);
    }

    /** Die Grenze zum Vorlesen: "3 MB". */
    private static function maxLabel(): string
    {
        return (string) intdiv(self::MAX_BYTES, 1024 * 1024) . ' MB';
    }
}
