<?php

namespace App\Entity;

use App\Repository\ChallengeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
#[ORM\Entity(repositoryClass: ChallengeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Challenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $theme = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $votingStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $votingEndDate = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'active'; // active, voting, closed, archived

    #[ORM\Column(nullable: true)]
    private ?int $maxEntries = null;

    #[ORM\Column(type: Types::JSON)]
    private array $prizes = []; // Array of prize information

    #[ORM\Column(type: Types::JSON)]
    private array $categories = ['outfit', 'design']; // Enabled submission categories

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'challenge', targetEntity: Entry::class, orphanRemoval: true)]
    private Collection $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getVotingStartDate(): ?\DateTimeImmutable
    {
        return $this->votingStartDate;
    }

    public function setVotingStartDate(?\DateTimeImmutable $votingStartDate): static
    {
        $this->votingStartDate = $votingStartDate;
        return $this;
    }

    public function getVotingEndDate(): ?\DateTimeImmutable
    {
        return $this->votingEndDate;
    }

    public function setVotingEndDate(?\DateTimeImmutable $votingEndDate): static
    {
        $this->votingEndDate = $votingEndDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getMaxEntries(): ?int
    {
        return $this->maxEntries;
    }

    public function setMaxEntries(?int $maxEntries): static
    {
        $this->maxEntries = $maxEntries;
        return $this;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setCategories(array $categories): static
    {
        $this->categories = $categories;
        return $this;
    }

    public function getPrizes(): array
    {
        return $this->prizes;
    }

    public function setPrizes(array $prizes): static
    {
        $this->prizes = $prizes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return Collection<int, Entry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(Entry $entry): static
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->setChallenge($this);
        }
        return $this;
    }

    public function removeEntry(Entry $entry): static
    {
        if ($this->entries->removeElement($entry)) {
            if ($entry->getChallenge() === $this) {
                $entry->setChallenge(null);
            }
        }
        return $this;
    }

    public function isActive(): bool
    {
        $now = new \DateTimeImmutable();
        return $this->status === 'active'
            && $this->startDate->getTimestamp() <= $now->getTimestamp()
            && $now->getTimestamp() <= $this->endDate->getTimestamp();
    }

    public function isVotingOpen(): bool
    {
        $now = new \DateTimeImmutable();

        // If explicit voting dates are configured, use them
        if ($this->votingStartDate && $this->votingEndDate) {
            if ($this->votingStartDate <= $now && $now <= $this->votingEndDate) {
                return true;
            }
        }

        // Fallback: allow voting while the challenge is active (not closed)
        return $this->status === 'active';
    }
}
