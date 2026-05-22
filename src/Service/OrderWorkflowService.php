<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrderWorkflowService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function createOrder(
        Customer $customer,
        array $items,
        string $paymentMethod = 'cash',
        ?string $gcashNumber = null,
        ?string $cardType = null,
    ): Order {
        $order = new Order();
        $this->applyCustomerDetails($order, $customer);
        $this->applyPaymentDetails($order, $paymentMethod, $gcashNumber, $cardType);
        $order->setOrderStatus('pending');
        $order->setPaymentStatus('pending');
        $order->setOrderDate(new \DateTimeImmutable());
        $order->setTotalAmount($this->calculateTotal($items));

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function updatePendingOrder(
        Order $order,
        array $items,
        string $paymentMethod = 'cash',
        ?string $gcashNumber = null,
        ?string $cardType = null,
    ): Order {
        $status = strtolower((string) $order->getOrderStatus());
        if (!in_array($status, ['pending', 'new'], true)) {
            throw new \RuntimeException('Only pending orders can be updated.');
        }

        $this->applyPaymentDetails($order, $paymentMethod, $gcashNumber, $cardType);
        $order->setTotalAmount($this->calculateTotal($items));

        $this->entityManager->flush();

        return $order;
    }

    public function updateOrderStatus(Order $order, string $status): Order
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            throw new \InvalidArgumentException('Order status is required.');
        }

        $currentStatus = strtolower((string) $order->getOrderStatus());
        if (in_array($currentStatus, ['completed', 'cancelled'], true) && $currentStatus !== $status) {
            throw new \RuntimeException('Completed or cancelled orders cannot be modified.');
        }

        $order->setOrderStatus($status);
        if ($status === 'cancelled') {
            $order->setPaymentStatus('refunded');
        }

        $this->entityManager->flush();

        return $order;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function calculateTotal(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
            $price = $this->resolveItemPrice($item);
            $total += $price * $quantity;
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveItemPrice(array $item): float
    {
        if (isset($item['product_id'])) {
            $product = $this->productRepository->find((int) $item['product_id']);
            if ($product instanceof Product && $product->getPrice() !== null) {
                return (float) $product->getPrice();
            }
        }

        if (isset($item['price'])) {
            return (float) $item['price'];
        }

        throw new \InvalidArgumentException('Each order item must include a valid product_id or price.');
    }

    private function applyCustomerDetails(Order $order, Customer $customer): void
    {
        $order->setCustomerName($customer->getFullName());
        $order->setCustomerEmail($customer->getEmail());
        $order->setCustomerPhone($customer->getPhoneNumber());
        $order->setShippingAddress($customer->getShippingAddress());
    }

    private function applyPaymentDetails(Order $order, string $paymentMethod, ?string $gcashNumber, ?string $cardType): void
    {
        $order->setPaymentMethod($paymentMethod);
    }
}
