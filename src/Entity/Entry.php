<?php

namespace App\Entity;

use App\Repository\EntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
#[ORM\Entity(repositoryClass: EntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Entry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Challenge::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Challenge $challenge = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $submittedBy = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $entryType = 'outfit'; // outfit or design

    #[ORM\Column(type: Types::JSON)]
    private array $files = []; // Array of file paths

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $metadata = []; // Additional metadata like dimensions, colors, etc.

    #[ORM\Column]
    private int $voteCount = 0;

    #[ORM\Column(name: 'entry_rank', nullable: true)]
    private ?int $rank = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'published'; // published, removed, flagged

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'entry', targetEntity: Vote::class, orphanRemoval: true)]
    private Collection $votes;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
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

    public function getChallenge(): ?Challenge
    {
        return $this->challenge;
    }

    public function setChallenge(?Challenge $challenge): static
    {
        $this->challenge = $challenge;
        return $this;
    }

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $submittedBy): static
    {
        $this->submittedBy = $submittedBy;
        return $this;
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

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getEntryType(): ?string
    {
        return $this->entryType;
    }

    public function setEntryType(string $entryType): static
    {
        $this->entryType = $entryType;
        return $this;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): static
    {
        $this->files = $files;
        return $this;
    }

    public function addFile(string $file): static
    {
        if (!in_array($file, $this->files)) {
            $this->files[] = $file;
        }
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getVoteCount(): int
    {
        return $this->voteCount;
    }

    public function setVoteCount(int $voteCount): static
    {
        $this->voteCount = $voteCount;
        return $this;
    }

    public function incrementVoteCount(): static
    {
        $this->voteCount++;
        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): static
    {
        $this->rank = $rank;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Vote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(Vote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setEntry($this);
        }
        return $this;
    }

    public function removeVote(Vote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            if ($vote->getEntry() === $this) {
                $vote->setEntry(null);
            }
        }
        return $this;
    }
}
