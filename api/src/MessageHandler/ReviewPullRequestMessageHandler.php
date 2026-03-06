<?php

namespace App\MessageHandler;

use App\Message\ReviewPullRequestMessage;
use App\Service\Github\GithubInstallationRepositoriesService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReviewPullRequestMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private GithubInstallationRepositoriesService $repositoriesService
    )
    {
    }

    public function __invoke(ReviewPullRequestMessage $message): void
    {
        try {
            $affectedUsers = $this->repositoriesService->processPullRequestWebhookEvent(
                $message->installationId,
                $message->repoId,
                $message->prNumber,
                $message->action,
                $message->deliveryId,
                $message->headSha
            );

            $this->logger->info('Worker processed pull request webhook message', [
                'delivery_id' => $message->deliveryId,
                'installation_id' => $message->installationId,
                'repository_id' => $message->repoId,
                'repository' => $message->repoFullName,
                'action' => $message->action,
                'pr_number' => $message->prNumber,
                'head_sha' => $message->headSha,
                'affected_users' => $affectedUsers,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Worker failed while processing pull request webhook message', [
                'delivery_id' => $message->deliveryId,
                'installation_id' => $message->installationId,
                'repository_id' => $message->repoId,
                'repository' => $message->repoFullName,
                'action' => $message->action,
                'pr_number' => $message->prNumber,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
