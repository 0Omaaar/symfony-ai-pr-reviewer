<?php

namespace App\MessageHandler;

use App\Entity\GithubInstallation;
use App\Entity\UserGithubInstallation;
use App\Message\CleanupGithubInstallationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CleanupGithubInstallationMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
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

        foreach ($userLinks as $link) {
            $this->em->remove($link);
        }

        if ($message->action === 'deleted') {
            $this->em->remove($installation);
        }

        $this->em->flush();

        $this->logger->info('Installation cleanup complete', [
            'installation_id' => $message->installationId,
            'action' => $message->action,
            'delivery_id' => $message->deliveryId,
            'user_links_removed' => count($userLinks),
        ]);
    }
}
