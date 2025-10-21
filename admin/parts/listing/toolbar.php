<?php
declare(strict_types=1);

/**
 * @var array<int,array{label:string,href:string,active:bool,count:int|null}> $tabs
 * @var array<string,mixed>|null $search
 * @var array<string,mixed>|null $button
 * @var string|null $containerClasses
 * @var string|null $tabsClass
 */

$tabs = $tabs ?? [];
$search = $search ?? null;
$button = $button ?? null;
$containerClasses = $containerClasses ?? 'd-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 mb-3';
$tabsClass = $tabsClass ?? 'order-2 order-md-1';

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="<?= $h($containerClasses) ?>">
  <?php if ($tabs !== []): ?>
    <nav aria-label="Filtr" class="<?= $h($tabsClass) ?>">
      <ul class="nav nav-pills nav-sm">
        <?php foreach ($tabs as $tab): ?>
          <li class="nav-item">
            <a class="nav-link px-3 py-1 <?= !empty($tab['active']) ? 'active' : '' ?>" href="<?= $h((string)$tab['href']) ?>">
              <?= $h((string)$tab['label']) ?>
              <?php if (array_key_exists('count', $tab) && $tab['count'] !== null): ?>
                <span class="ms-1 badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">
                  <?= $h((string)(int)$tab['count']) ?>
                </span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  <?php endif; ?>

  <?php if (is_array($search)): ?>
    <?php
      $searchAction      = (string)($search['action'] ?? 'admin.php');
      $searchMethod      = strtolower((string)($search['method'] ?? 'get')) === 'post' ? 'post' : 'get';
      $searchWrapper     = (string)($search['wrapperClass'] ?? 'order-1 order-md-2 ms-md-auto');
      $searchName        = (string)($search['name'] ?? 'q');
      $searchValue       = (string)($search['value'] ?? '');
      $searchPlaceholder = (string)($search['placeholder'] ?? '');
      $hiddenInputs      = is_array($search['hidden'] ?? null) ? $search['hidden'] : [];
      $resetHref         = (string)($search['resetHref'] ?? '');
      $resetDisabled     = (bool)($search['resetDisabled'] ?? ($searchValue === ''));
      $inputGroupClass   = (string)($search['inputGroupClass'] ?? 'input-group input-group-sm');
      $searchTooltip     = (string)($search['searchTooltip'] ?? 'Hledat');
      $clearTooltip      = (string)($search['clearTooltip'] ?? 'Zrušit filtr');
      $ariaLabel         = (string)($search['ariaLabel'] ?? 'Hledat');
    ?>
    <form class="<?= $h($searchWrapper) ?>" method="<?= $h($searchMethod) ?>" action="<?= $h($searchAction) ?>" role="search" data-ajax>
      <?php foreach ($hiddenInputs as $name => $value): ?>
        <input type="hidden" name="<?= $h((string)$name) ?>" value="<?= $h((string)$value) ?>">
      <?php endforeach; ?>
      <div class="<?= $h($inputGroupClass) ?>" style="min-width:260px;">
        <input class="form-control" name="<?= $h($searchName) ?>" placeholder="<?= $h($searchPlaceholder) ?>" value="<?= $h($searchValue) ?>">
        <button class="btn btn-outline-secondary" type="submit" aria-label="<?= $h($ariaLabel) ?>" data-bs-toggle="tooltip" data-bs-title="<?= $h($searchTooltip) ?>">
          <i class="bi bi-search"></i>
        </button>
        <a class="btn btn-outline-secondary <?= $resetDisabled ? 'disabled' : '' ?>"
           href="<?= $h($resetHref) ?>"
           aria-label="<?= $h($clearTooltip) ?>"
           data-bs-toggle="tooltip"
           data-bs-title="<?= $h($clearTooltip) ?>">
          <i class="bi bi-x-circle"></i>
        </a>
      </div>
    </form>
  <?php endif; ?>

  <?php if (is_array($button)): ?>
    <?php
      $buttonHref  = (string)($button['href'] ?? '#');
      $buttonLabel = (string)($button['label'] ?? '');
      $buttonClass = trim((string)($button['class'] ?? 'btn btn-primary'));
      $buttonIcon  = (string)($button['icon'] ?? '');
      $buttonAttributes = is_array($button['attributes'] ?? null) ? $button['attributes'] : [];
      if ($buttonClass === '') {
          $buttonClass = 'btn btn-primary';
      }
      if (strpos($buttonClass, 'admin-toolbar-action') === false) {
          $buttonClass .= ' admin-toolbar-action';
      }
      if ($buttonLabel !== '' && !array_key_exists('aria-label', $buttonAttributes)) {
          $buttonAttributes['aria-label'] = $buttonLabel;
      }
    ?>
    <a class="<?= $h($buttonClass) ?>" href="<?= $h($buttonHref) ?>"<?php foreach ($buttonAttributes as $attr => $value): if (!is_string($attr) || $attr === '' || $value === null || $value === false) { continue; } ?> <?= $h($attr) ?><?php if ($value !== true): ?>="<?= $h((string)$value) ?>"<?php endif; ?><?php endforeach; ?>>
      <?php if ($buttonIcon !== ''): ?>
        <i class="<?= $h($buttonIcon) ?>" aria-hidden="true"></i>
      <?php endif; ?>
      <?php if ($buttonLabel !== ''): ?>
        <span class="admin-toolbar-action-label"><?= $h($buttonLabel) ?></span>
      <?php endif; ?>
    </a>
  <?php endif; ?>
</div>
