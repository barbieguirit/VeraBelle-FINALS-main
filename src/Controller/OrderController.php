<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/order')]
#[IsGranted('ROLE_STAFF')]
class OrderController extends AbstractController
{
    private ActivityLogger $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    #[Route('/', name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Admin sees all orders, Staff sees only their own
        if ($isAdmin) {
            $orders = $orderRepository->findAll();
        } else {
            $orders = $orderRepository->findBy(['createdBy' => $user]);
        }
        
        // Calculate statistics
        $totalOrders = count($orders);
        $pendingOrders = count(array_filter($orders, fn($o) => strtolower($o->getOrderStatus()) === 'pending'));
        $completedOrders = count(array_filter($orders, fn($o) => strtolower($o->getOrderStatus()) === 'completed'));
        
        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'stats' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'completed' => $completedOrders,
            ]
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order->setCreatedBy($this->getUser());
            $entityManager->persist($order);
            $entityManager->flush();

            // Log the activity
            $this->activityLogger->log(
                'CREATE',
                sprintf('Order #%d for %s - ₱%s', 
                    $order->getId(), 
                    $order->getCustomerName(), 
                    number_format($order->getTotalAmount(), 2)
                )
            );

            $this->addFlash('success', 'Order created successfully!');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        // Check if user has permission to view this order
        $this->checkOrderAccess($order);

        return $this->render('order/show.html.twig', [
            'order' => $order
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        // Check if user has permission to edit this order
        $this->checkOrderAccess($order);

        // Prevent editing completed/cancelled orders
        $status = strtolower($order->getOrderStatus());
        if (in_array($status, ['completed', 'cancelled'])) {
            $this->addFlash('error', 'Completed or cancelled orders cannot be edited.');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Log the activity
            $this->activityLogger->log(
                'UPDATE',
                sprintf('Order #%d for %s - ₱%s', 
                    $order->getId(), 
                    $order->getCustomerName(), 
                    number_format($order->getTotalAmount(), 2)
                )
            );

            $this->addFlash('success', 'Order updated successfully!');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        // Check if user has permission to delete this order
        $this->checkOrderAccess($order);

        // Prevent deleting completed orders
        $status = strtolower($order->getOrderStatus());
        if ($status === 'completed') {
            $this->addFlash('error', 'Completed orders cannot be deleted.');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            // Store order info before deletion
            $orderInfo = sprintf('Order #%d for %s - ₱%s', 
                $order->getId(), 
                $order->getCustomerName(), 
                number_format($order->getTotalAmount(), 2)
            );

            $entityManager->remove($order);
            $entityManager->flush();

            // Log the activity
            $this->activityLogger->log('DELETE', $orderInfo);
            
            $this->addFlash('success', 'Order deleted successfully!');
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Check if the current user has access to this order
     */
    private function checkOrderAccess(Order $order): void
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Admin can access all orders
        if ($isAdmin) {
            return;
        }

        // Staff can only access their own orders
        if ($order->getCreatedBy() !== $user) {
            throw $this->createAccessDeniedException('You do not have permission to access this order.');
        }
    }
}