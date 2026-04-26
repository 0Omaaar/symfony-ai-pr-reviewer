<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PullRequestSnapshot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
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

    private const OPEN_STATUSES = ['open', 'draft'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PullRequestSnapshot::class);
    }

    /**
     * @return PullRequestSnapshot[]
     */
    public function findOpenByUser(User $user, array $filters = []): array
    {
        [$whereSql, $params, $types] = $this->buildListWhereClause($user, $filters);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['perPage'] ?? 25)));

        $params['limit'] = $perPage;
        $params['offset'] = ($page - 1) * $perPage;
        $types['limit'] = ParameterType::INTEGER;
        $types['offset'] = ParameterType::INTEGER;

        $sql = \sprintf(
            'SELECT id FROM pull_request_snapshot WHERE %s ORDER BY %s LIMIT :limit OFFSET :offset',
            $whereSql,
            $this->buildOrderByClause($filters),
        );

        $ids = \array_map('intval', $this->getEntityManager()->getConnection()->fetchFirstColumn($sql, $params, $types));
        if ([] === $ids) {
            return [];
        }

        $snapshots = $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $snapshotsById = [];
        foreach ($snapshots as $snapshot) {
            if ($snapshot instanceof PullRequestSnapshot && $snapshot->getId() !== null) {
                $snapshotsById[$snapshot->getId()] = $snapshot;
            }
        }

        $orderedSnapshots = [];
        foreach ($ids as $id) {
            if (isset($snapshotsById[$id])) {
                $orderedSnapshots[] = $snapshotsById[$id];
            }
        }

        return $orderedSnapshots;
    }

    public function countOpenByUser(User $user, array $filters = []): int
    {
        [$whereSql, $params, $types] = $this->buildListWhereClause($user, $filters);

        $sql = \sprintf('SELECT COUNT(*) FROM pull_request_snapshot WHERE %s', $whereSql);

        return (int) $this->getEntityManager()->getConnection()->fetchOne($sql, $params, $types);
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
            ->setParameter('statuses', self::OPEN_STATUSES)
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
            ->setParameter('statuses', self::OPEN_STATUSES)
            ->getQuery()
            ->execute();
    }

    public function getDashboardStats(User $user, string $view = 'all', ?array $repoFilter = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $username = $user->getGithubUsername() ?? '';
        $view = $this->normalizeView($view);

        $params = [
            'userId' => (int) $user->getId(),
            'openStatuses' => self::OPEN_STATUSES,
            'githubUsername' => $username,
        ];
        $types = [
            'openStatuses' => ArrayParameterType::STRING,
        ];

        $repoClause = '';
        if ($repoFilter !== null) {
            if (empty($repoFilter)) {
                // Empty workspace — return zeroed stats
                return [
                    'totalOpen' => 0, 'needsReview' => 0, 'stale' => 0,
                    'aiReviewed' => 0, 'ciFailing' => 0, 'myPRs' => 0, 'needsMyReview' => 0,
                    'views' => ['all' => 0, 'my_authored' => 0, 'requesting_my_review' => 0, 'i_approved' => 0, 'blocked_by_ci' => 0, 'unowned' => 0],
                ];
            }
            $repoClause = ' AND repo_full_name IN (:repoFilter)';
            $params['repoFilter'] = $repoFilter;
            $types['repoFilter'] = ArrayParameterType::STRING;
        }

        [$viewClause, $viewParams, $viewTypes] = $this->buildOwnershipClause($view, $username, 'selectedView');

        $selectedStatsSql = <<<'SQL'
            SELECT
                COUNT(*) AS total_open,
                COUNT(*) FILTER (WHERE review_status = 'review_requested') AS needs_review,
                COUNT(*) FILTER (WHERE is_stale = true) AS stale,
                COUNT(*) FILTER (WHERE ai_review_status = 'completed') AS ai_reviewed,
                COUNT(*) FILTER (WHERE ci_status = 'failure') AS ci_failing,
                COUNT(*) FILTER (WHERE author_login = :githubUsername) AS my_prs,
                COUNT(*) FILTER (
                    WHERE EXISTS (
                        SELECT 1
                        FROM json_array_elements(assigned_reviewers) reviewer
                        WHERE reviewer->>'login' = :githubUsername
                    )
                ) AS needs_my_review
            FROM pull_request_snapshot
            WHERE app_user_id = :userId
              AND status IN (:openStatuses)
        SQL;

        if ($viewClause !== null) {
            $selectedStatsSql .= ' AND ' . $viewClause;
            $params = [...$params, ...$viewParams];
            $types = [...$types, ...$viewTypes];
        }

        $selectedStatsSql .= $repoClause;

        $selectedRow = $conn->fetchAssociative($selectedStatsSql, $params, $types) ?: [];

        $viewsSql = <<<'SQL'
            SELECT
                COUNT(*) AS all_count,
                COUNT(*) FILTER (WHERE author_login = :githubUsername) AS my_authored_count,
                COUNT(*) FILTER (
                    WHERE EXISTS (
                        SELECT 1
                        FROM json_array_elements(assigned_reviewers) reviewer
                        WHERE reviewer->>'login' = :githubUsername
                    )
                ) AS requesting_my_review_count,
                COUNT(*) FILTER (
                    WHERE EXISTS (
                        SELECT 1
                        FROM json_array_elements(completed_reviews) review
                        WHERE review->>'login' = :githubUsername
                          AND review->>'state' = 'APPROVED'
                    )
                ) AS i_approved_count,
                COUNT(*) FILTER (WHERE ci_status = 'failure') AS blocked_by_ci_count,
                COUNT(*) FILTER (
                    WHERE json_array_length(assigned_reviewers) = 0
                      AND json_array_length(completed_reviews) = 0
                ) AS unowned_count
            FROM pull_request_snapshot
            WHERE app_user_id = :userId
              AND status IN (:openStatuses)
        SQL;

        $viewsParams = [
            'userId' => (int) $user->getId(),
            'openStatuses' => self::OPEN_STATUSES,
            'githubUsername' => $username,
        ];
        $viewsTypes = ['openStatuses' => ArrayParameterType::STRING];

        if ($repoFilter !== null && !empty($repoFilter)) {
            $viewsSql .= $repoClause;
            $viewsParams['repoFilter'] = $repoFilter;
            $viewsTypes['repoFilter'] = ArrayParameterType::STRING;
        }

        $viewsRow = $conn->fetchAssociative($viewsSql, $viewsParams, $viewsTypes) ?: [];

        return [
            'totalOpen' => (int) ($selectedRow['total_open'] ?? 0),
            'needsReview' => (int) ($selectedRow['needs_review'] ?? 0),
            'stale' => (int) ($selectedRow['stale'] ?? 0),
            'aiReviewed' => (int) ($selectedRow['ai_reviewed'] ?? 0),
            'ciFailing' => (int) ($selectedRow['ci_failing'] ?? 0),
            'myPRs' => (int) ($selectedRow['my_prs'] ?? 0),
            'needsMyReview' => (int) ($selectedRow['needs_my_review'] ?? 0),
            'views' => [
                'all' => (int) ($viewsRow['all_count'] ?? 0),
                'my_authored' => (int) ($viewsRow['my_authored_count'] ?? 0),
                'requesting_my_review' => (int) ($viewsRow['requesting_my_review_count'] ?? 0),
                'i_approved' => (int) ($viewsRow['i_approved_count'] ?? 0),
                'blocked_by_ci' => (int) ($viewsRow['blocked_by_ci_count'] ?? 0),
                'unowned' => (int) ($viewsRow['unowned_count'] ?? 0),
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
                ->setParameter('statuses', self::OPEN_STATUSES)
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
            ->setParameter('statuses', self::OPEN_STATUSES)
            ->setParameter('openPrs', $openPrNumbers)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function buildListWhereClause(User $user, array $filters): array
    {
        $view = $this->normalizeView((string) ($filters['view'] ?? 'all'));
        $statusFilter = $view === 'all' ? (string) ($filters['status'] ?? 'open') : 'open';

        $where = ['app_user_id = :userId'];
        $params = ['userId' => (int) $user->getId()];
        $types = [];

        if ($statusFilter === 'open') {
            $where[] = 'status IN (:statuses)';
            $params['statuses'] = self::OPEN_STATUSES;
            $types['statuses'] = ArrayParameterType::STRING;
        } elseif ($statusFilter !== 'all') {
            $where[] = 'status = :status';
            $params['status'] = $statusFilter;
        }

        $repos = \array_values(\array_filter((array) ($filters['repos'] ?? []), static fn (mixed $value): bool => \is_string($value) && $value !== ''));
        if ([] !== $repos) {
            $where[] = 'repo_full_name IN (:repos)';
            $params['repos'] = $repos;
            $types['repos'] = ArrayParameterType::STRING;
        }

        $authors = \array_values(\array_filter((array) ($filters['authors'] ?? []), static fn (mixed $value): bool => \is_string($value) && $value !== ''));
        if ([] !== $authors) {
            $where[] = 'author_login IN (:authors)';
            $params['authors'] = $authors;
            $types['authors'] = ArrayParameterType::STRING;
        }

        if (\is_string($filters['reviewStatus'] ?? null) && $filters['reviewStatus'] !== '') {
            $where[] = 'review_status = :reviewStatus';
            $params['reviewStatus'] = $filters['reviewStatus'];
        }

        if (\is_string($filters['targetBranch'] ?? null) && $filters['targetBranch'] !== '') {
            $where[] = 'target_branch = :targetBranch';
            $params['targetBranch'] = $filters['targetBranch'];
        }

        if (($filters['stale'] ?? false) === true) {
            $where[] = 'is_stale = true';
        }

        if (\is_string($filters['ciStatus'] ?? null) && $filters['ciStatus'] !== '') {
            $where[] = 'ci_status = :ciStatus';
            $params['ciStatus'] = $filters['ciStatus'];
        }

        if (\is_string($filters['aiStatus'] ?? null) && $filters['aiStatus'] !== '') {
            if ($filters['aiStatus'] === 'has_issues') {
                $where[] = 'ai_review_status = :aiCompletedStatus AND ai_issue_count > 0';
                $params['aiCompletedStatus'] = 'completed';
            } else {
                $where[] = 'ai_review_status = :aiStatus';
                $params['aiStatus'] = $filters['aiStatus'];
            }
        }

        [$ownershipClause, $ownershipParams, $ownershipTypes] = $this->buildOwnershipClause($view, $user->getGithubUsername() ?? '', 'ownership');
        if ($ownershipClause !== null) {
            $where[] = $ownershipClause;
            $params = [...$params, ...$ownershipParams];
            $types = [...$types, ...$ownershipTypes];
        }

        return [\implode(' AND ', $where), $params, $types];
    }

    private function buildOrderByClause(array $filters): string
    {
        $sortBy = $filters['sortBy'] ?? 'lastActivityAt';
        $sortDir = \strtoupper((string) ($filters['sortDir'] ?? 'DESC'));

        $column = match ($sortBy) {
            'openedAt' => 'opened_at',
            'prNumber' => 'pr_number',
            'commentCount' => 'comment_count',
            default => 'last_activity_at',
        };

        if (!\in_array($sortDir, ['ASC', 'DESC'], true)) {
            $sortDir = 'DESC';
        }

        return \sprintf('%s %s, id DESC', $column, $sortDir);
    }

    /**
     * @return array{0: ?string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function buildOwnershipClause(string $view, string $username, string $prefix): array
    {
        $view = $this->normalizeView($view);
        $usernameParam = $prefix . 'Username';

        return match ($view) {
            'my_authored' => [
                \sprintf('author_login = :%s', $usernameParam),
                [$usernameParam => $username],
                [],
            ],
            'requesting_my_review' => $username === ''
                ? ['1 = 0', [], []]
                : [
                    \sprintf(
                        "EXISTS (SELECT 1 FROM json_array_elements(assigned_reviewers) reviewer WHERE reviewer->>'login' = :%s)",
                        $usernameParam,
                    ),
                    [$usernameParam => $username],
                    [],
                ],
            'i_approved' => $username === ''
                ? ['1 = 0', [], []]
                : [
                    \sprintf(
                        "EXISTS (SELECT 1 FROM json_array_elements(completed_reviews) review WHERE review->>'login' = :%s AND review->>'state' = 'APPROVED')",
                        $usernameParam,
                    ),
                    [$usernameParam => $username],
                    [],
                ],
            'blocked_by_ci' => ["ci_status = 'failure'", [], []],
            'unowned' => ['json_array_length(assigned_reviewers) = 0 AND json_array_length(completed_reviews) = 0', [], []],
            default => [null, [], []],
        };
    }

    private function normalizeView(string $view): string
    {
        return \in_array($view, self::OWNERSHIP_VIEWS, true) ? $view : 'all';
    }
}
