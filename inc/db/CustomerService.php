<?php
declare(strict_types=1);

namespace Cms\Db;

final class CustomerService
{
    public function __construct(private readonly CustomerRepository $customers = new CustomerRepository())
    {
    }

    public function registerCustomer(array $customer, array $addresses = []): int
    {
        $customerId = $this->customers->create($customer);
        foreach ($addresses as $address) {
            $address['customer_id'] = $customerId;
            $this->customers->saveAddress($address);
        }
        return $customerId;
    }

    public function updateCustomer(int $id, array $customer): int
    {
        return $this->customers->update($id, $customer);
    }

    public function addAddress(int $customerId, array $address): int
    {
        $address['customer_id'] = $customerId;
        return $this->customers->saveAddress($address);
    }
}
