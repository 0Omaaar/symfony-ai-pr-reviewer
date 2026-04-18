<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PullRequestSnapshot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PullRequestSnapshot>
 */
class PullRequestSnapshotRepository extends ServiceEntityRepository
{
    private const OWNERSHIP_VIEWS = [
        'all',
        'my_authored',
        'requesting_my_review',
        'i_approved',
        'blocked_by_ci',
        'unowned',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PullRequestSnapshot::class);
    }

    /**
     * @return PullRequestSnapshot[]
     */
    public function findOpenByUser(User $user, array $filters = []): array
    {
        $snapshots = $this->getFilteredSnapshots($user, $filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['perPage'] ?? 25)));

        return \array_slice($snapshots, ($page - 1) * $perPage, $perPage);
    }

    public function countOpenByUser(User $user, array $filters = []): int
    {
        return \count($this->getFilteredSnapshots($user, $filters));
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

    public function getDashboardStats(User $user, string $view = 'all'): array
    {
        $allSnapshots = $this->getFilteredSnapshots($user, ['status' => 'open']);
        $view = $this->normalizeView($view);
        $viewSnapshots = $view === 'all'
            ? $allSnapshots
            : \array_values(\array_filter(
                $allSnapshots,
                fn (PullRequestSnapshot $snapshot): bool => $this->matchesOwnershipView($snapshot, $view, $user)
            ));

        return [
            'totalOpen' => \count($viewSnapshots),
            'needsReview' => $this->countMatching($viewSnapshots, static fn (PullRequestSnapshot $snapshot): bool => $snapshot->getReviewStatus() === 'review_requested'),
            'stale' => $this->countMatching($viewSnapshots, static fn (PullRequestSnapshot $snapshot): bool => $snapshot->isStale()),
            'aiReviewed' => $this->countMatching($viewSnapshots, static fn (PullRequestSnapshot $snapshot): bool => $snapshot->getAiReviewStatus() === 'completed'),
            'ciFailing' => $this->countMatching($viewSnapshots, static fn (PullRequestSnapshot $snapshot): bool => $snapshot->getCiStatus() === 'failure'),
            'myPRs' => $this->countMatching($viewSnapshots, fn (PullRequestSnapshot $snapshot): bool => $snapshot->getAuthorLogin() === ($user->getGithubUsername() ?? '')),
            'needsMyReview' => $this->countMatching($viewSnapshots, fn (PullRequestSnapshot $snapshot): bool => $this->isUserRequestedReviewer($snapshot->getAssignedReviewers(), $user->getGithubUsername() ?? '')),
            'views' => [
                'all' => \count($allSnapshots),
                'my_authored' => $this->countMatching($allSnapshots, fn (PullRequestSnapshot $snapshot): bool => $this->matchesOwnershipView($snapshot, 'my_authored', $user)),
                'requesting_my_review' => $this->countMatching($allSnapshots, fn (PullRequestSnapshot $snapshot): bool => $this->matchesOwnershipView($snapshot, 'requesting_my_review', $user)),
                'i_approved' => $this->countMatching($allSnapshots, fn (PullRequestSnapshot $snapshot): bool => $this->matchesOwnershipView($snapshot, 'i_approved', $user)),
                'blocked_by_ci' => $this->countMatching($allSnapshots, fn (PullRequestSnapshot $snapshot): bool => $this->matchesOwnershipView($snapshot, 'blocked_by_ci', $user)),
                'unowned' => $this->countMatching($allSnapshots, fn (PullRequestSnapshot $snapshot): bool => $this->matchesOwnershipView($snapshot, 'unowned', $user)),
            ],
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

    /**
     * @return PullRequestSnapshot[]
     */
    private function getFilteredSnapshots(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->setParameter('user', $user);

        $this->applyStandardFilters($qb, $filters);
        $this->applySorting($qb, $filters);

        $snapshots = $qb->getQuery()->getResult();
        $view = $this->normalizeView($filters['view'] ?? 'all');
        if ($view === 'all') {
            return $snapshots;
        }

        return \array_values(\array_filter(
            $snapshots,
            fn (PullRequestSnapshot $snapshot): bool => $this->matchesOwnershipView($snapshot, $view, $user)
        ));
    }

    private function applyStandardFilters(QueryBuilder $qb, array $filters): void
    {
        $view = $this->normalizeView($filters['view'] ?? 'all');
        $statusFilter = $view === 'all' ? ($filters['status'] ?? 'open') : 'open';

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
    }

    private function applySorting(QueryBuilder $qb, array $filters): void
    {
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
    }

    private function matchesOwnershipView(PullRequestSnapshot $snapshot, string $view, User $user): bool
    {
        $username = $user->getGithubUsername() ?? '';

        return match ($view) {
            'my_authored' => $snapshot->getAuthorLogin() === $username,
            'requesting_my_review' => $this->isUserRequestedReviewer($snapshot->getAssignedReviewers(), $username),
            'i_approved' => $this->hasUserApproved($snapshot->getCompletedReviews(), $username),
            'blocked_by_ci' => $snapshot->getCiStatus() === 'failure',
            'unowned' => $this->isUnowned($snapshot),
            default => true,
        };
    }

    private function hasUserApproved(array $completedReviews, string $username): bool
    {
        if ($username === '') {
            return false;
        }

        foreach ($completedReviews as $review) {
            if (!\is_array($review)) {
                continue;
            }

            if (($review['login'] ?? '') === $username && ($review['state'] ?? '') === 'APPROVED') {
                return true;
            }
        }

        return false;
    }

    private function isUserRequestedReviewer(array $assignedReviewers, string $username): bool
    {
        if ($username === '') {
            return false;
        }

        foreach ($assignedReviewers as $reviewer) {
            if (!\is_array($reviewer)) {
                continue;
            }

            if (($reviewer['login'] ?? '') === $username) {
                return true;
            }
        }

        return false;
    }

    private function isUnowned(PullRequestSnapshot $snapshot): bool
    {
        return [] === $snapshot->getAssignedReviewers() && [] === $snapshot->getCompletedReviews();
    }

    /**
     * @param PullRequestSnapshot[] $snapshots
     */
    private function countMatching(array $snapshots, callable $predicate): int
    {
        return \count(\array_filter($snapshots, $predicate));
    }

    private function normalizeView(string $view): string
    {
        return \in_array($view, self::OWNERSHIP_VIEWS, true) ? $view : 'all';
    }
}
