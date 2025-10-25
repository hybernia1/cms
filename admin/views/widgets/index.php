<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<int,array{id:string,name:string,description:string,areas:array<int,string>,render:callable,active:bool,meta:array<string,mixed>}> $widgets */
/** @var array<string,mixed>|null $selectedWidget */

$selected = $selectedWidget;
if ($selected === null && $widgets !== []) {
    $selected = $widgets[0];
}

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($widgets, $selected) {
    $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Seznam widgetů</span>
                <span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"><?= count($widgets); ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($widgets as $widget): ?>
                    <?php
                        $isSelected = $selected && $selected['id'] === $widget['id'];
                        $isActive = !empty($widget['active']);
                        $statusClass = $isActive
                            ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle'
                            : 'text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
                        $statusLabel = $isActive ? 'Aktivní' : 'Neaktivní';
                        $areas = implode(', ', array_map(static fn ($area) => (string)$area, $widget['areas']));
                    ?>
                    <a href="admin.php?r=widgets&amp;widget=<?= $h($widget['id']); ?>" class="list-group-item list-group-item-action<?= $isSelected ? ' active' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold"><?= $h($widget['name']); ?></div>
                                <div class="small text-muted">Oblasti: <?= $areas !== '' ? $h($areas) : '—'; ?></div>
                            </div>
                            <span class="badge <?= $statusClass; ?>"><?= $statusLabel; ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if ($widgets === []): ?>
                    <div class="list-group-item text-muted">
                        Žádné widgety nebyly nalezeny ve složce <code>/widgets</code>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if ($selected): ?>
            <?php
                $isActive = !empty($selected['active']);
                $description = (string)($selected['description'] ?? '');
                $areas = $selected['areas'] ?? [];
                $areaLabels = [];
                foreach ($areas as $area) {
                    $areaLabels[] = $area !== '' ? $area : 'sidebar';
                }
                $areaDisplay = $areaLabels !== [] ? implode(', ', $areaLabels) : 'sidebar';
                $formId = 'widget-settings-' . preg_replace('/[^a-z0-9\-]/', '-', $selected['id']);
            ?>
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-2">
                    <h2 class="h5 mb-0"><?= $h($selected['name']); ?></h2>
                    <span class="badge <?= $isActive ? 'text-bg-success-subtle text-success-emphasis border border-success-subtle' : 'text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle'; ?>">
                        <?= $isActive ? 'Aktivní' : 'Deaktivováno'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($description !== ''): ?>
                        <p class="mb-3"><?= nl2br($h($description)); ?></p>
                    <?php endif; ?>

                    <dl class="row mb-0">
                        <dt class="col-sm-4">ID widgetu</dt>
                        <dd class="col-sm-8"><code><?= $h($selected['id']); ?></code></dd>
                        <dt class="col-sm-4">Oblasti</dt>
                        <dd class="col-sm-8"><?= $h($areaDisplay); ?></dd>
                    </dl>

                    <hr class="my-4">

                    <form id="<?= $h($formId); ?>" method="post" action="admin.php?r=widgets&amp;a=toggle" class="vstack gap-3">
                        <input type="hidden" name="csrf" value="<?= $h((string)($csrf ?? '')); ?>">
                        <input type="hidden" name="widget" value="<?= $h($selected['id']); ?>">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="<?= $h($formId); ?>-active" name="active" value="1"<?= $isActive ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="<?= $h($formId); ?>-active">Widget je aktivní</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Uložit</button>
                            <?php if (!$isActive): ?>
                                <span class="text-muted align-self-center">Widget je aktuálně deaktivován.</span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Přidejte widgety do složky <code>/widgets</code>, abyste je mohli spravovat.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
});
