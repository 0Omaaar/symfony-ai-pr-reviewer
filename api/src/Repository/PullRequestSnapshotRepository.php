<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PullRequestSnapshot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PullRequestSnapshot>
 */
class PullRequestSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PullRequestSnapshot::class);
    }

    /**
     * @return PullRequestSnapshot[]
     */
    public function findOpenByUser(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->setParameter('user', $user);

        $statusFilter = $filters['status'] ?? 'open';
        if ($statusFilter === 'open') {
            $qb->andWhere('s.status IN (:statuses)')
                ->setParameter('statuses', ['open', 'draft']);
        } elseif ($statusFilter !== 'all') {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if (!empty($filters['repos'])) {
            $qb->andWhere('s.repoFullName IN (:repos)')
                ->setParameter('repos', $filters['repos']);
        }

        if (!empty($filters['authors'])) {
            $qb->andWhere('s.authorLogin IN (:authors)')
                ->setParameter('authors', $filters['authors']);
        }

        if (!empty($filters['reviewStatus'])) {
            $qb->andWhere('s.reviewStatus = :reviewStatus')
                ->setParameter('reviewStatus', $filters['reviewStatus']);
        }

        if (!empty($filters['targetBranch'])) {
            $qb->andWhere('s.targetBranch = :targetBranch')
                ->setParameter('targetBranch', $filters['targetBranch']);
        }

        if (isset($filters['stale']) && $filters['stale']) {
            $qb->andWhere('s.isStale = true');
        }

        if (!empty($filters['ciStatus'])) {
            $qb->andWhere('s.ciStatus = :ciStatus')
                ->setParameter('ciStatus', $filters['ciStatus']);
        }

        if (!empty($filters['aiStatus'])) {
            match ($filters['aiStatus']) {
                'has_issues' => $qb->andWhere('s.aiReviewStatus = :aiStatus AND s.aiIssueCount > 0')
                    ->setParameter('aiStatus', 'completed'),
                default => $qb->andWhere('s.aiReviewStatus = :aiStatus')
                    ->setParameter('aiStatus', $filters['aiStatus']),
            };
        }

        $sortBy = $filters['sortBy'] ?? 'lastActivityAt';
        $sortDir = strtoupper($filters['sortDir'] ?? 'DESC');
        $validSorts = ['openedAt', 'lastActivityAt', 'prNumber', 'commentCount'];
        if (!\in_array($sortBy, $validSorts, true)) {
            $sortBy = 'lastActivityAt';
        }
        if (!\in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }
        $qb->orderBy('s.' . $sortBy, $sortDir);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['perPage'] ?? 25)));
        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return $qb->getQuery()->getResult();
    }

    public function countOpenByUser(User $user, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.appUser = :user')
            ->setParameter('user', $user);

        $statusFilter = $filters['status'] ?? 'open';
        if ($statusFilter === 'open') {
            $qb->andWhere('s.status IN (:statuses)')
                ->setParameter('statuses', ['open', 'draft']);
        } elseif ($statusFilter !== 'all') {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if (!empty($filters['repos'])) {
            $qb->andWhere('s.repoFullName IN (:repos)')
                ->setParameter('repos', $filters['repos']);
        }

        if (!empty($filters['authors'])) {
            $qb->andWhere('s.authorLogin IN (:authors)')
                ->setParameter('authors', $filters['authors']);
        }

        if (!empty($filters['reviewStatus'])) {
            $qb->andWhere('s.reviewStatus = :reviewStatus')
                ->setParameter('reviewStatus', $filters['reviewStatus']);
        }

        if (!empty($filters['targetBranch'])) {
            $qb->andWhere('s.targetBranch = :targetBranch')
                ->setParameter('targetBranch', $filters['targetBranch']);
        }

        if (isset($filters['stale']) && $filters['stale']) {
            $qb->andWhere('s.isStale = true');
        }

        if (!empty($filters['ciStatus'])) {
            $qb->andWhere('s.ciStatus = :ciStatus')
                ->setParameter('ciStatus', $filters['ciStatus']);
        }

        if (!empty($filters['aiStatus'])) {
            match ($filters['aiStatus']) {
                'has_issues' => $qb->andWhere('s.aiReviewStatus = :aiStatus AND s.aiIssueCount > 0')
                    ->setParameter('aiStatus', 'completed'),
                default => $qb->andWhere('s.aiReviewStatus = :aiStatus')
                    ->setParameter('aiStatus', $filters['aiStatus']),
            };
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return PullRequestSnapshot[]
     */
    public function findByUserAndRepo(User $user, string $repoFullName): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.repoFullName = :repo')
            ->setParameter('user', $user)
            ->setParameter('repo', $repoFullName)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PullRequestSnapshot[]
     */
    public function findStaleByUser(User $user, int $days = 7): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.isStale = true')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['open', 'draft'])
            ->orderBy('s.lastActivityAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserRepoAndPr(User $user, string $repoFullName, int $prNumber): ?PullRequestSnapshot
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.repoFullName = :repo')
            ->andWhere('s.prNumber = :prNumber')
            ->setParameter('user', $user)
            ->setParameter('repo', $repoFullName)
            ->setParameter('prNumber', $prNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markStale(int $thresholdDays = 7): int
    {
        $threshold = new \DateTimeImmutable("-{$thresholdDays} days");

        return (int) $this->createQueryBuilder('s')
            ->update()
            ->set('s.isStale', 'true')
            ->andWhere('s.lastActivityAt < :threshold')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.isStale = false')
            ->setParameter('threshold', $threshold)
            ->setParameter('statuses', ['open', 'draft'])
            ->getQuery()
            ->execute();
    }

    public function getDashboardStats(User $user): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE status IN ('open', 'draft')) AS total_open,
                COUNT(*) FILTER (WHERE review_status = 'review_requested' AND status IN ('open', 'draft')) AS needs_review,
                COUNT(*) FILTER (WHERE is_stale = true AND status IN ('open', 'draft')) AS stale,
                COUNT(*) FILTER (WHERE ai_review_status = 'completed' AND status IN ('open', 'draft')) AS ai_reviewed,
                COUNT(*) FILTER (WHERE ci_status = 'failure' AND status IN ('open', 'draft')) AS ci_failing
            FROM pull_request_snapshot
            WHERE app_user_id = :userId
        SQL;

        $row = $conn->fetchAssociative($sql, ['userId' => $user->getId()]);

        return [
            'totalOpen' => (int) ($row['total_open'] ?? 0),
            'needsReview' => (int) ($row['needs_review'] ?? 0),
            'stale' => (int) ($row['stale'] ?? 0),
            'aiReviewed' => (int) ($row['ai_reviewed'] ?? 0),
            'ciFailing' => (int) ($row['ci_failing'] ?? 0),
        ];
    }

    public function removeClosedForRepo(User $user, string $repoFullName, array $openPrNumbers): int
    {
        if (empty($openPrNumbers)) {
            // Close all snapshots for this repo
            return (int) $this->createQueryBuilder('s')
                ->update()
                ->set('s.status', ':closed')
                ->andWhere('s.appUser = :user')
                ->andWhere('s.repoFullName = :repo')
                ->andWhere('s.status IN (:statuses)')
                ->setParameter('closed', 'closed')
                ->setParameter('user', $user)
                ->setParameter('repo', $repoFullName)
                ->setParameter('statuses', ['open', 'draft'])
                ->getQuery()
                ->execute();
        }

        return (int) $this->createQueryBuilder('s')
            ->update()
            ->set('s.status', ':closed')
            ->andWhere('s.appUser = :user')
            ->andWhere('s.repoFullName = :repo')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.prNumber NOT IN (:openPrs)')
            ->setParameter('closed', 'closed')
            ->setParameter('user', $user)
            ->setParameter('repo', $repoFullName)
            ->setParameter('statuses', ['open', 'draft'])
            ->setParameter('openPrs', $openPrNumbers)
            ->getQuery()
            ->execute();
    }
}
