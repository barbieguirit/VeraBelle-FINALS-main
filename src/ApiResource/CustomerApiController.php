<?php

namespace App\ApiResource;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use App\Service\OrderWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/customer')]
class CustomerApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher,
        private OrderWorkflowService $orderWorkflowService,
        private ActivityLogger $activityLogger,
    ) {}

    #[Route('/register', name: 'api_customer_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $required = ['name', 'email', 'password', 'phone', 'address'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Field '$field' is required."], 400);
            }
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['error' => 'Email already registered.'], 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setIsActive(true);
        $user->setIsVerified(true);

        $customer = new Customer();
        $customer->setName($data['name']);
        $customer->setEmail($data['email']);
        $customer->setPhone($data['phone']);
        $customer->setAddress($data['address']);

        $this->em->persist($user);
        $this->em->persist($customer);
        $this->em->flush();

        $token = $this->jwtManager->createFromPayload($user, [
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'customer_id' => $customer->getId(),
        ]);

        return $this->json([
            'message' => 'Registration successful!',
            'token' => $token,
            'customer_id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
        ], 201);
    }

    #[Route('/login', name: 'api_customer_login', methods: ['POST'])]
    public function login(Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email and password are required.'], 400);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->json(['error' => 'Invalid email or password.'], 401);
        }

        if (!$user->isActive()) {
            return $this->json(['error' => 'Account is inactive.'], 403);
        }

        $user->setLastLogin(new \DateTime());
        $this->em->flush();
        $this->activityLogger->logLogin($user);

        $customer = $customerRepository->findOneBy(['email' => $user->getEmail()]);

        $token = $this->jwtManager->createFromPayload($user, [
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'customer_id' => $customer?->getId(),
        ]);

        return $this->json([
            'message' => 'Login successful!',
            'token' => $token,
            'customer_id' => $customer?->getId(),
            'name' => $customer?->getName(),
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/logout', name: 'api_customer_logout', methods: ['POST'])]
    public function logout(Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['customer_id'])) {
            return $this->json(['error' => 'customer_id is required.'], 400);
        }

        $customer = $customerRepository->find((int) $data['customer_id']);
        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found.'], 404);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $customer->getEmail()]);
        if ($user instanceof User) {
            $this->activityLogger->logLogout($user);
        }

        return $this->json(['message' => 'Logout recorded.']);
    }

    #[Route('/products', name: 'api_customer_products', methods: ['GET'])]
    public function products(ProductRepository $productRepository): JsonResponse
    {
        $products = $productRepository->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(static function ($product): array {
            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
                'image' => $product->getImage(),
                'image_url' => $product->getImage() ? '/uploads/' . $product->getImage() : null,
                'category' => $product->getCategory()?->getName(),
            ];
        }, $products);

        return $this->json([
            'success' => true,
            'count' => count($data),
            'data' => $data,
            'products' => $data,
        ]);
    }

    #[Route('/orders', name: 'api_customer_place_order', methods: ['POST'])]
    public function placeOrder(Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $items = $data['items'] ?? [];
        $paymentMethod = (string) ($data['payment_method'] ?? 'cash');
        $gcashNumber = $data['gcash_number'] ?? null;
        $cardType = $data['card_type'] ?? null;

        if ($items === [] && !empty($data['product_id'])) {
            $items = [[
                'product_id' => (int) $data['product_id'],
                'quantity' => (int) ($data['quantity'] ?? 1),
            ]];
        }

        if (empty($data['customer_id']) || $items === []) {
            return $this->json(['error' => 'customer_id and at least one order item are required.'], 400);
        }

        $customer = $customerRepository->find($data['customer_id']);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found.'], 404);
        }

        try {
            $order = $this->orderWorkflowService->createOrder(
                $customer,
                $items,
                $paymentMethod,
                is_string($gcashNumber) ? $gcashNumber : null,
                is_string($cardType) ? $cardType : null,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json([
            'message' => 'Order placed successfully!',
            ...$this->serializeOrder($order),
        ], 201);
    }

    #[Route('/orders/{customerId}', name: 'api_customer_orders', methods: ['GET'])]
    public function orders(int $customerId, CustomerRepository $customerRepository): JsonResponse
    {
        $customer = $customerRepository->find($customerId);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found.'], 404);
        }

        $orders = $customer->getOrders()->toArray();
        usort($orders, static fn(Order $left, Order $right) => $right->getCreatedAt() <=> $left->getCreatedAt());

        $orders = array_map(fn(Order $order) => $this->serializeOrder($order), $orders);

        return $this->json([
            'customer' => $customer->getName(),
            'orders' => $orders,
            'total_orders' => count($orders),
        ]);
    }

    #[Route('/orders/{orderId}', name: 'api_customer_update_order', methods: ['PUT', 'PATCH'])]
    public function updateOrder(int $orderId, Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $items = $data['items'] ?? [];
        $paymentMethod = (string) ($data['payment_method'] ?? 'cash');
        $gcashNumber = $data['gcash_number'] ?? null;
        $cardType = $data['card_type'] ?? null;

        if (empty($data['customer_id'])) {
            return $this->json(['error' => 'customer_id is required.'], 400);
        }

        $customer = $customerRepository->find((int) $data['customer_id']);
        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found.'], 404);
        }

        $order = $this->em->getRepository(Order::class)->find($orderId);
        if (!$order instanceof Order) {
            return $this->json(['error' => 'Order not found.'], 404);
        }

        if ($order->getCustomer()?->getId() !== $customer->getId()) {
            return $this->json(['error' => 'You can only edit your own orders.'], 403);
        }

        if ($items === []) {
            return $this->json(['error' => 'At least one order item is required.'], 400);
        }

        try {
            $order = $this->orderWorkflowService->updatePendingOrder(
                $order,
                $items,
                $paymentMethod,
                is_string($gcashNumber) ? $gcashNumber : null,
                is_string($cardType) ? $cardType : null,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json([
            'message' => 'Order updated successfully.',
            ...$this->serializeOrder($order),
        ]);
    }

    #[Route('/orders/{orderId}/cancel', name: 'api_customer_cancel_order', methods: ['POST'])]
    public function cancelOrder(int $orderId, Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['customer_id'])) {
            return $this->json(['error' => 'customer_id is required.'], 400);
        }

        $customer = $customerRepository->find((int) $data['customer_id']);
        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found.'], 404);
        }

        $order = $this->em->getRepository(Order::class)->find($orderId);
        if (!$order instanceof Order) {
            return $this->json(['error' => 'Order not found.'], 404);
        }

        if ($order->getCustomer()?->getId() !== $customer->getId()) {
            return $this->json(['error' => 'You can only cancel your own orders.'], 403);
        }

        try {
            $this->orderWorkflowService->updateOrderStatus($order, 'cancelled');
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], 409);
        }

        return $this->json([
            'message' => 'Order cancelled successfully.',
            ...$this->serializeOrder($order),
        ]);
    }

    #[Route('/profile/{customerId}', name: 'api_customer_profile', methods: ['GET'])]
    public function profile(int $customerId, CustomerRepository $customerRepository): JsonResponse
    {
        $customer = $customerRepository->find($customerId);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found.'], 404);
        }

        return $this->json([
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'total_orders' => $customer->getOrders()->count(),
        ]);
    }

    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'order_id' => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'total' => $order->getTotal(),
            'status' => $order->getStatus(),
            'payment_method' => $order->getPaymentMethod(),
            'gcash_number' => $order->getGcashNumber(),
            'card_type' => $order->getCardType(),
            'created_at' => $order->getCreatedAt()?->format('Y-m-d H:i:s'),
            'items' => array_map(
                fn(OrderItem $orderItem) => $this->serializeOrderItem($orderItem),
                $order->getOrderItems()->toArray()
            ),
        ];
    }

    private function serializeOrderItem(OrderItem $orderItem): array
    {
        return [
            'id' => $orderItem->getId(),
            'product_id' => $orderItem->getProduct()?->getId(),
            'product_name' => $orderItem->getProduct()?->getName(),
            'quantity' => $orderItem->getQuantity(),
            'unit_price' => $orderItem->getUnitPrice(),
            'line_total' => $orderItem->getLineTotal(),
        ];
    }
}