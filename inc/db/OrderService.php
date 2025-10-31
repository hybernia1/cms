<?php
declare(strict_types=1);

namespace Cms\Db;

final class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly InvoiceRepository $invoices = new InvoiceRepository()
    ) {
    }

    public function createOrder(array $order, array $items): int
    {
        $orderId = $this->orders->create($order);
        foreach ($items as $item) {
            $item['order_id'] = $orderId;
            $this->orders->addItem($item);
        }
        return $orderId;
    }

    public function changeStatus(int $orderId, int $statusId): int
    {
        return $this->orders->update($orderId, [
            'status_id' => $statusId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function ensureInvoice(int $orderId, array $data): int
    {
        $existing = $this->invoices->findByOrder($orderId);
        if ($existing) {
            $this->invoices->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        $data['order_id'] = $orderId;
        return $this->invoices->create($data);
    }
}
