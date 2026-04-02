<?php

namespace App\Service\Github;

use App\Entity\ProcessedWebhookDelivery;
use App\Repository\ProcessedWebhookDeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handles the cross-cutting concerns of every incoming GitHub webhook:
 * - HMAC-SHA256 signature verification
 * - Idempotency (reject already-seen delivery IDs)
 *
 * The controller is responsible for rate-limiting and dispatching messages;
 * this service is responsible for the security and deduplication layer.
 */
final class GithubWebhookService
{
    public function __construct(
        #[Autowire(param: 'github.webhook_secret')] private readonly string $webhookSecret,
        private readonly ProcessedWebhookDeliveryRepository $deliveryRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function verifySignature(string $rawBody, string $signature): bool
    {
        if ($signature === '' || $this->webhookSecret === '') {
            return false;
        }

        $expected = 'sha256=' . \hash_hmac('sha256', $rawBody, $this->webhookSecret);

        return \hash_equals($expected, $signature);
    }

    public function isAlreadyProcessed(string $deliveryId): bool
    {
        return $deliveryId !== '' && $this->deliveryRepo->existsByDeliveryId($deliveryId);
    }

    public function markAsProcessed(string $deliveryId): void
    {
        if ($deliveryId === '') {
            return;
        }

        $this->em->persist(new ProcessedWebhookDelivery($deliveryId));
        $this->em->flush();
    }
}
