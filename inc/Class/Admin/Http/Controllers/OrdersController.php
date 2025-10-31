<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Controllers;

use Cms\Admin\Utils\AdminNavigation;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Models\Repositories\OrderRepository;
use Cms\Models\Repositories\OrderItemRepository;
use Cms\Models\Repositories\AddressRepository;
use Cms\Services\OrderService;
use Core\Database\Init as DB;
use InvalidArgumentException;
use Throwable;

final class OrdersController extends BaseAdminController
{
    private const STATUSES = [
        OrderService::STATUS_NEW,
        OrderService::STATUS_AWAITING_PAYMENT,
        OrderService::STATUS_PACKED,
        OrderService::STATUS_SHIPPED,
        OrderService::STATUS_DELIVERED,
        OrderService::STATUS_CANCELLED,
    ];

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
        $order = $repo->find($id);
        if ($order === null) {
            $this->redirect('admin.php?r=orders', 'danger', 'Objednávka nebyla nalezena.');
        }

        $service = new OrderService($repo);

        $requestedStatus = (string)($_POST['status'] ?? $order->status ?? OrderService::STATUS_NEW);
        $notes = trim((string)($_POST['notes'] ?? ''));
        $paymentReference = trim((string)($_POST['payment_reference'] ?? ''));
        $shippingTracking = trim((string)($_POST['shipping_tracking'] ?? ''));
        $shippingCarrier = trim((string)($_POST['shipping_carrier'] ?? ''));
        $shippingDate = trim((string)($_POST['shipping_date'] ?? ''));
        $shippingTotalRaw = (string)($_POST['shipping_total'] ?? '');
        $shippingTotal = $shippingTotalRaw !== '' ? (float)str_replace(',', '.', $shippingTotalRaw) : (float)$order->shipping_total;

        $noteParts = [];
        if ($notes !== '') {
            $noteParts[] = $notes;
        }
        if ($paymentReference !== '') {
            $noteParts[] = 'Platba: ' . $paymentReference;
        }
        $finalNotes = $noteParts !== [] ? implode("\n\n", $noteParts) : null;

        $payload = [
            'notes' => $finalNotes,
            'shipping_total' => round($shippingTotal, 2),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        /** @var Order $order */
        $order = $repo->update($id, $payload);

        $context = array_filter([
            'source' => 'admin_ui',
            'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
        ], static fn($value) => $value !== null && $value !== '');

        $targetStatus = $requestedStatus;
        if (isset($_POST['fulfill']) && $_POST['fulfill'] === '1') {
            $targetStatus = OrderService::STATUS_PACKED;
        }

        try {
            $order = $service->changeStatus($order, $targetStatus, $notes !== '' ? $notes : null, $context);
        } catch (InvalidArgumentException $exception) {
            $this->redirect(
                'admin.php?r=orders&a=detail&id=' . $id,
                'danger',
                'Změna stavu selhala: ' . $exception->getMessage()
            );
        } catch (Throwable $exception) {
            $this->redirect(
                'admin.php?r=orders&a=detail&id=' . $id,
                'danger',
                'Nepodařilo se uložit změny: ' . $exception->getMessage()
            );
        }

        $shouldCreateShipment = $shippingCarrier !== '' || $shippingTracking !== '' || $shippingDate !== '';

        if ($shouldCreateShipment) {
            try {
                $result = $service->createShipment($order, [
                    'carrier' => $shippingCarrier,
                    'tracking_number' => $shippingTracking,
                    'shipping_date' => $shippingDate,
                    'note' => $notes !== '' ? $notes : null,
                ]);
                $order = $result['order'];
            } catch (Throwable $exception) {
                $this->redirect(
                    'admin.php?r=orders&a=detail&id=' . $id,
                    'danger',
                    'Zásilku se nepodařilo vytvořit: ' . $exception->getMessage()
                );
            }
        }

        $this->redirect('admin.php?r=orders&a=detail&id=' . $id, 'success', 'Objednávka byla aktualizována.');
    }
}

