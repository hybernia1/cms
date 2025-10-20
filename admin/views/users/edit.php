<?php
declare(strict_types=1);
/** @var array|null $user */
/** @var array<int,array{key:string,label:string}> $mailTemplates */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function() use ($user,$csrf,$mailTemplates) {
  $h = fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
  $sel = fn($a,$b)=>$a===$b?' selected':'';
?>
<form class="card" method="post" action="admin.php?r=users&a=save" data-ajax data-form-helper="validation">
  <div class="card-header"><?= $user?'Upravit':'Nový' ?> uživatel</div>
  <div class="card-body">
    <div class="alert alert-danger mb-3" data-error-for="form" hidden></div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="user-name">Jméno</label>
        <input class="form-control" id="user-name" name="name" value="<?= $h((string)($user['name'] ?? '')) ?>" required>
        <div class="invalid-feedback" data-error-for="name" hidden></div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="user-email">E-mail</label>
        <input class="form-control" id="user-email" name="email" type="email" value="<?= $h((string)($user['email'] ?? '')) ?>" required>
        <div class="invalid-feedback" data-error-for="email" hidden></div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="user-role">Role</label>
        <select class="form-select" id="user-role" name="role">
          <option value="user"<?= $sel('user', (string)($user['role'] ?? 'user')) ?>>user</option>
          <option value="admin"<?= $sel('admin', (string)($user['role'] ?? 'user')) ?>>admin</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="user-active">Aktivní</label>
        <select class="form-select" id="user-active" name="active">
          <option value="1"<?= ((int)($user['active'] ?? 1)===1)?' selected':''; ?>>Ano</option>
          <option value="0"<?= ((int)($user['active'] ?? 1)===0)?' selected':''; ?>>Ne</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="user-password">Heslo (nechte prázdné pro beze změny)</label>
        <input class="form-control" type="password" id="user-password" name="password">
        <div class="invalid-feedback" data-error-for="password" hidden></div>
      </div>
    </div>
  </div>
  <div class="card-footer">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <?php if ($user): ?><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><?php endif; ?>
    <button class="btn btn-primary" type="submit">Uložit</button>
  </div>
</form>
<?php if ($user): ?>
  <div class="card mt-3">
    <div class="card-header">Odeslat e-mail uživateli</div>
    <?php if ($mailTemplates): ?>
      <form class="card-body row gy-2 gx-2 align-items-end" method="post" action="admin.php?r=users&a=send-template" data-ajax data-form-helper="validation">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
        <div class="col-12">
          <div class="alert alert-danger mb-3" data-error-for="form" hidden></div>
        </div>
        <div class="col-md-8">
          <label class="form-label" for="mail-template">Šablona e-mailu</label>
          <select class="form-select" name="template" id="mail-template" required>
            <option value="">-- Vyberte šablonu --</option>
            <?php foreach ($mailTemplates as $tpl): ?>
              <option value="<?= $h((string)$tpl['key']) ?>"><?= $h((string)$tpl['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback" data-error-for="template" hidden></div>
          <div class="form-text">Odešle na adresu <?= $h((string)($user['email'] ?? '')) ?>.</div>
        </div>
        <div class="col-md-4 text-md-end">
          <button class="btn btn-outline-primary mt-3 mt-md-0" type="submit">
            <i class="bi bi-send me-1"></i>Odeslat e-mail
          </button>
        </div>
      </form>
    <?php else: ?>
      <div class="card-body text-secondary">Žádné e-mailové šablony nejsou k dispozici.</div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php }); ?>
