<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\CustomerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_STAFF')]
    public function index(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        CustomerRepository $customerRepository
    ): Response {
        // ✅ Total products
        $totalProducts = $productRepository->count([]);

        // ✅ Total stocks (sum of all product stock)
        $totalStocks = $productRepository->createQueryBuilder('p')
            ->select('SUM(p.stock)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // ✅ Total orders
        $totalOrders = $orderRepository->count([]);

        // ✅ Total customers
        $totalCustomers = $customerRepository->count([]);

        // ✅ Total revenue (sum of all successful payment amounts)
        $totalRevenue = $paymentRepository->createQueryBuilder('pay')
            ->select('SUM(pay.amount)')
            ->where('pay.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // ✅ Top 3 products by stock (you can later change to best-selling)
        $topProducts = $productRepository->createQueryBuilder('p')
            ->orderBy('p.stock', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        // ✅ 5 most recent orders
        $recentOrders = $orderRepository->createQueryBuilder('o')
            ->orderBy('o.orderDate', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('dashboard/index.html.twig', [
            'totalProducts' => $totalProducts,
            'totalStocks' => $totalStocks,
            'totalOrders' => $totalOrders,
            'totalCustomers' => $totalCustomers,
            'totalRevenue' => $totalRevenue,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
        ]);
    }
}
