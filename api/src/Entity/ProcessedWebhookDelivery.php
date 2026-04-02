<?php

namespace App\Entity;

use App\Repository\ProcessedWebhookDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProcessedWebhookDeliveryRepository::class)]
#[ORM\Table(name: 'processed_webhook_delivery')]
#[ORM\UniqueConstraint(name: 'UNIQ_WEBHOOK_DELIVERY_ID', fields: ['deliveryId'])]
class ProcessedWebhookDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $deliveryId;

    #[ORM\Column]
    private \DateTimeImmutable $processedAt;

    public function __construct(string $deliveryId)
    {
        $this->deliveryId = $deliveryId;
        $this->processedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
