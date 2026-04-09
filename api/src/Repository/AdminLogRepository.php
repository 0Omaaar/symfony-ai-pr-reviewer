<?php

namespace App\Repository;

use App\Entity\AdminLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminLog>
 */
class AdminLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminLog::class);
    }

    public function findPaginated(int $page, int $pageSize, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('l.action LIKE :search OR l.targetId LIKE :search OR l.performedBy LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $total = (clone $qb)->select('COUNT(l.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $results = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['data' => $results, 'total' => (int) $total];
    }
}
