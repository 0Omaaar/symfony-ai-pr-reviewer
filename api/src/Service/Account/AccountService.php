<?php

namespace App\Service\Account;

use App\Entity\GithubInstallation;
use App\Entity\User;
use App\Entity\UserGithubInstallation;
use App\Service\CacheKeys;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Business logic for account lifecycle operations.
 * Controllers call these methods and handle the HTTP response; this service
 * owns the rules (what gets deleted, what cache keys are busted, etc.).
 */
final class AccountService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Deletes the user and all their data.
     * Removes UserGithubInstallation links and orphaned GithubInstallation records.
     */
    public function deleteUser(User $user): void
    {
        $userLinks = $this->em->getRepository(UserGithubInstallation::class)
            ->findBy(['appUser' => $user]);

        $installationIds = [];
        foreach ($userLinks as $link) {
            $installation = $link->getInstallation();
            if ($installation !== null && $installation->getId() !== null) {
                $installationIds[] = $installation->getId();
            }
            $this->em->remove($link);
        }

        foreach ($installationIds as $installationId) {
            $installation = $this->em->find(GithubInstallation::class, $installationId);
            if ($installation === null) {
                continue;
            }
            if ($this->em->getRepository(UserGithubInstallation::class)->count(['installation' => $installation]) === 0) {
                $this->em->remove($installation);
            }
        }

        $this->em->remove($user);
        $this->em->flush();
    }

    /**
     * Unlinks a specific GitHub installation from the user.
     * Removes the orphaned GithubInstallation record if no other users remain.
     * Busts the user's repo cache so the removal is reflected immediately.
     *
     * @throws \InvalidArgumentException if the installation is not linked to this user
     */
    public function removeInstallation(User $user, GithubInstallation $installation): void
    {
        $link = $this->em->getRepository(UserGithubInstallation::class)
            ->findOneBy(['appUser' => $user, 'installation' => $installation]);

        if ($link === null) {
            throw new \InvalidArgumentException('Installation not linked to this account.');
        }

        $this->em->remove($link);
        $this->em->flush();

        if ($this->em->getRepository(UserGithubInstallation::class)->count(['installation' => $installation]) === 0) {
            $this->em->remove($installation);
            $this->em->flush();
        }

        if ($user->getId() !== null) {
            $this->cache->delete(CacheKeys::userRepositories($user->getId()));
        }
    }
}
