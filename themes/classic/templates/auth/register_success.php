<?php
declare(strict_types=1);
/** @var string $email */
$this->render('layouts/base', compact('assets','siteTitle'), function() use ($email) {
  $h = fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
?><div class="alert alert-success">Účet byl vytvořen. Můžete se přihlásit jako <strong><?= $h($email) ?></strong>.</div><?php }); ?>
