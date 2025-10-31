<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $nav */
/** @var array|null $currentUser */
/** @var array|null $flash */
/** @var array<string,mixed> $order */
/** @var array<int,array<string,mixed>> $items */
/** @var array<string,array<string,mixed>|null> $addresses */
/** @var array<int,string> $statuses */
/** @var string $csrf */

$this->render('parts/layouts/base', compact('pageTitle', 'nav', 'currentUser', 'flash'), function () use ($order, $items, $addresses, $statuses, $csrf) {
    $h = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $addressBlock = static function (?array $address) use ($h): string {
        if ($address === null) {
            return '<p class="text-muted">Nezadáno.</p>';
        }
        $lines = [];
        $fullName = trim(((string)($address['first_name'] ?? '')) . ' ' . ((string)($address['last_name'] ?? '')));
        if ($fullName !== '') {
            $lines[] = $h($fullName);
        }
        foreach (['company', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'] as $key) {
            $value = trim((string)($address[$key] ?? ''));
            if ($value !== '') {
                $lines[] = $h($value);
            }
        }
        if (!empty($address['phone'])) {
            $lines[] = 'Tel: ' . $h((string)$address['phone']);
        }
        if (!empty($address['email'])) {
            $lines[] = 'E-mail: ' . $h((string)$address['email']);
        }
        if ($lines === []) {
            return '<p class="text-muted">Nezadáno.</p>';
        }
        return '<p class="mb-0">' . implode('<br>', $lines) . '</p>';
    };
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Objednávka <?= $h((string)($order['order_number'] ?? '')) ?></h1>
    <a class="btn btn-outline-secondary" href="admin.php?r=orders">Zpět na přehled</a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title h5 mb-0">Položky objednávky</h2>
        </div>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Název</th>
                <th>SKU</th>
                <th class="text-end">Množství</th>
                <th class="text-end">Cena / ks</th>
                <th class="text-end">Celkem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td><?= $h((string)($item['name'] ?? '')) ?></td>
                  <td><?= $h((string)($item['sku'] ?? '')) ?></td>
                  <td class="text-end"><?= (int)($item['quantity'] ?? 0) ?></td>
                  <td class="text-end"><?= number_format((float)($item['unit_price'] ?? 0), 2, ',', ' ') ?></td>
                  <td class="text-end"><?= number_format((float)($item['total_price'] ?? 0), 2, ',', ' ') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-body">
          <h2 class="h6">Rekapitulace</h2>
          <dl class="row mb-0 small">
            <dt class="col-6 text-muted">Mezisoučet</dt>
            <dd class="col-6 text-end"><?= number_format((float)($order['subtotal'] ?? 0), 2, ',', ' ') ?> <?= $h((string)($order['currency'] ?? '')) ?></dd>
            <dt class="col-6 text-muted">Sleva</dt>
            <dd class="col-6 text-end"><?= number_format((float)($order['discount_total'] ?? 0), 2, ',', ' ') ?> <?= $h((string)($order['currency'] ?? '')) ?></dd>
            <dt class="col-6 text-muted">Daň</dt>
            <dd class="col-6 text-end"><?= number_format((float)($order['tax_total'] ?? 0), 2, ',', ' ') ?> <?= $h((string)($order['currency'] ?? '')) ?></dd>
            <dt class="col-6 text-muted">Doprava</dt>
            <dd class="col-6 text-end"><?= number_format((float)($order['shipping_total'] ?? 0), 2, ',', ' ') ?> <?= $h((string)($order['currency'] ?? '')) ?></dd>
            <dt class="col-6 text-muted fw-semibold">Celkem</dt>
            <dd class="col-6 text-end fw-semibold"><?= number_format((float)($order['total'] ?? 0), 2, ',', ' ') ?> <?= $h((string)($order['currency'] ?? '')) ?></dd>
          </dl>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h2 class="h6">Adresa fakturační</h2>
          <?= $addressBlock($addresses['billing'] ?? null) ?>
          <hr>
          <h2 class="h6">Adresa dodací</h2>
          <?= $addressBlock($addresses['shipping'] ?? null) ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title h5 mb-0">Správa objednávky</h2>
    </div>
    <div class="card-body">
      <form method="post" action="admin.php?r=orders&a=detail&id=<?= (int)($order['id'] ?? 0) ?>" class="row g-3">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <div class="col-md-4">
          <label class="form-label" for="order-status">Stav objednávky</label>
          <select class="form-select" id="order-status" name="status">
            <?php foreach ($statuses as $status): ?>
              <option value="<?= $h($status) ?>" <?= ($order['status'] ?? 'pending') === $status ? 'selected' : '' ?>><?= $h(ucfirst($status)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="order-shipping-total">Cena dopravy</label>
          <input class="form-control" id="order-shipping-total" name="shipping_total" type="text" value="<?= $h((string)($order['shipping_total'] ?? '0')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="order-payment-reference">Platba</label>
          <input class="form-control" id="order-payment-reference" name="payment_reference" type="text" placeholder="např. číslo transakce">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="order-shipping-carrier">Dopravce</label>
          <input class="form-control" id="order-shipping-carrier" name="shipping_carrier" type="text">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="order-shipping-tracking">Číslo zásilky</label>
          <input class="form-control" id="order-shipping-tracking" name="shipping_tracking" type="text">
        </div>
        <div class="col-12">
          <label class="form-label" for="order-notes">Poznámky</label>
          <textarea class="form-control" id="order-notes" name="notes" rows="4" placeholder="Interní poznámky nebo informace pro zákazníka."><?= $h((string)($order['notes'] ?? '')) ?></textarea>
        </div>
        <div class="col-12 d-flex justify-content-between">
          <button class="btn btn-outline-secondary" type="submit" name="fulfill" value="1">Zahájit vyřízení</button>
          <button class="btn btn-primary" type="submit">Uložit změny</button>
        </div>
      </form>
    </div>
  </div>
<?php
});
