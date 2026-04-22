<?php

namespace App\Entity;

use App\Repository\UserBadgeRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $badgeName = null; // challenge_winner, designer_of_month, rising_creator, fashion_icon, community_favorite, exclusive_collaborator

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $challengeId = null; // Link to specific challenge if applicable

    #[ORM\Column(length: 50)]
    private ?string $level = 'bronze'; // bronze, silver, gold, platinum

    #[ORM\Column]
    private ?\DateTimeImmutable $earnedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        if (!$this->earnedAt) {
            $this->earnedAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getBadgeName(): ?string
    {
        return $this->badgeName;
    }

    public function setBadgeName(string $badgeName): static
    {
        $this->badgeName = $badgeName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getChallengeId(): ?int
    {
        return $this->challengeId;
    }

    public function setChallengeId(?int $challengeId): static
    {
        $this->challengeId = $challengeId;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getEarnedAt(): ?\DateTimeImmutable
    {
        return $this->earnedAt;
    }

    public function setEarnedAt(\DateTimeImmutable $earnedAt): static
    {
        $this->earnedAt = $earnedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getBadgeIcon(): string
    {
        return match($this->badgeName) {
            'challenge_winner' => '🥇',
            'designer_of_month' => '🎨',
            'rising_creator' => '⭐',
            'fashion_icon' => '👑',
            'community_favorite' => '🌟',
            'exclusive_collaborator' => '💎',
            default => '🏅',
        };
    }
}
