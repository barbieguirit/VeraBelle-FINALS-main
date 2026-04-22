<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Customer;
use App\Entity\Payment;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\CustomerRepository;
use App\Service\CartService;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ShopCheckoutController handles customer shopping cart and checkout flow
 */
class ShopCheckoutController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
        private CustomerRepository $customerRepository
    ) {}

    /**
     * View shopping cart
     */
    #[Route('/cart', name: 'app_cart', methods: ['GET'])]
    public function viewCart(Request $request): Response
    {
        $cart = $this->cartService->getCart();
        $total = $this->cartService->getTotal();
        $itemCount = $this->cartService->getItemCount();

        // Return JSON for AJAX requests
        if ($request->getPreferredFormat() === 'json' || $request->headers->get('Accept') === 'application/json') {
            return $this->json([
                'cart' => $cart,
                'total' => $total,
                'itemCount' => $itemCount,
            ]);
        }

        return $this->render('shop/cart.html.twig', [
            'cart' => $cart,
            'total' => $total,
            'itemCount' => $itemCount,
        ]);
    }

    /**
     * Add product to cart (AJAX)
     */
    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function addToCart(
        Request $request,
        Product $product,
        ProductRepository $productRepository
    ): Response {
        $quantity = max(1, (int) $request->request->get('quantity', 1));

        // Validate stock
        if ($product->getStock() < $quantity) {
            return $this->json(['error' => 'Not enough stock available'], 400);
        }

        $this->cartService->addToCart(
            $product->getId(),
            $product->getName(),
            (float) $product->getPrice(),
            $quantity,
            $product->getImage()
        );

        return $this->json([
            'success' => true,
            'message' => sprintf('%s added to cart', $product->getName()),
            'cartCount' => $this->cartService->getItemCount(),
            'cartTotal' => $this->cartService->getTotal(),
        ]);
    }

    /**
     * Remove product from cart
     */
    #[Route('/cart/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function removeFromCart(int $id): Response
    {
        $this->cartService->removeFromCart($id);

        return $this->json([
            'success' => true,
            'cartCount' => $this->cartService->getItemCount(),
            'cartTotal' => $this->cartService->getTotal(),
        ]);
    }

    /**
     * Update product quantity in cart
     */
    #[Route('/cart/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function updateCart(Request $request, int $id): Response
    {
        $quantity = (int) $request->request->get('quantity', 1);

        if ($quantity < 1) {
            $this->cartService->removeFromCart($id);
        } else {
            $this->cartService->updateQuantity($id, $quantity);
        }

        return $this->json([
            'success' => true,
            'cartCount' => $this->cartService->getItemCount(),
            'cartTotal' => $this->cartService->getTotal(),
        ]);
    }

    /**
     * Checkout page - requires login
     */
    #[Route('/checkout', name: 'app_checkout', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkout(Request $request): Response
    {
        $fullCart = $this->cartService->getCart();

        // Filter by selected IDs if passed from cart page
        $selected = $request->get('selected', '');
        if ($selected) {
            $selectedIds = array_map('intval', explode(',', $selected));
            $cart = array_filter($fullCart, fn($item) => in_array($item['id'], $selectedIds));
        } else {
            $cart = $fullCart;
        }

        $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));
        $itemCount = array_sum(array_column($cart, 'quantity'));

        if (empty($cart)) {
            $this->addFlash('error', 'Your cart is empty. Please add items before checking out.');
            return $this->redirectToRoute('app_shop');
        }

        if ($request->isMethod('POST')) {
            try {
                $selected = $request->request->get('selected', '');
                if ($selected) {
                    $selectedIds = array_map('intval', explode(',', $selected));
                    $cart = array_filter($fullCart, fn($item) => in_array($item['id'], $selectedIds));
                }
                $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));
                // Get customer info from form
                $firstName = trim($request->request->get('firstName', ''));
                $lastName = trim($request->request->get('lastName', ''));
                $email = trim($request->request->get('email', ''));
                $phone = trim($request->request->get('phone', ''));
                $address = trim($request->request->get('address', ''));
                $paymentMethod = trim($request->request->get('paymentMethod', 'cod'));

                // Validation
                if (empty($firstName) || empty($email) || empty($phone) || empty($address)) {
                    $this->addFlash('error', 'Please fill in all required fields');
                    return $this->redirectToRoute('app_checkout');
                }

                // Create order from cart
                $order = new Order();
                $order->setCustomerName($firstName . ' ' . $lastName);
                $order->setCustomerEmail($email);
                $order->setCustomerPhone($phone);
                $order->setShippingAddress($address);
                $order->setTotalAmount($total);
                $order->setPaymentStatus('pending');
                $order->setOrderStatus('pending');
                $order->setPaymentMethod($paymentMethod);
                $order->setCreatedBy($this->getUser());

                $this->entityManager->persist($order);
                $this->entityManager->flush();

                // Deduct stock for each ordered product
                foreach ($cart as $item) {
                    $product = $this->entityManager->getRepository(Product::class)->find($item['id']);
                    if ($product) {
                        $newStock = max(0, $product->getStock() - $item['quantity']);
                        $product->setStock($newStock);
                    }
                }
                $this->entityManager->flush();

                // Sync to Customer table for dashboard visibility
                $customer = $this->customerRepository->findOneBy(['email' => $email]);
                if (!$customer) {
                    $customer = new Customer();
                    $customer->setEmail($email);
                    $customer->setFullName($firstName . ' ' . $lastName);
                    $customer->setPhoneNumber($phone ?: null);
                    $customer->setShippingAddress($address ?: null);
                    $customer->setStatus('Active');
                    $customer->setTotalOrders(1);
                    $customer->setTotalSpent($total);
                    $this->entityManager->persist($customer);
                } else {
                    $customer->setTotalOrders($customer->getTotalOrders() + 1);
                    $customer->setTotalSpent($customer->getTotalSpent() + $total);
                }
                $this->entityManager->flush();

                // Auto-create Payment record
                $payment = new Payment();
                $payment->setOrder($order);
                $payment->setCustomer($customer);
                $payment->setAmount($total);
                $payment->setMethod($paymentMethod);
                $payment->setStatus($paymentMethod === 'cod' ? 'Pending' : 'Pending');
                $payment->setDate(new \DateTime());
                $this->entityManager->persist($payment);
                $this->entityManager->flush();

                // Log activity
                $this->activityLogger->logCreate(
                    'Order',
                    $order->getId(),
                    $order->getCustomerName(),
                    sprintf('Items: %d, Total: ₱%.2f', $this->cartService->getItemCount(), $order->getTotalAmount())
                );

                // Clear cart after successful order
                $this->cartService->clearCart();

                $this->addFlash('success', 'Order created successfully! View your orders in your account.');
                return $this->redirectToRoute('app_my_orders');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating order. Please try again.');
                return $this->redirectToRoute('app_checkout');
            }
        }

        return $this->render('shop/checkout.html.twig', [
            'cart' => $cart,
            'total' => $total,
            'itemCount' => $itemCount,
            'cartEmpty' => empty($cart),
            'selected' => $selected,
        ]);
    }

    /**
     * Customer order history - requires login
     */
    #[Route('/my-orders', name: 'app_my_orders', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myOrders(): Response
    {
        $user = $this->getUser();
        $orders = $this->entityManager->getRepository(Order::class)->findBy(
            ['createdBy' => $user],
            ['orderDate' => 'DESC']
        );

        $totalSpent = array_sum(array_map(fn($o) => (float) $o->getTotalAmount(), $orders));

        return $this->render('shop/my-orders.html.twig', [
            'orders' => $orders,
            'totalSpent' => $totalSpent,
        ]);
    }

    /**
     * View single order details
     */
    #[Route('/my-orders/{id}', name: 'app_order_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function orderDetail(Order $order): Response
    {
        // Check if order belongs to current user
        if ($order->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot view this order');
        }

        return $this->render('shop/order-detail.html.twig', [
            'order' => $order,
        ]);
    }
}
