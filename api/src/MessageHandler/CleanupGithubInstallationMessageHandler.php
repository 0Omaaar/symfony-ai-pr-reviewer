<?php

namespace App\MessageHandler;

use App\Entity\GithubInstallation;
use App\Entity\UserGithubInstallation;
use App\Message\CleanupGithubInstallationMessage;
use App\Service\CacheKeys;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
final readonly class CleanupGithubInstallationMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private CacheInterface $cache,
    ) {
    }

    public function __invoke(CleanupGithubInstallationMessage $message): void
    {
        $installation = $this->em->getRepository(GithubInstallation::class)
            ->findOneBy(['installationId' => $message->installationId]);

        if ($installation === null) {
            $this->logger->info('Installation cleanup: no record found, nothing to do', [
                'installation_id' => $message->installationId,
                'action' => $message->action,
                'delivery_id' => $message->deliveryId,
            ]);

            return;
        }

        $userLinks = $this->em->getRepository(UserGithubInstallation::class)
            ->findBy(['installation' => $installation]);

        // Collect user IDs before removing links so we can bust their caches
        $affectedUserIds = [];
        foreach ($userLinks as $link) {
            $user = $link->getAppUser();
            if ($user !== null && $user->getId() !== null) {
                $affectedUserIds[] = $user->getId();
            }
            $this->em->remove($link);
        }

        if ($message->action === 'deleted') {
            $this->em->remove($installation);
        }

        $this->em->flush();

        // Bust server-side caches for every affected user
        foreach ($affectedUserIds as $userId) {
            $this->cache->delete(CacheKeys::userRepositories($userId));
            $this->cache->delete(CacheKeys::dashboardPayload($userId));
        }

        $this->logger->info('Installation cleanup complete', [
            'installation_id' => $message->installationId,
            'action' => $message->action,
            'delivery_id' => $message->deliveryId,
            'user_links_removed' => count($userLinks),
        ]);
    }
}
