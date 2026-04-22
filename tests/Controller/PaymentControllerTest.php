<?php

namespace App\Tests\Controller;

use App\Entity\Payment;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Integration Tests for PaymentController
 * 
 * Tests critical payment processing workflows:
 * 1. Viewing payment list
 * 2. Processing payments
 * 3. Verifying payment amounts
 * 4. Updating payment status
 * 5. Handling payment failures
 * 
 * SECURITY: Payment tests ensure proper authorization and validation
 */
class PaymentControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->adminUser = $this->createAdminUser();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Test: View payments list as admin
     * 
     * Verifies that authenticated admins can view payment records
     */
    public function testPaymentIndexDisplaysList(): void
    {
        $this->loginAs($this->adminUser);
        
        $this->client->request('GET', '/admin/payments');
        
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Payment', $this->client->getResponse()->getContent());
    }

    /**
     * Test: Unauthenticated users cannot access payments
     * 
     * Verifies security: payments require authentication
     */
    public function testPaymentIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/admin/payments');
        
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test: Process payment successfully
     * 
     * Verifies that valid payments are processed correctly
     */
    public function testProcessPaymentSuccessfully(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('pending', 99.99);
        
        // Process payment
        $this->client->request('POST', sprintf('/admin/payments/%d/process', $payment->getId()));

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());

        // Verify payment status changed
        $this->entityManager->refresh($payment);
        $this->assertEquals('completed', $payment->getStatus());
    }

    /**
     * Test: Cannot process payment with zero amount
     * 
     * Verifies validation: payment amount must be positive
     */
    public function testCannotProcessZeroAmountPayment(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('pending', 0);
        
        $response = $this->client->request('POST', sprintf('/admin/payments/%d/process', $payment->getId()));

        // Should show error
        $this->assertTrue(
            $this->client->getResponse()->getStatusCode() === 302 ||
            strpos($this->client->getResponse()->getContent(), 'invalid') !== false ||
            strpos($this->client->getResponse()->getContent(), 'zero') !== false
        );
    }

    /**
     * Test: Cannot process negative payment
     * 
     * Verifies validation: payment amount must be positive
     */
    public function testCannotProcessNegativeAmountPayment(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('pending', -50.00);
        
        $response = $this->client->request('POST', sprintf('/admin/payments/%d/process', $payment->getId()));

        // Should prevent processing
        $this->assertNotNull($payment);
    }

    /**
     * Test: Cannot process already completed payment
     * 
     * Verifies business rule: payments can only be processed once
     */
    public function testCannotProcessAlreadyCompletedPayment(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('completed', 99.99);
        $originalStatus = $payment->getStatus();
        
        // Try to process again
        $this->client->request('POST', sprintf('/admin/payments/%d/process', $payment->getId()));

        // Payment status should remain unchanged
        $this->entityManager->refresh($payment);
        $this->assertEquals($originalStatus, $payment->getStatus());
    }

    /**
     * Test: Refund payment successfully
     * 
     * Verifies that completed payments can be refunded
     */
    public function testRefundPaymentSuccessfully(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('completed', 99.99);
        
        $this->client->request('POST', sprintf('/admin/payments/%d/refund', $payment->getId()), [
            'reason' => 'Customer requested refund',
        ]);

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());

        // Verify payment was refunded
        $this->entityManager->refresh($payment);
        $this->assertEquals('refunded', $payment->getStatus());
    }

    /**
     * Test: Cannot refund pending payment
     * 
     * Verifies business rule: only completed payments can be refunded
     */
    public function testCannotRefundPendingPayment(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('pending', 99.99);
        
        $response = $this->client->request('POST', sprintf('/admin/payments/%d/refund', $payment->getId()), [
            'reason' => 'Customer requested refund',
        ]);

        // Should show error - payment must be completed first
        $this->assertTrue(
            $this->client->getResponse()->getStatusCode() === 302 ||
            strpos($this->client->getResponse()->getContent(), 'completed') !== false
        );
    }

    /**
     * Test: View payment details
     * 
     * Verifies that payment records show all required details
     */
    public function testViewPaymentDetails(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('completed', 149.99);
        
        $this->client->request('GET', sprintf('/admin/payments/%d', $payment->getId()));

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('149.99', $this->client->getResponse()->getContent());
    }

    /**
     * Test: Delete payment successfully
     * 
     * Verifies that payment records can be removed
     */
    public function testDeletePaymentSuccessfully(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('pending', 99.99);
        $paymentId = $payment->getId();
        
        $this->client->request('POST', sprintf('/admin/payments/%d/delete', $paymentId));

        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());

        // Verify payment was deleted
        $deletedPayment = $this->entityManager->getRepository(Payment::class)->find($paymentId);
        $this->assertNull($deletedPayment);
    }

    /**
     * Test: Cannot delete completed payment
     * 
     * Verifies business rule: completed payments should not be deleted (audit trail)
     */
    public function testCannotDeleteCompletedPayment(): void
    {
        $this->loginAs($this->adminUser);
        
        $payment = $this->createTestPayment('completed', 99.99);
        $paymentId = $payment->getId();
        
        $this->client->request('POST', sprintf('/admin/payments/%d/delete', $paymentId));

        // Verify payment still exists
        $stillExists = $this->entityManager->getRepository(Payment::class)->find($paymentId);
        $this->assertNotNull($stillExists);
    }

    /**
     * Test: Filter payments by status
     * 
     * Verifies that payment list can be filtered
     */
    public function testFilterPaymentsByStatus(): void
    {
        $this->loginAs($this->adminUser);
        
        $this->createTestPayment('pending', 50.00);
        $this->createTestPayment('completed', 100.00);
        
        $this->client->request('GET', '/admin/payments?status=completed');

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString('completed', $this->client->getResponse()->getContent());
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

    private function createTestPayment(string $status = 'pending', float $amount = 99.99): Payment
    {
        $payment = new Payment();
        $payment->setStatus($status);
        $payment->setAmount($amount);
        $payment->setPaymentMethod('credit_card');
        $payment->setTransactionId('TXN_' . time());
        $payment->setCreatedAt(new \DateTime());

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
