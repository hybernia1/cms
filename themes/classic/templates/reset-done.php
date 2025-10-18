<?php
$this->part('auth', 'card', [
    'title' => 'Heslo aktualizováno',
    'type'  => 'success',
    'msg'   => null,
    'body'  => static function (): void {
        ?>
        <p>Heslo bylo změněno. Můžete se nyní přihlásit s novými přihlašovacími údaji.</p>
        <?php
    },
]);
