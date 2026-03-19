<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payment')]
#[IsGranted('ROLE_STAFF')]
class PaymentController extends AbstractController
{
    public function __construct(private ActivityLogger $activityLogger) {}
    
    #[Route('/', name: 'app_payment_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $payments = $em->getRepository(Payment::class)->findBy([], ['date' => 'DESC']);
        // Example stats, replace with actual calculations
        $stats = [
            'total' => count($payments),
            'pending' => count(array_filter($payments, fn($p) => $p->getStatus() === 'Pending')),
            'failed' => count(array_filter($payments, fn($p) => in_array($p->getStatus(), ['Failed', 'Refunded']))),
            'revenue' => array_sum(array_map(fn($p) => $p->getStatus() === 'Paid' ? $p->getAmount() : 0, $payments)),
            'avg' => count($payments) ? array_sum(array_map(fn($p) => $p->getAmount(), $payments)) / count($payments) : 0,
        ];

        return $this->render('payment/index.html.twig', [
            'payments' => $payments,
            'stats' => $stats,
        ]);
    }

    #[Route('/new', name: 'app_payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload if needed
            $em->persist($payment);
            $em->flush();
            $this->addFlash('success', '✅ Payment record added successfully!');
            return $this->redirectToRoute('app_payment_index');
        }

        return $this->render('payment/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_payment_show', methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_payment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Payment $payment, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', '✅ Payment updated successfully!');
            return $this->redirectToRoute('app_payment_index');
        }

        return $this->render('payment/edit.html.twig', [
            'payment' => $payment,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_payment_delete', methods: ['POST'])]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$payment->getId(), $request->request->get('_token'))) {
            $em->remove($payment);
            $em->flush();
            $this->addFlash('success', '🗑️ Payment deleted successfully!');
        }

        return $this->redirectToRoute('app_payment_index');
    }

    #[Route('/{id}/verify', name: 'app_payment_verify', methods: ['GET', 'POST'])]
    public function verify(Payment $payment, EntityManagerInterface $em): Response
    {
        $payment->setStatus('Paid');
        $em->flush();
        $this->addFlash('success', '✅ Payment verified!');
        return $this->redirectToRoute('app_payment_index');
    }

    #[Route('/{id}/refund', name: 'app_payment_refund', methods: ['GET', 'POST'])]
    public function refund(Payment $payment, EntityManagerInterface $em): Response
    {
        $payment->setStatus('Refunded');
        $em->flush();
        $this->addFlash('success', '💸 Payment marked as refunded.');
        return $this->redirectToRoute('app_payment_index');
    }
}