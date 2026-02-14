<?php

namespace App\MessageHandler;

use App\Message\ReviewPullRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReviewPullRequestMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ReviewPullRequestMessage $message): void
    {
        $this->logger->info('Worker received review message', [
            'delivery_id' => $message->deliveryId,
            'installation_id' => $message->installationId,
            'repository' => $message->repoFullName,
            'pr_number' => $message->prNumber,
            'head_sha' => $message->headSha,
        ]);
    }
}
