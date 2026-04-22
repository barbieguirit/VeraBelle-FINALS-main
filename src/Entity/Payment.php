<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 50)]
    private ?string $method = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amount = '0.00';

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionRef = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        // Generate unique reference if not set
        if (!$this->reference) {
            $this->reference = $this->generatePaymentReference();
        }
        
        // Set default date if not set
        if (!$this->date) {
            $this->date = new \DateTime();
        }
    }

    private function generatePaymentReference(): string
    {
        // Format: PAY-YYYYMMDD-HHMMSS-RANDOM
        $timestamp = date('Ymd-His');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return 'PAY-' . $timestamp . '-' . $random;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount === null ? null : (float) $this->amount;
    }

    public function getAmountRaw(): ?string
    {
        return $this->amount;
    }

    public function setAmount(float|string $amount): self
    {
        $this->amount = (string) $amount;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTransactionRef(): ?string
    {
        return $this->transactionRef;
    }

    public function setTransactionRef(?string $transactionRef): self
    {
        $this->transactionRef = $transactionRef;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): self
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }
}