<?php

namespace App\Entity;

use App\Repository\RepoSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepoSubscriptionRepository::class)]
#[ORM\Table(name: 'repo_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_user_repo_branch', fields: ['appUser', 'repoFullName', 'branch'])]
#[ORM\HasLifecycleCallbacks]
class RepoSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $appUser = null;

    #[ORM\Column(length: 100)]
    private ?string $installationId = null;

    #[ORM\Column(length: 255)]
    private ?string $repoFullName = null;

    #[ORM\Column(length: 100)]
    private ?string $repoId = null;

    #[ORM\Column(length: 255)]
    private ?string $branch = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $activatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deactivatedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppUser(): ?User
    {
        return $this->appUser;
    }

    public function setAppUser(User $appUser): static
    {
        $this->appUser = $appUser;

        return $this;
    }

    public function getInstallationId(): ?string
    {
        return $this->installationId;
    }

    public function setInstallationId(string $installationId): static
    {
        $this->installationId = $installationId;

        return $this;
    }

    public function getRepoFullName(): ?string
    {
        return $this->repoFullName;
    }

    public function setRepoFullName(string $repoFullName): static
    {
        $this->repoFullName = $repoFullName;

        return $this;
    }

    public function getRepoId(): ?string
    {
        return $this->repoId;
    }

    public function setRepoId(string $repoId): static
    {
        $this->repoId = $repoId;

        return $this;
    }

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(string $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getActivatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(\DateTimeImmutable $activatedAt): static
    {
        $this->activatedAt = $activatedAt;

        return $this;
    }

    public function getDeactivatedAt(): ?\DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function setDeactivatedAt(?\DateTimeImmutable $deactivatedAt): static
    {
        $this->deactivatedAt = $deactivatedAt;

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

    public function activate(): static
    {
        $this->isActive = true;
        $this->activatedAt = new \DateTimeImmutable();
        $this->deactivatedAt = null;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function deactivate(): static
    {
        $this->isActive = false;
        $this->deactivatedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    #[ORM\PrePersist]
    public function setTimestampsOnCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
        $this->activatedAt ??= $now;
    }

    #[ORM\PreUpdate]
    public function setTimestampOnUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
