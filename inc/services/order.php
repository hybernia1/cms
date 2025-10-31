<?php
declare(strict_types=1);

namespace Cms\Services;

use Cms\Models\Customer;
use Cms\Models\Order;
use Cms\Models\Repositories\CustomerRepository;
use Cms\Models\Repositories\OrderRepository;
use InvalidArgumentException;

final class OrderService
{
    private OrderRepository $orders;
    private CustomerRepository $customers;
    private InventoryService $inventory;

    public function __construct(?OrderRepository $orders = null, ?CustomerRepository $customers = null, ?InventoryService $inventory = null)
    {
        $this->orders = $orders ?? new OrderRepository();
        $this->customers = $customers ?? new CustomerRepository();
        $this->inventory = $inventory ?? new InventoryService();
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
        $status = isset($data['status']) && $data['status'] !== '' ? $data['status'] : 'pending';

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
        $order = db_transaction(function () use ($orderData, $cart, $data, $customer): Order {
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

            return $order;
        });

        return [
            'order' => $order,
            'customer' => $customer,
        ];
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
}
