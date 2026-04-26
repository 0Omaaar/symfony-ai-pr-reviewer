<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkspaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkspaceRepository::class)]
#[ORM\Table(name: 'workspace')]
#[ORM\UniqueConstraint(name: 'uniq_user_workspace_name', fields: ['appUser', 'name'])]
#[ORM\HasLifecycleCallbacks]
class Workspace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $appUser = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, WorkspaceRepository_>
     */
    #[ORM\OneToMany(targetEntity: WorkspaceRepository_::class, mappedBy: 'workspace', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $repositories;

    public function __construct()
    {
        $this->repositories = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, WorkspaceRepository_>
     */
    public function getRepositories(): Collection
    {
        return $this->repositories;
    }

    public function addRepository(WorkspaceRepository_ $repo): static
    {
        if (!$this->repositories->contains($repo)) {
            $this->repositories->add($repo);
            $repo->setWorkspace($this);
        }

        return $this;
    }

    public function removeRepository(WorkspaceRepository_ $repo): static
    {
        $this->repositories->removeElement($repo);

        return $this;
    }
}
