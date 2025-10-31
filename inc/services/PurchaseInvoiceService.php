<?php
declare(strict_types=1);

namespace Cms\Services;

use Cms\Models\StockEntry;
use InvalidArgumentException;
use RuntimeException;

final class PurchaseInvoiceService
{
    private InventoryService $inventory;

    public function __construct(?InventoryService $inventory = null)
    {
        $this->inventory = $inventory ?? new InventoryService();
    }

    /**
     * @param array<string,mixed> $invoice
     * @param list<array<string,mixed>> $items
     *
     * @return list<StockEntry>
     */
    public function ingestManual(array $invoice, array $items, ?string $reason = null): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('Invoice must contain at least one line item.');
        }

        $reference = null;
        if (isset($invoice['reference']) && $invoice['reference'] !== '') {
            $reference = (string)$invoice['reference'];
        }
        if (isset($invoice['invoice_number']) && $invoice['invoice_number'] !== '') {
            $reference = (string)$invoice['invoice_number'];
        }
        $entries = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $variantId = isset($item['variant_id']) ? (int)$item['variant_id'] : 0;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            if ($variantId <= 0 || $quantity === 0) {
                continue;
            }

            $meta = $this->buildMeta($invoice, $item);
            $entry = $this->inventory->adjust(
                $variantId,
                $quantity,
                $reason !== null && $reason !== '' ? $reason : 'Purchase invoice receipt',
                $reference,
                $meta
            );
            $entries[] = $entry;
        }

        if ($entries === []) {
            throw new RuntimeException('No valid items were provided for invoice ingestion.');
        }

        return $entries;
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return list<StockEntry>
     */
    public function ingestFromFile(string $filePath, callable $parser, array $context = []): array
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException('Invoice source file not found.');
        }

        $parsed = $parser($filePath);
        if (!is_array($parsed)) {
            throw new RuntimeException('Invoice parser must return an array payload.');
        }

        $invoice = isset($parsed['invoice']) && is_array($parsed['invoice'])
            ? $parsed['invoice']
            : [];
        $items = isset($parsed['items']) && is_array($parsed['items'])
            ? $parsed['items']
            : [];

        $invoicePayload = array_merge($context, $invoice);

        return $this->ingestManual($invoicePayload, $items, $invoicePayload['reason'] ?? null);
    }

    /**
     * @param array<string,mixed> $invoice
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function buildMeta(array $invoice, array $item): array
    {
        $meta = [];
        $invoiceNumber = isset($invoice['invoice_number']) ? (string)$invoice['invoice_number'] : '';
        if ($invoiceNumber !== '') {
            $meta['invoice_number'] = $invoiceNumber;
        }

        $supplier = isset($invoice['supplier']) ? (string)$invoice['supplier'] : '';
        if ($supplier !== '') {
            $meta['supplier'] = $supplier;
        }

        $unitCost = null;
        if (isset($item['unit_cost'])) {
            $rawCost = (string)$item['unit_cost'];
            if ($rawCost !== '') {
                $unitCost = (float)str_replace(',', '.', $rawCost);
            }
        } elseif (isset($invoice['unit_cost'])) {
            $invoiceCost = (string)$invoice['unit_cost'];
            if ($invoiceCost !== '') {
                $unitCost = (float)str_replace(',', '.', $invoiceCost);
            }
        }

        if ($unitCost !== null) {
            $meta['unit_cost'] = $unitCost;
        }

        return $meta;
    }
}
