<?php
$this->part('auth', 'card', [
    'title' => 'Registrace nedostupná',
    'type'  => 'info',
    'msg'   => null,
    'body'  => static function (): void {
        ?>
        <p class="muted">Registrace nových uživatelů je aktuálně vypnutá. Zkuste to prosím později nebo kontaktujte správce webu.</p>
        <?php
    },
]);
