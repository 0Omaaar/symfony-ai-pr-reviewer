<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Workspace;
use App\Entity\WorkspaceRepository_;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkspaceRepository_>
 */
class WorkspaceRepositoryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkspaceRepository_::class);
    }

    /**
     * @return WorkspaceRepository_[]
     */
    public function findByWorkspace(Workspace $workspace): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.workspace = :workspace')
            ->setParameter('workspace', $workspace)
            ->orderBy('r.repoFullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function deleteByWorkspace(Workspace $workspace): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.workspace = :workspace')
            ->setParameter('workspace', $workspace)
            ->getQuery()
            ->execute();
    }
}
