<?php
$this->part('auth', 'card', [
    'title' => 'Instrukce odeslány',
    'type'  => 'success',
    'msg'   => null,
    'body'  => static function (): void {
        ?>
        <p>Pokud účet existuje, během několika minut dorazí e-mail s dalšími kroky. Zkontrolujte i složku SPAM.</p>
        <?php
    },
]);
