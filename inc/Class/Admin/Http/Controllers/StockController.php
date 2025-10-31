<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Cms\Services\InventoryService;

final class StockController extends BaseAdminController
{
    public function handle(string $action): void
    {
        if ($action === 'adjust' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->adjust();
            return;
        }

        $this->index();
    }

    private function index(): void
    {
        $selectedVariant = isset($_GET['variant']) ? (int)$_GET['variant'] : 0;
        $variants = $this->loadVariants();
        $history = [];
        if ($selectedVariant > 0) {
            $history = $this->variantHistory($selectedVariant);
        }
        $recent = $this->recentEntries();

        $this->renderAdmin('stock/index', [
            'pageTitle'       => 'Správa skladových zásob',
            'nav'             => AdminNavigation::build('stock'),
            'variants'        => $variants,
            'selectedVariant' => $selectedVariant,
            'history'         => $history,
            'recent'          => $recent,
        ]);
    }

    private function adjust(): void
    {
        $this->assertCsrf();

        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $quantity = isset($_POST['quantity_change']) ? (int)$_POST['quantity_change'] : 0;
        $reason = trim((string)($_POST['reason'] ?? '')); 
        $reference = trim((string)($_POST['reference'] ?? ''));
        $mode = (string)($_POST['mode'] ?? 'manual');

        $errors = [];
        if ($variantId <= 0) {
            $errors['variant_id'] = 'Vyberte variantu produktu.';
        }
        if ($quantity === 0) {
            $errors['quantity_change'] = 'Zadejte nenulové množství.';
        }

        $meta = [];
        if ($mode === 'invoice') {
            $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
            if ($invoiceNumber === '') {
                $errors['invoice_number'] = 'Doplňte číslo dokladu.';
            } else {
                $meta['invoice_number'] = $invoiceNumber;
            }
            $supplier = trim((string)($_POST['supplier'] ?? ''));
            if ($supplier !== '') {
                $meta['supplier'] = $supplier;
            }
            $unitCost = trim((string)($_POST['unit_cost'] ?? ''));
            if ($unitCost !== '') {
                $meta['unit_cost'] = $unitCost;
            }
            if ($reason === '') {
                $reason = 'Doplnění skladu podle faktury';
            }
        }

        if ($errors !== []) {
            $this->respondErrors($errors, 'Nelze zapsat pohyb na skladě.');
        }

        $service = new InventoryService();
        $service->adjust($variantId, $quantity, $reason === '' ? 'Ruční úprava' : $reason, $reference !== '' ? $reference : null, $meta);

        $target = 'admin.php?r=stock';
        if ($variantId > 0) {
            $target .= '&variant=' . $variantId;
        }

        $this->redirect($target, 'success', 'Pohyb na skladě byl zaznamenán.');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadVariants(): array
    {
        $sql = 'SELECT v.id, v.name, v.sku, v.inventory_quantity, p.name AS product_name'
            . ' FROM product_variants v'
            . ' INNER JOIN products p ON p.id = v.product_id'
            . ' ORDER BY p.name ASC, v.sort_order ASC, v.name ASC';

        return db_fetch_all($sql);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function variantHistory(int $variantId): array
    {
        $entries = (new InventoryService())->historyForVariant($variantId);
        $history = [];
        foreach ($entries as $entry) {
            $row = $entry->toArray();
            $row['meta'] = $this->decodeMeta($row['meta'] ?? null);
            $history[] = $row;
        }

        return $history;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function recentEntries(): array
    {
        $sql = 'SELECT se.*, v.sku, v.name AS variant_name, p.name AS product_name'
            . ' FROM stock_entries se'
            . ' INNER JOIN product_variants v ON v.id = se.variant_id'
            . ' INNER JOIN products p ON p.id = v.product_id'
            . ' ORDER BY se.created_at DESC, se.id DESC'
            . ' LIMIT 20';

        $rows = db_fetch_all($sql);
        foreach ($rows as &$row) {
            $row['meta'] = $this->decodeMeta($row['meta'] ?? null);
        }
        unset($row);

        return $rows;
    }

    private function decodeMeta(mixed $meta): mixed
    {
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $meta;
    }

    /**
     * @param array<string,string> $errors
     */
    private function respondErrors(array $errors, string $message): never
    {
        if ($this->isAjax()) {
            $this->jsonError($errors, status: 422);
        }

        $this->flash('danger', $message);
        $this->redirect('admin.php?r=stock');
    }
}

