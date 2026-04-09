<?php

namespace App\Entity;

use App\Repository\AdminLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminLogRepository::class)]
#[ORM\Table(name: 'admin_log')]
#[ORM\HasLifecycleCallbacks]
class AdminLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $action = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $targetType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetId = null;

    #[ORM\Column(length: 100)]
    private string $performedBy = 'admin';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetType(?string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    public function setTargetId(?string $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getPerformedBy(): string
    {
        return $this->performedBy;
    }

    public function setPerformedBy(string $performedBy): static
    {
        $this->performedBy = $performedBy;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtOnPersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }
}
