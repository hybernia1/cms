<?php
declare(strict_types=1);
/** @var array|null $user */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function() use ($user,$csrf) {
  $h = fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
  $sel = fn($a,$b)=>$a===$b?' selected':'';
?>
<form class="card" method="post" action="admin.php?r=users&a=save">
  <div class="card-header"><?= $user?'Upravit':'Nový' ?> uživatel</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Jméno</label>
        <input class="form-control" name="name" value="<?= $h((string)($user['name'] ?? '')) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">E-mail</label>
        <input class="form-control" name="email" type="email" value="<?= $h((string)($user['email'] ?? '')) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Role</label>
        <select class="form-select" name="role">
          <option value="user"<?= $sel('user', (string)($user['role'] ?? 'user')) ?>>user</option>
          <option value="admin"<?= $sel('admin', (string)($user['role'] ?? 'user')) ?>>admin</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Aktivní</label>
        <select class="form-select" name="active">
          <option value="1"<?= ((int)($user['active'] ?? 1)===1)?' selected':''; ?>>Ano</option>
          <option value="0"<?= ((int)($user['active'] ?? 1)===0)?' selected':''; ?>>Ne</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Heslo (nechte prázdné pro beze změny)</label>
        <input class="form-control" type="password" name="password">
      </div>
    </div>
  </div>
  <div class="card-footer">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <?php if ($user): ?><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><?php endif; ?>
    <button class="btn btn-primary" type="submit">Uložit</button>
  </div>
</form>
<?php }); ?>
