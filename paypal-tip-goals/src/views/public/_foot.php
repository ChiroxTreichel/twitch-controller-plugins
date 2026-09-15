<?php
/**
 * Der Fuss jeder oeffentlichen Seite.
 *
 * Der Satz darueber stand im alten System genauso da und ist der
 * eigentliche Grund der Seite: das Geld geht direkt an den Streamer.
 *
 * Verlinkt werden NUR die Texte, die es wirklich gibt: ein Link auf
 * eine leere Seite ist schlechter als kein Link. Das Impressum ist
 * immer dabei - ohne das waere die Seite gar nicht online.
 *
 * @var callable $e
 * @var callable $url
 * @var string $brand
 * @var list<string> $legal
 */

use TwitchController\Plugin\PaypalTipGoals\Legal;
?>
</main>

<footer class="page-footer">
    <p><?= $e(translate('pp_tip.public.footer', ['name' => $brand])) ?></p>

    <nav class="footer-links">
        <?php foreach ($legal as $schluessel): ?>
            <a href="<?= $e($url('/tips/' . $schluessel)) ?>">
                <?= $e(Legal::title($schluessel)) ?>
            </a>
        <?php endforeach ?>
    </nav>
</footer>
</body>
</html>
