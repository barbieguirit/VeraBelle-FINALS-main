<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/customer')]
#[IsGranted('ROLE_CUSTOMER')]
class CustomerApiController extends AbstractController
{
    #[Route('/me', name: 'api_customer_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
                'roleType' => 'customer',
            ],
        ]);
    }

    #[Route('/products', name: 'api_customer_products', methods: ['GET'])]
    public function products(ProductRepository $productRepository): JsonResponse
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
                    'category' => $product->getCategory()?->getName(),
                ];
            },
            $productRepository->findBy([], ['createdAt' => 'DESC'], 20)
        );

        return $this->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    #[Route('/orders', name: 'api_customer_orders', methods: ['GET'])]
    public function orders(#[CurrentUser] ?User $user, OrderRepository $orderRepository): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $orders = $orderRepository->createQueryBuilder('o')
            ->andWhere('o.customerEmail = :email')
            ->setParameter('email', $user->getEmail())
            ->orderBy('o.orderDate', 'DESC')
            ->getQuery()
            ->getResult();

        $items = array_map(
            static function (Order $order): array {
                return [
                    'id' => $order->getId(),
                    'customerName' => $order->getCustomerName(),
                    'customerEmail' => $order->getCustomerEmail(),
                    'totalAmount' => $order->getTotalAmount(),
                    'paymentStatus' => $order->getPaymentStatus(),
                    'orderStatus' => $order->getOrderStatus(),
                    'orderDate' => $order->getOrderDate()?->format(DATE_ATOM),
                    'paymentMethod' => $order->getPaymentMethod(),
                ];
            },
            $orders
        );

        return $this->json([
            'success' => true,
            'count' => count($items),
            'data' => $items,
        ]);
    }
}
