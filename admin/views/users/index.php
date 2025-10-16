<?php
declare(strict_types=1);
/** @var array $data */
/** @var string $q */

$this->render('layouts/base', compact('pageTitle','nav','currentUser'), function() use ($data,$q,$csrf) {
  $h = fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <form class="d-flex" method="get" action="admin.php">
    <input type="hidden" name="r" value="users">
    <input class="form-control me-2" name="q" value="<?= $h($q) ?>" placeholder="Hledat jméno/e-mail">
    <button class="btn btn-outline-light">Hledat</button>
  </form>
  <a class="btn btn-primary" href="admin.php?r=users&a=edit">Nový uživatel</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-dark table-hover mb-0">
      <thead><tr><th>ID</th><th>Jméno</th><th>E-mail</th><th>Role</th><th>Aktivní</th><th>Vytvořeno</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($data['items'] as $u): ?>
        <tr>
          <td><?= (int)$u['id'] ?></td>
          <td><?= $h($u['name']) ?></td>
          <td><?= $h($u['email']) ?></td>
          <td><span class="badge text-bg-<?= $u['role']==='admin'?'warning':'secondary' ?>"><?= $h($u['role']) ?></span></td>
          <td><?= (int)$u['active']===1?'✅':'❌' ?></td>
          <td title="<?= $h((string)($u['created_at_raw'] ?? '')) ?>"><?= $h((string)($u['created_at_display'] ?? ($u['created_at_raw'] ?? ''))) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-light" href="admin.php?r=users&a=edit&id=<?= (int)$u['id'] ?>">Upravit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data['items']): ?>
        <tr><td colspan="7" class="text-center text-secondary py-4">Nic nenalezeno</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($data['pages']>1): ?>
  <div class="card-footer">
    <?php for ($p=1;$p<=$data['pages'];$p++): ?>
      <a class="btn btn-sm <?= $p===$data['page']?'btn-primary':'btn-outline-light' ?>" href="admin.php?r=users&page=<?= $p ?>&q=<?= urlencode($q) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php }); ?>
