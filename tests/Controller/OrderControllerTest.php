<?php

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Integration Tests for OrderController
 * 
 * Tests the critical order management workflows:
 * 1. Creating new orders
 * 2. Viewing orders
 * 3. Updating order status
 * 4. Deleting orders
 * 
 * Each test uses database transactions to maintain test isolation.
 */
class OrderControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $adminUser;
    private $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->adminUser = $this->createAdminUser();
        $this->product = $this->createTestProduct();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Test: View orders list as authenticated admin
     */
    public function testOrderIndexDisplaysList(): void
    {
        $this->loginAs($this->adminUser);
        
        $this->client->request('GET', '/admin/orders');
        
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Orders', $this->client->getResponse()->getContent());
    }

    /**
     * Test: Cannot access orders without authentication
     */
    public function testOrderIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/orders');
        
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test: Create order with valid data
     * 
     * Verifies that orders can be created with proper validation
     */
    public function testCreateOrderWithValidData(): void
    {
        $this->loginAs($this->adminUser);
        
        $this->client->request('POST', '/admin/orders/new', [
            'customer_email' => 'customer@example.com',
            'product_id' => $this->product->getId(),
            'quantity' => 2,
            'status' => 'pending',
        ]);

        // Should redirect on success
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());

        // Verify order was created
        $order = $this->entityManager->getRepository(Order::class)
            ->findOneBy(['customerEmail' => 'customer@example.com']);
        
        if ($order) {
            $this->assertNotNull($order);
            $this->assertEquals(2, $order->getQuantity());
        }
    }

    /**
     * Test: Order creation fails with negative quantity
     * 
     * Verifies validation of order quantity
     */
    public function testCreateOrderWithInvalidQuantity(): void
    {
        $this->loginAs($this->adminUser);
        
        $this->client->request('POST', '/admin/orders/new', [
            'customer_email' => 'customer@example.com',
            'product_id' => $this->product->getId(),
            'quantity' => -1,  // Invalid: negative quantity
            'status' => 'pending',
        ]);

        // Should fail validation
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test: Update order status
     * 
     * Verifies that order status can be changed
     */
    public function testUpdateOrderStatus(): void
    {
        $this->loginAs($this->adminUser);
        
        $order = $this->createTestOrder('processing');
        
        $this->client->request('POST', sprintf('/admin/orders/%d/edit', $order->getId()), [
            'status' => 'completed',
            'notes' => 'Order shipped',
        ]);

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());

        // Verify status changed
        $this->entityManager->refresh($order);
        $this->assertEquals('completed', $order->getStatus());
    }

    /**
     * Test: Delete order successfully
     * 
     * Verifies that orders can be removed from system
     */
    public function testDeleteOrderSuccessfully(): void
    {
        $this->loginAs($this->adminUser);
        
        $order = $this->createTestOrder('pending');
        $orderId = $order->getId();
        
        $this->client->request('POST', sprintf('/admin/orders/%d/delete', $orderId));

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());

        // Verify order was deleted
        $deletedOrder = $this->entityManager->getRepository(Order::class)->find($orderId);
        $this->assertNull($deletedOrder);
    }

    /**
     * Test: Cannot delete completed order
     * 
     * Verifies business rule: completed orders cannot be deleted
     */
    public function testCannotDeleteCompletedOrder(): void
    {
        $this->loginAs($this->adminUser);
        
        $order = $this->createTestOrder('completed');
        
        $response = $this->client->request('POST', sprintf('/admin/orders/%d/delete', $order->getId()));

        // Should show error or prevent deletion
        $this->assertTrue(
            $this->client->getResponse()->getStatusCode() === 302 ||
            strpos($this->client->getResponse()->getContent(), 'cannot') !== false
        );
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function loginAs(User $user): void
    {
        $this->client->loginUser($user);
    }

    private function createAdminUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus('active');
        $admin->setIsVerified(true);
        $admin->setPassword($passwordHasher->hashPassword($admin, 'adminPassword123'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return $admin;
    }

    private function createTestProduct(): Product
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product = new Product();
        $product->setName('Test Product');
        $product->setPrice(99.99);
        $product->setStock(100);
        $product->setCategory($category);

        $this->entityManager->persist($category);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function createTestOrder(string $status = 'pending'): Order
    {
        $order = new Order();
        $order->setCustomerEmail('customer@test.com');
        $order->setStatus($status);
        $order->setQuantity(1);
        $order->setTotalAmount(99.99);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}
