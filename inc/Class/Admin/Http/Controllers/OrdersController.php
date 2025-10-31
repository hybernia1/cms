<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Models\Repositories\OrderRepository;
use Cms\Models\Repositories\OrderItemRepository;
use Cms\Models\Repositories\AddressRepository;
use Cms\Services\InventoryService;
use Core\Database\Init as DB;
use Throwable;

final class OrdersController extends BaseAdminController
{
    private const STATUSES = ['draft', 'pending', 'processing', 'completed', 'cancelled', 'refunded'];

    public function handle(string $action): void
    {
        if ($action === 'detail') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->updateDetail();
                return;
            }
            $this->detail();
            return;
        }

        $this->index();
    }

    private function index(): void
    {
        $filters = [
            'status' => (string)($_GET['status'] ?? ''),
            'q'      => (string)($_GET['q'] ?? ''),
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;

        $query = DB::query()->table('orders', 'o')
            ->select(['o.id', 'o.order_number', 'o.status', 'o.total', 'o.currency', 'o.created_at', 'c.email', 'c.first_name', 'c.last_name'])
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->orderBy('o.created_at', 'DESC');

        if ($filters['status'] !== '' && in_array($filters['status'], self::STATUSES, true)) {
            $query->where('o.status', '=', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $search = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($search) {
                $w->whereLike('o.order_number', $search)
                    ->orWhereLike('c.email', $search);
            });
        }

        $paginated = $query->paginate($page, $perPage);
        $orders = $paginated['items'] ?? [];

        $dateFactory = new DateTimeFactory();
        foreach ($orders as &$order) {
            $createdRaw = isset($order['created_at']) ? (string)$order['created_at'] : null;
            $order['created_at_display'] = $createdRaw ? $dateFactory->fromStorage($createdRaw)?->format('d.m.Y H:i') : '';
        }
        unset($order);

        $this->renderAdmin('orders/index', [
            'pageTitle'  => 'Objednávky',
            'nav'        => AdminNavigation::build('orders'),
            'orders'     => $orders,
            'filters'    => $filters,
            'statuses'   => self::STATUSES,
            'pagination' => $this->paginationData($paginated, $page, $perPage),
            'buildUrl'   => $this->listingUrlBuilder(['r' => 'orders', 'status' => $filters['status'], 'q' => $filters['q']]),
        ]);
    }

    private function detail(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->redirect('admin.php?r=orders', 'danger', 'Objednávka nebyla nalezena.');
        }

        $orderRepo = new OrderRepository();
        $order = $orderRepo->find($id);
        if ($order === null) {
            $this->redirect('admin.php?r=orders', 'danger', 'Objednávka nebyla nalezena.');
        }

        $items = (new OrderItemRepository())->forOrder($id);
        $addresses = (new AddressRepository())->forOrder($id);
        $addressData = ['billing' => null, 'shipping' => null];
        foreach ($addresses as $address) {
            $type = (string)$address->type;
            if (isset($addressData[$type])) {
                $addressData[$type] = $address->toArray();
            }
        }

        $this->renderAdmin('orders/detail', [
            'pageTitle' => 'Objednávka ' . $order->order_number,
            'nav'       => AdminNavigation::build('orders'),
            'order'     => $order->toArray(),
            'items'     => array_map(static fn($item) => $item->toArray(), $items),
            'addresses' => $addressData,
            'statuses'  => self::STATUSES,
        ]);
    }

    private function updateDetail(): void
    {
        $this->assertCsrf();
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->redirect('admin.php?r=orders', 'danger', 'Objednávka nebyla nalezena.');
        }

        $repo = new OrderRepository();
        $inventory = new InventoryService();
        $order = $repo->find($id);
        if ($order === null) {
            $this->redirect('admin.php?r=orders', 'danger', 'Objednávka nebyla nalezena.');
        }

        $previousStatus = isset($order->status) ? (string)$order->status : 'pending';
        $status = (string)($_POST['status'] ?? $order->status ?? 'pending');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }
        $notes = trim((string)($_POST['notes'] ?? ''));
        $paymentReference = trim((string)($_POST['payment_reference'] ?? ''));
        $shippingTracking = trim((string)($_POST['shipping_tracking'] ?? ''));
        $shippingCarrier = trim((string)($_POST['shipping_carrier'] ?? ''));
        $shippingTotalRaw = (string)($_POST['shipping_total'] ?? '');
        $shippingTotal = $shippingTotalRaw !== '' ? (float)str_replace(',', '.', $shippingTotalRaw) : (float)$order->shipping_total;

        $noteParts = [];
        if ($notes !== '') {
            $noteParts[] = $notes;
        }
        if ($paymentReference !== '') {
            $noteParts[] = 'Platba: ' . $paymentReference;
        }
        if ($shippingTracking !== '') {
            $label = 'Doprava';
            if ($shippingCarrier !== '') {
                $label .= ' (' . $shippingCarrier . ')';
            }
            $noteParts[] = $label . ': ' . $shippingTracking;
        }
        $finalNotes = $noteParts !== [] ? implode("\n\n", $noteParts) : null;

        $payload = [
            'status' => $status,
            'notes' => $finalNotes,
            'shipping_total' => round($shippingTotal, 2),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($status === 'cancelled') {
            $payload['cancelled_at'] = gmdate('Y-m-d H:i:s');
        } elseif ($status === 'completed' || $status === 'processing') {
            if (empty($order->placed_at)) {
                $payload['placed_at'] = gmdate('Y-m-d H:i:s');
            }
        }

        $updatedOrder = $repo->update($id, $payload);
        $finalStatus = isset($updatedOrder->status) ? (string)$updatedOrder->status : $status;

        if (isset($_POST['fulfill']) && $_POST['fulfill'] === '1') {
            $fulfillPayload = [
                'status' => 'processing',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];
            if (empty($order->placed_at)) {
                $fulfillPayload['placed_at'] = gmdate('Y-m-d H:i:s');
            }
            $updatedOrder = $repo->update($id, $fulfillPayload);
            $finalStatus = isset($updatedOrder->status) ? (string)$updatedOrder->status : 'processing';
        }

        try {
            if ($finalStatus === 'cancelled' && $previousStatus !== 'cancelled') {
                $inventory->releaseForOrder($id, 'Order cancelled');
            }

            $fulfillmentStatuses = ['processing', 'completed'];
            if (in_array($finalStatus, $fulfillmentStatuses, true) && !in_array($previousStatus, $fulfillmentStatuses, true)) {
                $reference = isset($updatedOrder->order_number) ? (string)$updatedOrder->order_number : null;
                $inventory->consumeForOrder($id, $reference, 'Order fulfilled');
            }
        } catch (Throwable $exception) {
            $this->redirect(
                'admin.php?r=orders&a=detail&id=' . $id,
                'danger',
                'Nepodařilo se aktualizovat sklad: ' . $exception->getMessage()
            );
        }

        $this->redirect('admin.php?r=orders&a=detail&id=' . $id, 'success', 'Objednávka byla aktualizována.');
    }
}

