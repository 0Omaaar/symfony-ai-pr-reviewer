<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkspaceRepositoryEntryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a repository membership within a workspace.
 * Named WorkspaceRepository_ to avoid collision with Doctrine's Repository class name.
 */
#[ORM\Entity(repositoryClass: WorkspaceRepositoryEntryRepository::class)]
#[ORM\Table(name: 'workspace_repository')]
#[ORM\UniqueConstraint(name: 'uniq_workspace_repo', fields: ['workspace', 'repoFullName'])]
#[ORM\HasLifecycleCallbacks]
class WorkspaceRepository_
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'repositories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Workspace $workspace = null;

    #[ORM\Column(length: 255)]
    private ?string $repoFullName = null;

    #[ORM\Column(length: 100)]
    private ?string $repoId = null;

    #[ORM\Column(length: 100)]
    private ?string $installationId = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(Workspace $workspace): static
    {
        $this->workspace = $workspace;

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

    public function getInstallationId(): ?string
    {
        return $this->installationId;
    }

    public function setInstallationId(string $installationId): static
    {
        $this->installationId = $installationId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
