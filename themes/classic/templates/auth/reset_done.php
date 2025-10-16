<?php
ob_start();
?>
<p>Heslo bylo změněno. Můžete se nyní přihlásit s novými přihlašovacími údaji.</p>
<?php
$body = ob_get_clean();
$this->part('parts/auth/card', [
    'title' => 'Heslo aktualizováno',
    'type'  => 'success',
    'msg'   => null,
    'body'  => $body,
]);
