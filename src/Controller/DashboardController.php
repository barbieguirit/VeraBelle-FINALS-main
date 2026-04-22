<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\CustomerRepository;
use App\Repository\ChallengeRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        CustomerRepository $customerRepository,
        ChallengeRepository $challengeRepository
    ): Response {
        // Redirect customers to product page
        if (!$this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_product_index');
        }
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

        // ✅ Total revenue (sum of all order amounts)
        $totalRevenue = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
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

        // ✅ Active challenges count
        $activeChallenges = count($challengeRepository->findBy(['status' => 'active']));

        return $this->render('dashboard/index.html.twig', [
            'totalProducts' => $totalProducts,
            'totalStocks' => $totalStocks,
            'totalOrders' => $totalOrders,
            'totalCustomers' => $totalCustomers,
            'totalRevenue' => $totalRevenue,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
            'activeChallenges' => $activeChallenges,
        ]);
    }
}
