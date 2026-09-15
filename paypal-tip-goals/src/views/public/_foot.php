<?php
/**
 * Der Fuss jeder oeffentlichen Seite.
 *
 * Verlinkt werden NUR die Texte, die es wirklich gibt: ein Link auf
 * eine leere Seite ist schlechter als kein Link. Das Impressum ist
 * immer dabei - ohne das waere die Seite gar nicht online.
 *
 * @var callable $e
 * @var callable $url
 * @var list<string> $legal
 */

use TwitchController\Plugin\PaypalTipGoals\Legal;
?>
</main>

<footer class="tp-foot">
    <?php foreach ($legal as $schluessel): ?>
        <a href="<?= $e($url('/tips/' . $schluessel)) ?>">
            <?= $e(Legal::title($schluessel)) ?>
        </a>
    <?php endforeach ?>
</footer>
</body>
</html>
