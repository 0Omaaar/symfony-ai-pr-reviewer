<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workspace>
 */
class WorkspaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workspace::class);
    }

    /**
     * @return Workspace[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.appUser = :user')
            ->setParameter('user', $user)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndId(User $user, int $id): ?Workspace
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.appUser = :user')
            ->andWhere('w.id = :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
