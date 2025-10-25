<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var string $csrf */
/** @var array<int,array{slug:string,name:string,description:string,version:string,author:string,homepage:?string,admin_url:?string,active:bool,meta:array<string,mixed>}> $plugins */
/** @var array<string,mixed>|null $selectedPlugin */

$selected = $selectedPlugin;
if ($selected === null && $plugins !== []) {
    $selected = $plugins[0];
}

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($plugins, $selected, $csrf) {
    $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<?php if ($selected): ?>
            <?php
                $isActive = !empty($selected['active']);
                $configured = !empty($selected['meta']['configured']);
                $measurementId = (string)($selected['meta']['measurement_id'] ?? '');
                $hint = (string)($selected['meta']['configuration_hint'] ?? '');
                $description = (string)($selected['description'] ?? '');
                $version = (string)($selected['version'] ?? '');
                $author = (string)($selected['author'] ?? '');
                $homepage = isset($selected['homepage']) ? (string)$selected['homepage'] : '';
                $adminUrl = isset($selected['admin_url']) ? (string)$selected['admin_url'] : '';
                $formId = 'plugin-settings-' . preg_replace('/[^a-z0-9\-]/', '-', $selected['slug']);
            ?>
    <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h5 mb-0"><?= $h($selected['name']); ?></h2>
                    <?php if ($version !== ''): ?>
                        <span class="badge text-bg-light border">v<?= $h($version); ?></span>
                    <?php endif; ?>
                    <span class="badge <?= $isActive ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle' : 'text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle'; ?>">
                        <?= $isActive ? 'Aktivní' : 'Deaktivováno'; ?>
                    </span>
                    <span class="badge <?= $configured ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle' : 'text-bg-warning-subtle text-warning-emphasis border border-warning-subtle'; ?>">
                        <?= $configured ? 'Konfigurováno' : 'Vyžaduje nastavení'; ?>
                    </span>
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
                        <dt class="col-sm-4">Stav pluginu</dt>
                        <dd class="col-sm-8">
                            <?= $isActive ? 'Aktivní' : 'Deaktivováno'; ?>
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

                    <hr class="my-4">

                    <form id="<?= $h($formId); ?>" method="post" action="admin.php?r=plugins&amp;a=update" class="vstack gap-3">
                        <input type="hidden" name="csrf" value="<?= $h($csrf); ?>">
                        <input type="hidden" name="plugin" value="<?= $h($selected['slug']); ?>">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="<?= $h($formId); ?>-active" name="active" value="1"<?= $isActive ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="<?= $h($formId); ?>-active">Plugin je aktivní</label>
                        </div>

                        <?php if ($selected['slug'] === 'google-analytics'): ?>
                            <?php
                                $inputId = $formId . '-measurement-id';
                            ?>
                            <div>
                                <label class="form-label" for="<?= $h($inputId); ?>">Měřicí ID Google Analytics</label>
                                <input type="text" class="form-control" id="<?= $h($inputId); ?>" name="measurement_id" value="<?= $h($measurementId); ?>" placeholder="např. G-XXXXXXXXXX">
                                <div class="form-text">Pokud pole ponecháte prázdné, použije se hodnota z proměnné <code>CMS_GA_MEASUREMENT_ID</code> (pokud je nastavena).</div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Uložit změny</button>
                            <?php if (!$isActive): ?>
                                <span class="text-muted align-self-center">Plugin je aktuálně deaktivován.</span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php if ($hint !== ''): ?>
                    <div class="card-footer text-muted small">
                        <?= nl2br($h($hint)); ?>
                    </div>
                <?php endif; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <?php if ($plugins === []): ?>
            Přidejte pluginy do složky <code>/plugins</code>, abyste je mohli spravovat.
        <?php else: ?>
            Vyberte plugin z nabídky v levém menu.
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php
});
