<?php

namespace App\Repository;

use App\Entity\RepoSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RepoSubscription>
 */
class RepoSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RepoSubscription::class);
    }

    /**
     * @return RepoSubscription[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('s.repoFullName', 'ASC')
            ->addOrderBy('s.branch', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RepoSubscription[]
     */
    public function findActiveByRepoAndBranch(string $repoFullName, string $branch): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.repoFullName = :repo')
            ->andWhere('s.branch = :branch')
            ->andWhere('s.isActive = true')
            ->setParameter('repo', $repoFullName)
            ->setParameter('branch', $branch)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return RepoSubscription[]
     */
    public function findByUserAndRepo(User $user, string $repoFullName): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.repoFullName = :repo')
            ->setParameter('user', $user)
            ->setParameter('repo', $repoFullName)
            ->orderBy('s.branch', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserRepoBranch(User $user, string $repoFullName, string $branch): ?RepoSubscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.repoFullName = :repo')
            ->andWhere('s.branch = :branch')
            ->setParameter('user', $user)
            ->setParameter('repo', $repoFullName)
            ->setParameter('branch', $branch)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.isActive = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deactivateByInstallationId(string $installationId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->update()
            ->set('s.isActive', 'false')
            ->set('s.deactivatedAt', ':now')
            ->set('s.updatedAt', ':now')
            ->andWhere('s.installationId = :installationId')
            ->andWhere('s.isActive = true')
            ->setParameter('installationId', $installationId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
