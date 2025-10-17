<?php
ob_start();
?>
<p class="muted">Odkaz pro změnu hesla je neplatný nebo vypršel. Vyžádejte si prosím nové heslo.</p>
<?php
$body = ob_get_clean();
$this->part('auth', 'card', [
    'title' => 'Neplatný odkaz',
    'type'  => 'danger',
    'msg'   => null,
    'body'  => $body,
]);
