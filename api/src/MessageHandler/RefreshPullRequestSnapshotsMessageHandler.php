<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\RefreshPullRequestSnapshotsMessage;
use App\Repository\RepoSubscriptionRepository;
use App\Service\PullRequest\PullRequestSnapshotService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshPullRequestSnapshotsMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private RepoSubscriptionRepository $subscriptionRepo,
        private PullRequestSnapshotService $snapshotService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshPullRequestSnapshotsMessage $message): void
    {
        if ($message->userId !== null) {
            $user = $this->em->find(User::class, $message->userId);
            if ($user instanceof User) {
                $this->snapshotService->refreshForUser($user);
                $this->logger->debug('Refreshed snapshots for single user', ['user_id' => $message->userId]);
            }
            return;
        }

        // Refresh for all users with active subscriptions
        $userIds = $this->subscriptionRepo->createQueryBuilder('s')
            ->select('DISTINCT IDENTITY(s.appUser)')
            ->andWhere('s.isActive = true')
            ->getQuery()
            ->getSingleColumnResult();

        $refreshed = 0;
        foreach ($userIds as $userId) {
            $user = $this->em->find(User::class, $userId);
            if (!$user instanceof User) {
                continue;
            }

            try {
                $this->snapshotService->refreshForUser($user);
                $refreshed++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to refresh snapshots for user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Background snapshot refresh complete', [
            'users_processed' => $refreshed,
            'users_total' => \count($userIds),
        ]);
    }
}
