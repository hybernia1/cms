<?php
ob_start();
?>
<p class="muted">Registrace nových uživatelů je aktuálně vypnutá. Zkuste to prosím později nebo kontaktujte správce webu.</p>
<?php
$body = ob_get_clean();
$this->part('auth', 'card', [
    'title' => 'Registrace nedostupná',
    'type'  => 'info',
    'msg'   => null,
    'body'  => $body,
]);
