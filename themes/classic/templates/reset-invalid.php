<?php
$this->part('auth', 'card', [
    'title' => 'Neplatný odkaz',
    'type'  => 'danger',
    'msg'   => null,
    'body'  => static function (): void {
        ?>
        <p class="muted">Odkaz pro změnu hesla je neplatný nebo vypršel. Vyžádejte si prosím nové heslo.</p>
        <?php
    },
]);
