<?php
declare(strict_types=1);

namespace Cms\Services;

use Cms\Admin\Mail\MailService;
use Cms\Admin\Mail\MailTemplate;
use Cms\Admin\Mail\TemplateManager;
use Cms\Models\Customer;
use Cms\Models\Order;
use Cms\Models\OrderShipment;
use Cms\Models\OrderStatusHistory;
use Cms\Models\Repositories\CustomerRepository;
use Cms\Models\Repositories\OrderRepository;
use Cms\Models\Repositories\OrderShipmentRepository;
use Cms\Models\Repositories\OrderStatusHistoryRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use function cms_apply_filters;
use function cms_do_action;

final class OrderService
{
    public const STATUS_NEW = 'new';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PACKED = 'packed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    private const DEFAULT_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_AWAITING_PAYMENT,
        self::STATUS_PACKED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    private const DEFAULT_TRANSITIONS = [
        self::STATUS_NEW => [self::STATUS_AWAITING_PAYMENT, self::STATUS_PACKED, self::STATUS_CANCELLED],
        self::STATUS_AWAITING_PAYMENT => [self::STATUS_PACKED, self::STATUS_CANCELLED],
        self::STATUS_PACKED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    private OrderRepository $orders;
    private CustomerRepository $customers;
    private OrderStatusHistoryRepository $statusHistory;
    private OrderShipmentRepository $shipments;
    private InventoryService $inventory;
    private MailService $mailer;
    private TemplateManager $mailTemplates;

    public function __construct(
        ?OrderRepository $orders = null,
        ?CustomerRepository $customers = null,
        ?InventoryService $inventory = null,
        ?OrderStatusHistoryRepository $statusHistory = null,
        ?OrderShipmentRepository $shipments = null,
        ?MailService $mailer = null,
        ?TemplateManager $templates = null
    )
    {
        $this->orders = $orders ?? new OrderRepository();
        $this->customers = $customers ?? new CustomerRepository();
        $this->inventory = $inventory ?? new InventoryService();
        $this->statusHistory = $statusHistory ?? new OrderStatusHistoryRepository();
        $this->shipments = $shipments ?? new OrderShipmentRepository();
        $this->mailer = $mailer ?? new MailService();
        $this->mailTemplates = $templates ?? new TemplateManager();
    }

    /**
     * @param array{items:list<array<string,mixed>>,subtotal:float,total:float,currency:string} $cart
     * @param array{
     *     customer:array{email:string,first_name:string,last_name:string,phone:?string,marketing_opt_in:bool|int},
     *     billing:array<string,mixed>,
     *     shipping:array<string,mixed>,
     *     payment_method:string,
     *     shipping_method:string,
     *     shipping_total:float,
     *     notes?:string,
     *     user_id?:?int,
     *     status?:string
     * } $data
     *
     * @return array{order:Order,customer:Customer}
     */
    public function create(array $cart, array $data): array
    {
        if ($cart['items'] === []) {
            throw new InvalidArgumentException('Nelze vytvořit objednávku bez položek v košíku.');
        }

        $customerInput = $data['customer'];
        $email = trim($customerInput['email'] ?? '');
        if ($email === '') {
            throw new InvalidArgumentException('Chybí e-mail zákazníka.');
        }

        $customerData = [
            'email' => $email,
            'first_name' => trim((string)($customerInput['first_name'] ?? '')),
            'last_name' => trim((string)($customerInput['last_name'] ?? '')),
            'phone' => $customerInput['phone'] !== null ? trim((string)$customerInput['phone']) : null,
            'marketing_opt_in' => !empty($customerInput['marketing_opt_in']) ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (array_key_exists('user_id', $data) && $data['user_id'] !== null) {
            $userId = (int)$data['user_id'];
            if ($userId > 0) {
                $customerData['user_id'] = $userId;
            }
        }

        $customer = $this->customers->upsertByEmail($email, $customerData);

        $shippingTotal = isset($data['shipping_total']) ? (float)$data['shipping_total'] : 0.0;
        $status = $this->normalizeStatus($data['status'] ?? self::STATUS_NEW);

        $orderNotes = trim((string)($data['notes'] ?? ''));
        $notesParts = [];
        if ($orderNotes !== '') {
            $notesParts[] = $orderNotes;
        }
        $notesParts[] = 'Způsob platby: ' . $data['payment_method'];
        $notesParts[] = 'Doprava: ' . $data['shipping_method'];
        $notes = implode("\n", $notesParts);

        $subtotal = (float)$cart['subtotal'];
        $discount = 0.0;
        $tax = 0.0;
        $total = round($subtotal - $discount + $tax + $shippingTotal, 2);

        $orderData = [
            'order_number' => $this->generateOrderNumber(),
            'customer_id' => isset($customer->id) ? (int)$customer->id : null,
            'user_id' => $data['user_id'] ?? null,
            'status' => $status,
            'currency' => $cart['currency'] ?? 'USD',
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'shipping_total' => $shippingTotal,
            'total' => $total,
            'notes' => $notes,
            'placed_at' => gmdate('Y-m-d H:i:s'),
        ];

        /** @var Order $order */
        $historyEntry = null;
        $order = db_transaction(function () use ($orderData, $cart, $data, $customer, &$historyEntry): Order {
            $order = $this->orders->create($orderData);

            foreach ($cart['items'] as $item) {
                $this->orders->addItem((int)$order->id, [
                    'product_id' => isset($item['product_id']) ? (int)$item['product_id'] : null,
                    'variant_id' => isset($item['variant_id']) ? (int)$item['variant_id'] : null,
                    'sku' => isset($item['sku']) ? (string)$item['sku'] : null,
                    'name' => (string)$item['name'],
                    'quantity' => (int)$item['quantity'],
                    'unit_price' => (float)$item['price'],
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_price' => round((float)$item['price'] * (int)$item['quantity'], 2),
                ]);
            }

            $billing = $this->normalizeAddress($data['billing'], $customer);
            $shipping = $this->normalizeAddress($data['shipping'], $customer);

            $this->orders->setAddress((int)$order->id, 'billing', $billing);
            $this->orders->setAddress((int)$order->id, 'shipping', $shipping);

            $reference = isset($order->order_number) ? (string)$order->order_number : null;
            $this->inventory->reserveForOrder((int)$order->id, $cart['items'], $reference, 'Order placement');

            $historyEntry = $this->recordStatusHistory(
                (int)$order->id,
                null,
                $orderData['status'],
                'Objednávka vytvořena',
                ['initiator' => 'system']
            );

            return $order;
        });

        if ($historyEntry !== null) {
            cms_do_action('order.status_history.recorded', $historyEntry);
        }
        cms_do_action('order.created', $order, $customer);

        return [
            'order' => $order,
            'customer' => $customer,
        ];
    }

    public function changeStatus(Order $order, string $targetStatus, ?string $note = null, array $context = [], bool $sendNotification = true): Order
    {
        if (!isset($order->id)) {
            throw new InvalidArgumentException('Objednávka nemá přiřazené ID.');
        }

        $fromStatus = $this->resolveStatus($order);
        $toStatus = $this->normalizeStatus($targetStatus);

        if ($fromStatus === $toStatus) {
            return $order;
        }

        if (!$this->canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException(sprintf('Neplatná změna stavu z "%s" na "%s".', $fromStatus, $toStatus));
        }

        $historyEntry = null;
        $updatedOrder = db_transaction(function () use ($order, $fromStatus, $toStatus, $note, $context, &$historyEntry): Order {
            $payload = [
                'status' => $toStatus,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ];

            if ($toStatus === self::STATUS_CANCELLED) {
                $payload['cancelled_at'] = gmdate('Y-m-d H:i:s');
                $this->inventory->releaseForOrder((int)$order->id, 'Order cancelled');
            }

            /** @var Order $updated */
            $updated = $this->orders->update((int)$order->id, $payload);

            $historyEntry = $this->recordStatusHistory((int)$order->id, $fromStatus, $toStatus, $note, $context);

            return $updated;
        });

        if ($historyEntry !== null) {
            cms_do_action('order.status_history.recorded', $historyEntry);
        }

        if ($sendNotification) {
            $this->notifyStatusChange($updatedOrder, $fromStatus, $toStatus, $note, $context);
        }

        cms_do_action('order.status_changed', $updatedOrder, $fromStatus, $toStatus, $historyEntry, $context);

        return $updatedOrder;
    }

    /**
     * @param array{carrier?:string,tracking_number?:string,shipping_date?:string|\DateTimeInterface,shipped_at?:string|\DateTimeInterface,note?:string} $data
     * @return array{shipment:OrderShipment,order:Order}
     */
    public function createShipment(Order $order, array $data): array
    {
        if (!isset($order->id)) {
            throw new InvalidArgumentException('Objednávka nemá přiřazené ID.');
        }

        $currentStatus = $this->resolveStatus($order);
        if ($currentStatus === self::STATUS_CANCELLED || $currentStatus === self::STATUS_DELIVERED) {
            throw new InvalidArgumentException('Pro tento stav objednávky nelze vytvořit zásilku.');
        }

        $carrier = trim((string)($data['carrier'] ?? ''));
        $tracking = trim((string)($data['tracking_number'] ?? ''));
        $note = isset($data['note']) ? trim((string)$data['note']) : null;

        if ($carrier === '' && $tracking === '') {
            throw new InvalidArgumentException('Zadejte alespoň dopravce nebo číslo zásilky.');
        }

        $shippedAtInput = $data['shipped_at'] ?? $data['shipping_date'] ?? null;
        $shippedAt = $this->normalizeShipmentDate($shippedAtInput);

        $context = [
            'carrier' => $carrier,
            'tracking_number' => $tracking,
            'shipped_at' => $shippedAt,
        ];

        $shipment = db_transaction(function () use ($order, $carrier, $tracking, $shippedAt, $note, $context): OrderShipment {
            $payload = [
                'order_id' => (int)$order->id,
                'carrier' => $carrier !== '' ? $carrier : null,
                'tracking_number' => $tracking !== '' ? $tracking : null,
                'shipped_at' => $shippedAt,
                'note' => $note,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];

            /** @var OrderShipment $shipment */
            $shipment = $this->shipments->create($payload);

            $reference = isset($order->order_number) ? (string)$order->order_number : null;
            $this->inventory->consumeForOrder((int)$order->id, $reference, 'Shipment created');

            $this->recordStatusHistory((int)$order->id, $this->resolveStatus($order), $this->resolveStatus($order), 'Zásilka vytvořena', $context);

            return $shipment;
        });

        cms_do_action('order.shipment_created', $shipment, $order);

        $reloadedOrder = $this->orders->find((int)$order->id);
        if (!$reloadedOrder instanceof Order) {
            throw new RuntimeException('Objednávku se nepodařilo znovu načíst.');
        }

        if ($this->resolveStatus($reloadedOrder) !== self::STATUS_SHIPPED && $this->canTransition($this->resolveStatus($reloadedOrder), self::STATUS_SHIPPED)) {
            $reloadedOrder = $this->changeStatus($reloadedOrder, self::STATUS_SHIPPED, 'Objednávka byla odeslána.', $context);
        }

        $this->notifyShipmentCreated($reloadedOrder, $shipment);

        return [
            'shipment' => $shipment,
            'order' => $reloadedOrder,
        ];
    }

    private function resolveStatus(Order $order): string
    {
        $status = isset($order->status) ? (string)$order->status : '';
        if ($status === '') {
            return self::STATUS_NEW;
        }

        return $this->normalizeStatus($status);
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            $normalized = self::STATUS_NEW;
        }

        $allowed = $this->allowedStatuses();
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Neznámý stav objednávky "%s".', $status));
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        $statuses = cms_apply_filters('order.statuses', self::DEFAULT_STATUSES);
        if (!is_array($statuses) || $statuses === []) {
            return self::DEFAULT_STATUSES;
        }

        $normalized = [];
        foreach ($statuses as $status) {
            if (!is_string($status)) {
                continue;
            }
            $status = strtolower(trim($status));
            if ($status === '') {
                continue;
            }
            $normalized[$status] = $status;
        }

        return array_values($normalized);
    }

    private function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $transitions = cms_apply_filters('order.status_transitions', self::DEFAULT_TRANSITIONS);
        if (!isset($transitions[$from]) || !is_array($transitions[$from])) {
            $transitions[$from] = self::DEFAULT_TRANSITIONS[$from] ?? [];
        }

        return in_array($to, $transitions[$from], true);
    }

    private function normalizeShipmentDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return gmdate('Y-m-d H:i:s');
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Neplatné datum odeslání zásilky.');
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function notifyStatusChange(Order $order, string $from, string $to, ?string $note, array $context): void
    {
        $customer = $this->resolveCustomer($order);
        if ($customer === null) {
            return;
        }

        try {
            $template = $this->mailTemplates->render('order_status_changed', [
                'order' => $order,
                'customer' => $customer,
                'fromStatus' => $from,
                'toStatus' => $to,
                'note' => $note,
                'context' => $context,
            ]);
        } catch (Throwable $exception) {
            cms_do_action('order.status_notification_failed', $order, $exception);
            return;
        }

        $this->sendMailTemplate($customer, $template);
    }

    private function notifyShipmentCreated(Order $order, OrderShipment $shipment): void
    {
        $customer = $this->resolveCustomer($order);
        if ($customer === null) {
            return;
        }

        try {
            $template = $this->mailTemplates->render('order_shipped', [
                'order' => $order,
                'customer' => $customer,
                'shipment' => $shipment,
            ]);
        } catch (Throwable $exception) {
            cms_do_action('order.shipment_notification_failed', $order, $exception);
            return;
        }

        $this->sendMailTemplate($customer, $template);
    }

    private function sendMailTemplate(Customer $customer, MailTemplate $template): void
    {
        $email = trim((string)($customer->email ?? ''));
        if ($email === '') {
            return;
        }

        $nameParts = array_filter([
            trim((string)($customer->first_name ?? '')),
            trim((string)($customer->last_name ?? '')),
        ]);

        $name = $nameParts !== [] ? implode(' ', $nameParts) : null;

        $this->mailer->sendTemplate($email, $template, $name);
    }

    private function resolveCustomer(Order $order): ?Customer
    {
        $customerId = isset($order->customer_id) ? (int)$order->customer_id : 0;
        if ($customerId <= 0) {
            return null;
        }

        $customer = $this->customers->find($customerId);
        return $customer instanceof Customer ? $customer : null;
    }

    private function recordStatusHistory(int $orderId, ?string $from, string $to, ?string $note, array $context): OrderStatusHistory
    {
        $payload = [
            'order_id' => $orderId,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'context' => $this->encodeContext($context),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        /** @var OrderStatusHistory $entry */
        $entry = $this->statusHistory->create($payload);

        return $entry;
    }

    private function generateOrderNumber(): string
    {
        $attempts = 0;
        do {
            $attempts++;
            $number = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $existing = $this->orders->findByNumber($number);
            if ($existing === null) {
                return $number;
            }
        } while ($attempts < 10);

        return 'ORD-' . date('Ymd') . '-' . time();
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalizeAddress(array $data, Customer $customer): array
    {
        $firstName = trim((string)($data['first_name'] ?? $customer->first_name ?? ''));
        $lastName = trim((string)($data['last_name'] ?? $customer->last_name ?? ''));
        $line1 = trim((string)($data['line1'] ?? ''));
        $city = trim((string)($data['city'] ?? ''));
        $postalCode = trim((string)($data['postal_code'] ?? ''));
        $country = strtoupper(trim((string)($data['country'] ?? '')));

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => isset($data['company']) ? trim((string)$data['company']) : null,
            'line1' => $line1,
            'line2' => isset($data['line2']) ? trim((string)$data['line2']) : null,
            'city' => $city,
            'state' => isset($data['state']) ? trim((string)$data['state']) : null,
            'postal_code' => $postalCode,
            'country' => $country,
            'phone' => isset($data['phone']) && $data['phone'] !== ''
                ? trim((string)$data['phone'])
                : ($customer->phone ?? null),
            'email' => $customer->email ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function encodeContext(array $context): ?string
    {
        if ($context === []) {
            return null;
        }

        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null;
    }
}
