<?php /** @var string $siteTitle */ ?>
<header class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;">
    <h1 style="margin:0"><?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    <nav class="nav">
      <a href="./">Domů</a>
      <a href="./type/post">Blog</a>
      <a href="./type/page">Stránky</a>
      <a href="./terms">Termy</a>
    </nav>
  </div>
</header>
