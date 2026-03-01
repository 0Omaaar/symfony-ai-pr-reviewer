<?php

namespace App\Entity;

use App\Repository\GithubInstallationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GithubInstallationRepository::class)]
#[ORM\Table(name: 'github_installation')]
#[ORM\UniqueConstraint(name: 'UNIQ_25806A745A63BEA7', fields: ['installationId'])]
#[ORM\HasLifecycleCallbacks]
class GithubInstallation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $installationId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountLogin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, UserGithubInstallation>
     */
    #[ORM\OneToMany(targetEntity: UserGithubInstallation::class, mappedBy: 'installation', orphanRemoval: true)]
    private Collection $userInstallations;

    public function __construct()
    {
        $this->userInstallations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInstallationId(): ?int
    {
        return $this->installationId;
    }

    public function setInstallationId(int $installationId): static
    {
        $this->installationId = $installationId;

        return $this;
    }

    public function getAccountLogin(): ?string
    {
        return $this->accountLogin;
    }

    public function setAccountLogin(?string $accountLogin): static
    {
        $this->accountLogin = $accountLogin;

        return $this;
    }

    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    public function setAccountType(?string $accountType): static
    {
        $this->accountType = $accountType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, UserGithubInstallation>
     */
    public function getUserInstallations(): Collection
    {
        return $this->userInstallations;
    }

    public function addUserInstallation(UserGithubInstallation $userInstallation): static
    {
        if (!$this->userInstallations->contains($userInstallation)) {
            $this->userInstallations->add($userInstallation);
            $userInstallation->setInstallation($this);
        }

        return $this;
    }

    public function removeUserInstallation(UserGithubInstallation $userInstallation): static
    {
        $this->userInstallations->removeElement($userInstallation);

        return $this;
    }

    #[ORM\PrePersist]
    public function setTimestampsOnCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function setTimestampOnUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
