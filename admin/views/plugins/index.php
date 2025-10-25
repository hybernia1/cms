<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array{slug:string,name:string,description:string,version:string,author:string,homepage:?string,admin_url:?string,active:bool,meta:array<string,mixed>}> $plugins */
/** @var array<string,mixed>|null $selectedPlugin */

$selected = $selectedPlugin;
if ($selected === null && $plugins !== []) {
    $selected = $plugins[0];
}

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($plugins, $selected) {
    $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Seznam pluginů</span>
                <span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"><?= count($plugins); ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($plugins as $plugin): ?>
                    <?php
                        $isSelected = $selected && $selected['slug'] === $plugin['slug'];
                        $configured = !empty($plugin['meta']['configured']);
                        $badgeClass = $configured ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle' : 'text-bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                        $badgeLabel = $configured ? 'Nastaveno' : 'Vyžaduje nastavení';
                    ?>
                    <a href="admin.php?r=plugins&amp;plugin=<?= $h($plugin['slug']); ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= $isSelected ? ' active' : ''; ?>">
                        <span><?= $h($plugin['name']); ?></span>
                        <span class="badge <?= $badgeClass; ?>" title="<?= $configured ? 'Plugin má nastavené potřebné údaje.' : 'Plugin může vyžadovat dodatečné nastavení.'; ?>">
                            <?= $badgeLabel; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
                <?php if ($plugins === []): ?>
                    <div class="list-group-item text-muted">
                        Žádné pluginy nebyly nalezeny ve složce <code>/plugins</code>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if ($selected): ?>
            <?php
                $configured = !empty($selected['meta']['configured']);
                $measurementId = (string)($selected['meta']['measurement_id'] ?? '');
                $hint = (string)($selected['meta']['configuration_hint'] ?? '');
                $description = (string)($selected['description'] ?? '');
                $version = (string)($selected['version'] ?? '');
                $author = (string)($selected['author'] ?? '');
                $homepage = isset($selected['homepage']) ? (string)$selected['homepage'] : '';
                $adminUrl = isset($selected['admin_url']) ? (string)$selected['admin_url'] : '';
            ?>
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h5 mb-0"><?= $h($selected['name']); ?></h2>
                    <?php if ($version !== ''): ?>
                        <span class="badge text-bg-light border">v<?= $h($version); ?></span>
                    <?php endif; ?>
                    <?php if ($configured): ?>
                        <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">Aktivní</span>
                    <?php else: ?>
                        <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle">Čeká na konfiguraci</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($description !== ''): ?>
                        <p class="mb-3"><?= nl2br($h($description)); ?></p>
                    <?php endif; ?>

                    <dl class="row mb-0">
                        <dt class="col-sm-4">Autor</dt>
                        <dd class="col-sm-8">
                            <?= $author !== '' ? $h($author) : '<span class="text-muted">Neuveden</span>'; ?>
                        </dd>
                        <dt class="col-sm-4">Stav konfigurace</dt>
                        <dd class="col-sm-8">
                            <?= $configured ? 'Konfigurováno' : 'Vyžaduje doplnění údajů'; ?>
                        </dd>
                        <?php if ($measurementId !== ''): ?>
                            <dt class="col-sm-4">Měřicí ID</dt>
                            <dd class="col-sm-8"><code><?= $h($measurementId); ?></code></dd>
                        <?php endif; ?>
                        <?php if ($homepage !== ''): ?>
                            <dt class="col-sm-4">Odkaz</dt>
                            <dd class="col-sm-8"><a href="<?= $h($homepage); ?>" target="_blank" rel="noopener"><?= $h($homepage); ?></a></dd>
                        <?php endif; ?>
                        <?php if ($adminUrl !== ''): ?>
                            <dt class="col-sm-4">Nastavení</dt>
                            <dd class="col-sm-8"><a href="<?= $h($adminUrl); ?>">Otevřít stránku pluginu</a></dd>
                        <?php endif; ?>
                    </dl>
                </div>
                <?php if ($hint !== ''): ?>
                    <div class="card-footer text-muted small">
                        <?= nl2br($h($hint)); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Přidejte pluginy do složky <code>/plugins</code>, abyste je mohli spravovat.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
});
