<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\Product;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mobile')]
class MobileOrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityLogger $activityLogger,
    ) {}

    #[Route('/products', name: 'api_mobile_products', methods: ['GET'])]
    public function products(): JsonResponse
    {
        $items = array_map(
            static function (Product $product): array {
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
            },
            $this->em->getRepository(Product::class)->findBy([], ['createdAt' => 'DESC'])
        );

        return $this->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
            'products' => $items,
        ]);
    }

    #[Route('/orders', name: 'api_mobile_order_create', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $items = $data['items'] ?? [];

        if (empty($items)) {
            return $this->json(['message' => 'Cart is empty'], 400);
        }

        $subtotal = array_reduce(
            $items,
            fn($sum, $i) => $sum + (floatval($i['price']) * intval($i['qty'])),
            0.0
        );
        $shipping = 6.0;
        $total = $subtotal + $shipping;
        $customerName = $user->getEmail() ?? 'Mobile Customer';

        $order = new Order();

        $order->setCustomerName($customerName);
        $order->setCustomerEmail($user->getEmail());
        $order->setOrderStatus('pending');
        $order->setTotalAmount($total);
        $order->setOrderDate(new \DateTimeImmutable());
        $order->setCreatedBy($user);

        if (method_exists($order, 'setItemsData')) {
            $order->setItemsData(json_encode($items));
        }

        $this->em->persist($order);
        $this->em->flush();

        $this->activityLogger->log(
            'CREATE',
            sprintf(
                'Mobile Order #%d for %s - ₱%s',
                $order->getId(),
                $order->getCustomerName(),
                number_format($total, 2)
            )
        );

        return $this->json([
            'id' => $order->getId(),
            'total' => $total,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'status' => $order->getOrderStatus(),
        ], 201);
    }

    #[Route('/orders', name: 'api_mobile_orders_list', methods: ['GET'])]
    public function myOrders(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $orders = $this->em->getRepository(Order::class)
            ->findBy(['createdBy' => $user], ['orderDate' => 'DESC']);

        $result = array_map(fn($o) => [
            'id' => $o->getId(),
            'status' => $o->getOrderStatus(),
            'total' => $o->getTotalAmount(),
            'date' => $o->getOrderDate()?->format('Y-m-d H:i'),
        ], $orders);

        return $this->json($result);
    }
}