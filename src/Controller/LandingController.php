<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Repository\ChallengeRepository;

class LandingController extends AbstractController
{
    // ─── Admin email loaded from .env ────────────────────────────────────────
    private string $adminEmail;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->adminEmail = $_ENV['ADMIN_EMAIL']        ?? 'admin@verabellecollection.com';
        $this->fromEmail  = $_ENV['MAILER_FROM_EMAIL']  ?? 'hello@verabellecollection.com';
        $this->fromName   = $_ENV['MAILER_FROM_NAME']   ?? 'VeraBelle Collection';
    }

    // =========================================================================
    // PUBLIC LANDING PAGE — visible at /
    // Redirects logged-in users to their correct dashboard
    // =========================================================================
    #[Route('/', name: 'app_landing', priority: 10)]
    public function index(ChallengeRepository $challengeRepo, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
      $challenges = [];
      $products = [];

      try {
        $challenges = $challengeRepo->findBy(['status' => 'active'], ['createdAt' => 'DESC'], 3);

        // Show specific featured products on landing page
        $featuredNames = ['Noir Essence Top', 'Chérie Flow Dress', 'Midnight Allure', 'Ivory Muse Dress', 'Velvet Reverie Dress'];
        $products = $em->getRepository(\App\Entity\Product::class)->createQueryBuilder('p')
          ->where('p.name IN (:names)')
          ->setParameter('names', $featuredNames)
          ->getQuery()
          ->getResult();

        // Fallback to latest products if none of the featured ones exist
        if (empty($products)) {
          $products = $em->getRepository(\App\Entity\Product::class)->findBy([], ['createdAt' => 'DESC'], 5);
        }
      } catch (\Throwable $e) {
        error_log('[VeraBelle Landing Error] ' . $e->getMessage());
      }

      return $this->render('landing/index.html.twig', [
          'challenges' => $challenges,
          'products'   => $products,
      ]);
    }

    // =========================================================================
    // ABOUT US PAGE
    // =========================================================================
    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('landing/about.html.twig');
    }

    // =========================================================================
    // CONTACT FORM — POST handler
    // Sends email via Brevo SMTP (configured in MAILER_DSN)
    // Also hits Brevo Transactional API for reliable delivery tracking
    // =========================================================================
    #[Route('/contact', name: 'app_contact_page', methods: ['GET'])]
    public function contactPage(): Response
    {
        return $this->render('landing/contact.html.twig');
    }

    #[Route('/contact', name: 'app_contact', methods: ['POST'])]
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        // ── Honeypot bot check ────────────────────────────────────────────────
        if ($request->request->get('website', '') !== '') {
            return $this->redirectToRoute('app_landing');
        }

        // ── Sanitize inputs ───────────────────────────────────────────────────
        $firstName = strip_tags(trim($request->request->get('firstName', '')));
        $lastName  = strip_tags(trim($request->request->get('lastName',  '')));
        $email     = filter_var(trim($request->request->get('email', '')), FILTER_SANITIZE_EMAIL);
        $phone     = strip_tags(trim($request->request->get('phone',    '')));
        $subject   = strip_tags(trim($request->request->get('subject',  'General Inquiry')));
        $message   = strip_tags(trim($request->request->get('message',  '')));

        // ── Basic validation ──────────────────────────────────────────────────
        if (empty($firstName) || empty($email) || empty($message)) {
            $this->addFlash('contact_error', 'Please fill in all required fields (First Name, Email, Message).');
            return $this->redirectToRoute('app_contact_page');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('contact_error', 'Please enter a valid email address.');
            return $this->redirectToRoute('app_contact_page');
        }

        $fullName   = $firstName . ($lastName ? ' ' . $lastName : '');
        $timestamp  = (new \DateTimeImmutable())->format('M d, Y — H:i');

        try {
            // ── 1. Email to ADMIN (via Brevo SMTP) ────────────────────────────
            $adminMail = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail))
                ->replyTo(new Address($email, $fullName))
                ->subject('[VeraBelle Contact] ' . $subject . ' — ' . $fullName)
                ->html($this->buildAdminEmail(
                    $fullName, $email, $phone, $subject, $message, $timestamp
                ));

            $mailer->send($adminMail);

            // ── 2. Auto-reply to SENDER (via Brevo SMTP) ─────────────────────
            $autoReply = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($email, $fullName))
                ->subject('Thank you for reaching out — VeraBelle Collection')
                ->html($this->buildAutoReplyEmail($fullName, $subject));

            $mailer->send($autoReply);

            // ── 3. Brevo Transactional API (optional — for analytics/tracking) ──
            $this->sendViaBrevoApi($fullName, $email, $subject, $message, $phone);

            $this->addFlash(
                'contact_success',
                "Thank you, {$firstName}! Your message has been sent. We'll get back to you within 24 hours."
            );

        } catch (\Throwable $e) {
            // Log the error but don't expose details to the user
            error_log('[VeraBelle Contact Error] ' . $e->getMessage());

            $this->addFlash(
                'contact_error',
                'Something went wrong sending your message. Please try again or email us directly at hello@verabellecollection.com'
            );
        }

        return $this->redirectToRoute('app_contact_page');
    }

    // =========================================================================
    // BREVO TRANSACTIONAL API CALL
    // Sends via Brevo REST API — gives access to delivery analytics in dashboard
    // Requires: BREVO_API_KEY in .env
    // =========================================================================
    private function sendViaBrevoApi(
        string $name,
        string $email,
        string $subject,
        string $message,
        string $phone = ''
    ): void {
        $apiKey = $_ENV['BREVO_API_KEY'] ?? '';

        if (empty($apiKey)) {
            return; // API key not set — skip silently
        }

        $payload = [
            'sender'     => [
                'name'  => $this->fromName,
                'email' => $this->fromEmail,
            ],
            'to' => [
                ['email' => $this->adminEmail, 'name' => 'VeraBelle Admin'],
            ],
            'replyTo' => ['email' => $email, 'name' => $name],
            'subject' => '[API] Contact Form: ' . $subject . ' — ' . $name,
            'htmlContent' => $this->buildAdminEmail(
                $name, $email, $phone, $subject, $message,
                (new \DateTimeImmutable())->format('M d, Y — H:i')
            ),
        ];

        // Non-blocking cURL — fails silently if network is unavailable
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $apiKey,
                'content-type: application/json',
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // =========================================================================
    // EMAIL TEMPLATES — branded, inline CSS (mail-safe)
    // =========================================================================

    private function buildAdminEmail(
        string $name,
        string $email,
        string $phone,
        string $subject,
        string $message,
        string $timestamp
    ): string {
        $msgHtml  = nl2br(htmlspecialchars($message));
        $phoneRow = $phone
            ? "<tr><td style='padding:12px 0;border-bottom:1px solid #F0EBE2;color:#8C8479;font-size:11px;letter-spacing:1px;text-transform:uppercase;width:28%'>Phone</td><td style='padding:12px 0;border-bottom:1px solid #F0EBE2;color:#1A1A18;font-size:14px'>{$phone}</td></tr>"
            : '';

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Contact Notification</title></head>
<body style="font-family:'DM Sans',Arial,sans-serif;background:#F5F0E8;padding:40px 20px;margin:0">
<div style="max-width:600px;margin:0 auto">

  <!-- Header -->
  <div style="background:#2D4A3E;padding:40px 44px;margin-bottom:0">
    <p style="font-family:Georgia,serif;font-size:26px;font-weight:400;color:#F5F0E8;margin:0;letter-spacing:0.04em">VeraBelle <em style="color:#E8C4B0;font-style:italic">Collection</em></p>
    <p style="font-size:10px;letter-spacing:0.3em;text-transform:uppercase;color:rgba(245,240,232,0.5);margin:8px 0 0">New Contact Form Submission</p>
  </div>

  <!-- Body -->
  <div style="background:#ffffff;padding:44px;border-left:1px solid #E8E0D4;border-right:1px solid #E8E0D4">
    <table style="width:100%;border-collapse:collapse">
      <tr>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;color:#8C8479;font-size:11px;letter-spacing:1px;text-transform:uppercase;width:28%">From</td>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;color:#1A1A18;font-size:14px;font-weight:500">{$name}</td>
      </tr>
      <tr>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;color:#8C8479;font-size:11px;letter-spacing:1px;text-transform:uppercase">Email</td>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;font-size:14px"><a href="mailto:{$email}" style="color:#C4714A;text-decoration:none">{$email}</a></td>
      </tr>
      {$phoneRow}
      <tr>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;color:#8C8479;font-size:11px;letter-spacing:1px;text-transform:uppercase">Subject</td>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;color:#1A1A18;font-size:14px">{$subject}</td>
      </tr>
      <tr>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;color:#8C8479;font-size:11px;letter-spacing:1px;text-transform:uppercase">Sent</td>
        <td style="padding:12px 0;border-bottom:1px solid #F0EBE2;color:#8C8479;font-size:13px">{$timestamp}</td>
      </tr>
    </table>

    <div style="margin-top:32px">
      <p style="font-size:10px;letter-spacing:0.25em;text-transform:uppercase;color:#C4714A;margin-bottom:14px">Message</p>
      <div style="background:#F5F0E8;padding:24px;border-left:3px solid #C4714A;font-size:14px;color:#3D3530;line-height:1.85;font-weight:300">{$msgHtml}</div>
    </div>

    <div style="margin-top:32px;padding-top:24px;border-top:1px solid #F0EBE2">
      <a href="mailto:{$email}?subject=Re: {$subject}" style="background:#2D4A3E;color:#F5F0E8;padding:14px 32px;font-size:10px;letter-spacing:0.22em;text-transform:uppercase;text-decoration:none;display:inline-block">Reply to {$name}</a>
    </div>
  </div>

  <!-- Footer -->
  <div style="background:#F5F0E8;padding:20px 44px;border:1px solid #E8E0D4;border-top:none;text-align:center">
    <p style="font-size:11px;color:#8C8479;margin:0">VeraBelle Collection · Dumaguete City, Philippines · hello@verabellecollection.com</p>
  </div>

</div>
</body></html>
HTML;
    }

    private function buildAutoReplyEmail(string $name, string $subject): string
    {
        $firstName = explode(' ', $name)[0];

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Thank You</title></head>
<body style="font-family:'DM Sans',Arial,sans-serif;background:#F5F0E8;padding:40px 20px;margin:0">
<div style="max-width:560px;margin:0 auto">

  <!-- Hero -->
  <div style="background:#2D4A3E;padding:56px 44px;text-align:center">
    <p style="font-family:Georgia,serif;font-size:30px;font-weight:400;color:#F5F0E8;margin:0 0 6px;letter-spacing:0.04em">VeraBelle</p>
    <p style="font-family:Georgia,serif;font-style:italic;font-size:16px;color:#E8C4B0;margin:0;letter-spacing:0.1em">Collection</p>
    <div style="width:40px;height:1px;background:rgba(196,113,74,0.5);margin:24px auto"></div>
    <p style="font-size:10px;letter-spacing:0.3em;text-transform:uppercase;color:rgba(245,240,232,0.45);margin:0">Message Received</p>
  </div>

  <!-- Body -->
  <div style="background:#ffffff;padding:52px 44px;border-left:1px solid #E8E0D4;border-right:1px solid #E8E0D4">
    <p style="font-family:Georgia,serif;font-style:italic;font-size:26px;color:#2D4A3E;margin:0 0 24px">Hello, {$firstName}!</p>
    <p style="font-size:15px;font-weight:300;color:#8C8479;line-height:1.9;margin-bottom:18px">
      Thank you for getting in touch about <strong style="color:#1A1A18;font-weight:500">{$subject}</strong>.
      We've received your message and a member of our team will get back to you
      within <strong style="color:#1A1A18">24 hours</strong>.
    </p>
    <p style="font-size:14px;font-weight:300;color:#8C8479;line-height:1.9;margin-bottom:36px">
      In the meantime, explore our latest pieces or follow us on Instagram
      for daily style inspiration.
    </p>

    <div style="text-align:center;margin:36px 0">
      <a href="https://verabellecollection.com/#products" style="background:#C4714A;color:#F5F0E8;padding:16px 44px;font-size:10px;letter-spacing:0.22em;text-transform:uppercase;text-decoration:none;display:inline-block">Browse the Collection</a>
    </div>

    <div style="width:48px;height:1px;background:#C4714A;margin:36px auto"></div>
    <p style="font-family:Georgia,serif;font-style:italic;font-size:18px;color:#C4714A;text-align:center;margin:0">With warmth,</p>
    <p style="font-family:Georgia,serif;font-size:22px;color:#2D4A3E;text-align:center;margin:8px 0 0;letter-spacing:0.04em">VeraBelle Collection</p>
  </div>

  <!-- Footer -->
  <div style="background:#F5F0E8;padding:20px 44px;border:1px solid #E8E0D4;border-top:none;text-align:center">
    <p style="font-size:11px;color:#8C8479;margin:0 0 6px">Dumaguete City, Negros Oriental, Philippines</p>
    <p style="font-size:11px;color:#8C8479;margin:0">hello@verabellecollection.com · +63 912 345 6789</p>
  </div>

</div>
</body></html>
HTML;
    }
}