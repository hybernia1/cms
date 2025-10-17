<?php
ob_start();
?>
<p>Pokud účet existuje, během několika minut dorazí e-mail s dalšími kroky. Zkontrolujte i složku SPAM.</p>
<?php
$body = ob_get_clean();
$this->part('auth', 'card', [
    'title' => 'Instrukce odeslány',
    'type'  => 'success',
    'msg'   => null,
    'body'  => $body,
]);
