<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessPullRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessPullRequestHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessPullRequestMessage $message): void
    {
        $this->logger->info('Processing pull request from n8n', [
            'provider' => $message->provider,
            'pr_id' => $message->prId,
            'title' => $message->title,
            'repo_url' => $message->repoUrl,
            'branch' => $message->branch,
            // authorToken intentionally excluded from logs
        ]);

        // TODO: Implement AI review logic here
    }
}
