<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PullRequestSnapshot;
use App\Entity\User;
use App\Repository\PullRequestSnapshotRepository;
use App\Repository\WorkspaceRepository;
use App\Service\PullRequest\TeamDashboardPreviewService;
use App\Service\CacheKeys;
use App\Service\PullRequest\PullRequestSnapshotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/team-dashboard')]
final class TeamDashboardController extends AbstractController
{
    private const OWNERSHIP_VIEWS = [
        'all',
        'my_authored',
        'requesting_my_review',
        'i_approved',
        'blocked_by_ci',
        'unowned',
    ];

    public function __construct(
        private readonly PullRequestSnapshotRepository $snapshotRepo,
        private readonly PullRequestSnapshotService $snapshotService,
        private readonly TeamDashboardPreviewService $previewService,
        private readonly CacheInterface $cache,
        private readonly WorkspaceRepository $workspaceRepo,
    ) {
    }

    #[Route('', name: 'team_dashboard_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $filters = $this->extractFilters($request);

        // Resolve optional workspace scope
        $workspaceRepos = $this->resolveWorkspaceRepos($user, $request);
        if ($workspaceRepos === false) {
            return $this->json(['error' => 'Workspace not found'], 404);
        }
        if ($workspaceRepos !== null) {
            if (empty($workspaceRepos)) {
                // Empty workspace — return empty results
                return $this->json([
                    'data' => [
                        'pullRequests' => [],
                        'stats' => $this->snapshotRepo->getDashboardStats($user, 'all', []),
                        'groups' => null,
                        'pagination' => ['total' => 0, 'page' => 1, 'perPage' => 25, 'totalPages' => 0],
                    ],
                ]);
            }
            $filters['repos'] = $workspaceRepos;
        }

        $pullRequests = $this->snapshotRepo->findOpenByUser($user, $filters);
        $totalCount = $this->snapshotRepo->countOpenByUser($user, $filters);
        $stats = $this->snapshotRepo->getDashboardStats($user, (string) ($filters['view'] ?? 'all'), $workspaceRepos !== null ? $workspaceRepos : null);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['perPage'] ?? 25)));

        // Compute needsMyAttention per PR
        $githubUsername = $user->getGithubUsername() ?? '';
        $prData = array_map(fn (PullRequestSnapshot $s) => $this->serializePr($s, $githubUsername), $pullRequests);

        // Grouping
        $groups = null;
        $groupBy = $request->query->get('groupBy');
        if ($groupBy !== null && \in_array($groupBy, ['repo', 'author', 'targetBranch', 'aiStatus'], true)) {
            $groups = $this->groupPullRequests($prData, $groupBy);
        }

        return $this->json([
            'data' => [
                'pullRequests' => $prData,
                'stats' => $stats,
                'groups' => $groups,
                'pagination' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'perPage' => $perPage,
                    'totalPages' => (int) ceil($totalCount / $perPage),
                ],
            ],
        ]);
    }

    #[Route('/stats', name: 'team_dashboard_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $view = $this->normalizeView($request->query->get('view'));
        $userId = $user->getId();

        // Resolve optional workspace scope
        $workspaceRepos = $this->resolveWorkspaceRepos($user, $request);
        if ($workspaceRepos === false) {
            return $this->json(['error' => 'Workspace not found'], 404);
        }

        if ($workspaceRepos === null && $view === 'all') {
            $stats = $this->cache->get(CacheKeys::teamDashboardStats((int) $userId), function (ItemInterface $item) use ($user): array {
                $item->expiresAfter(60);
                return $this->snapshotRepo->getDashboardStats($user);
            });
        } else {
            $stats = $this->snapshotRepo->getDashboardStats($user, $view, $workspaceRepos);
        }

        return $this->json(['data' => $stats]);
    }

    #[Route('/activity', name: 'team_dashboard_activity', methods: ['GET'])]
    public function activity(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Resolve optional workspace scope
        $workspaceRepos = $this->resolveWorkspaceRepos($user, $request);
        if ($workspaceRepos === false) {
            return $this->json(['error' => 'Workspace not found'], 404);
        }

        $qb = $this->snapshotRepo->createQueryBuilder('s')
            ->andWhere('s.appUser = :user')
            ->setParameter('user', $user)
            ->orderBy('s.lastActivityAt', 'DESC')
            ->setMaxResults(50);

        if ($workspaceRepos !== null && \count($workspaceRepos) > 0) {
            $qb->andWhere('s.repoFullName IN (:repos)')->setParameter('repos', $workspaceRepos);
        } elseif ($workspaceRepos !== null && \count($workspaceRepos) === 0) {
            // Empty workspace — return nothing
            return $this->json(['data' => []]);
        }

        $recent = $qb->getQuery()->getResult();

        $events = [];
        foreach ($recent as $snapshot) {
            if (!$snapshot instanceof PullRequestSnapshot) {
                continue;
            }
            $events[] = [
                'type' => $this->inferEventType($snapshot),
                'prNumber' => $snapshot->getPrNumber(),
                'title' => $snapshot->getTitle(),
                'repoFullName' => $snapshot->getRepoFullName(),
                'authorLogin' => $snapshot->getAuthorLogin(),
                'authorAvatarUrl' => $snapshot->getAuthorAvatarUrl(),
                'status' => $snapshot->getStatus(),
                'aiReviewStatus' => $snapshot->getAiReviewStatus(),
                'occurredAt' => $snapshot->getLastActivityAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $this->json(['data' => $events]);
    }

    #[Route('/pr/{repoFullName}/{number}', name: 'team_dashboard_pr_detail', methods: ['GET'], requirements: ['repoFullName' => '.+/[^/]+', 'number' => '\d+'])]
    public function prDetail(string $repoFullName, int $number): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $snapshot = $this->snapshotRepo->findOneByUserRepoAndPr($user, $repoFullName, $number);
        if ($snapshot === null) {
            return $this->json(['error' => 'PR not found'], 404);
        }

        $githubUsername = $user->getGithubUsername() ?? '';
        $preview = $this->previewService->buildPreview($snapshot);

        return $this->json(['data' => [
            ...$this->serializePr($snapshot, $githubUsername),
            ...$preview,
        ]]);
    }

    #[Route('/refresh', name: 'team_dashboard_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $userId = (int) $user->getId();

        // Rate limit: max once per 30 seconds
        $lastRefreshKey = CacheKeys::teamDashboardLastRefresh($userId);
        $lastRefresh = $this->cache->get($lastRefreshKey, function (ItemInterface $item): ?string {
            $item->expiresAfter(1);
            return null;
        });

        if ($lastRefresh !== null) {
            return $this->json([
                'status' => 'rate_limited',
                'message' => 'Please wait 30 seconds between refreshes.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Set rate limit
        $this->cache->delete($lastRefreshKey);
        $this->cache->get($lastRefreshKey, function (ItemInterface $item): string {
            $item->expiresAfter(30);
            return (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        });

        $this->snapshotService->refreshForUser($user);

        // Bust stats cache
        $this->cache->delete(CacheKeys::teamDashboardStats($userId));

        return $this->json([
            'status' => 'refreshed',
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Returns null (no workspace filter), a string[] of repo names (workspace filter),
     * or false if the workspace was not found / not owned by the user.
     *
     * @return string[]|null|false
     */
    private function resolveWorkspaceRepos(User $user, Request $request): array|null|false
    {
        $workspaceIdParam = $request->query->get('workspaceId');
        if ($workspaceIdParam === null || $workspaceIdParam === '') {
            return null;
        }

        $workspace = $this->workspaceRepo->findOneByUserAndId($user, (int) $workspaceIdParam);
        if ($workspace === null) {
            return false;
        }

        $repos = [];
        foreach ($workspace->getRepositories() as $entry) {
            $repos[] = $entry->getRepoFullName();
        }

        return $repos;
    }

    private function extractFilters(Request $request): array
    {
        return [
            'repos' => $request->query->all('repos'),
            'authors' => $request->query->all('authors'),
            'status' => $request->query->get('status', 'open'),
            'reviewStatus' => $request->query->get('reviewStatus'),
            'aiStatus' => $request->query->get('aiStatus'),
            'targetBranch' => $request->query->get('targetBranch'),
            'stale' => $request->query->getBoolean('stale', false),
            'ciStatus' => $request->query->get('ciStatus'),
            'view' => $this->normalizeView($request->query->get('view')),
            'sortBy' => $request->query->get('sortBy', 'lastActivityAt'),
            'sortDir' => $request->query->get('sortDir', 'desc'),
            'page' => $request->query->getInt('page', 1),
            'perPage' => $request->query->getInt('perPage', 25),
        ];
    }

    private function serializePr(PullRequestSnapshot $s, string $currentUsername): array
    {
        $isAuthoredByMe = $s->getAuthorLogin() === $currentUsername;
        $isRequestingMyReview = $this->isUserRequestedReviewer($s->getAssignedReviewers(), $currentUsername);
        $isApprovedByMe = $this->hasUserApproval($s->getCompletedReviews(), $currentUsername);
        $isBlockedByCi = $s->getCiStatus() === 'failure';
        $isUnowned = [] === $s->getAssignedReviewers() && [] === $s->getCompletedReviews();
        $needsMyAttention = $isRequestingMyReview
            || ($isAuthoredByMe && $s->getReviewStatus() === 'changes_requested')
            || ($isAuthoredByMe && $isBlockedByCi);

        return [
            'id' => $s->getId(),
            'prNumber' => $s->getPrNumber(),
            'title' => $s->getTitle(),
            'description' => $s->getDescription(),
            'repoFullName' => $s->getRepoFullName(),
            'sourceBranch' => $s->getSourceBranch(),
            'targetBranch' => $s->getTargetBranch(),
            'authorLogin' => $s->getAuthorLogin(),
            'authorAvatarUrl' => $s->getAuthorAvatarUrl(),
            'status' => $s->getStatus(),
            'isDraft' => $s->isDraft(),
            'reviewStatus' => $s->getReviewStatus(),
            'ciStatus' => $s->getCiStatus(),
            'aiReviewStatus' => $s->getAiReviewStatus(),
            'aiIssueCount' => $s->getAiIssueCount(),
            'aiReviewSummary' => $s->getAiReviewSummary(),
            'commentCount' => $s->getCommentCount(),
            'changedFiles' => $s->getChangedFiles(),
            'additions' => $s->getAdditions(),
            'deletions' => $s->getDeletions(),
            'assignedReviewers' => $s->getAssignedReviewers(),
            'completedReviews' => $s->getCompletedReviews(),
            'labels' => $s->getLabels(),
            'isStale' => $s->isStale(),
            'githubUrl' => $s->getGithubUrl(),
            'openedAt' => $s->getOpenedAt()?->format(\DateTimeInterface::ATOM),
            'lastActivityAt' => $s->getLastActivityAt()?->format(\DateTimeInterface::ATOM),
            'isAuthoredByMe' => $isAuthoredByMe,
            'isRequestingMyReview' => $isRequestingMyReview,
            'isApprovedByMe' => $isApprovedByMe,
            'isBlockedByCi' => $isBlockedByCi,
            'isUnowned' => $isUnowned,
            'needsMyAttention' => $needsMyAttention,
        ];
    }

    private function isUserRequestedReviewer(array $assignedReviewers, string $username): bool
    {
        if ($username === '') {
            return false;
        }

        foreach ($assignedReviewers as $reviewer) {
            if (\is_array($reviewer) && ($reviewer['login'] ?? '') === $username) {
                return true;
            }
        }
        return false;
    }

    private function hasUserApproval(array $completedReviews, string $username): bool
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

    private function groupPullRequests(array $prs, string $groupBy): array
    {
        $groups = [];
        $key = match ($groupBy) {
            'repo' => 'repoFullName',
            'author' => 'authorLogin',
            'targetBranch' => 'targetBranch',
            'aiStatus' => 'aiReviewStatus',
            default => 'repoFullName',
        };

        foreach ($prs as $pr) {
            $groupKey = $pr[$key] ?? 'unknown';
            $groups[$groupKey][] = $pr;
        }

        return $groups;
    }

    private function inferEventType(PullRequestSnapshot $snapshot): string
    {
        return match ($snapshot->getStatus()) {
            'merged' => 'pr_merged',
            'closed' => 'pr_closed',
            default => match ($snapshot->getAiReviewStatus()) {
                'completed' => 'ai_review_completed',
                'processing' => 'ai_review_started',
                default => 'pr_updated',
            },
        };
    }

    private function normalizeView(mixed $view): string
    {
        return \is_string($view) && \in_array($view, self::OWNERSHIP_VIEWS, true) ? $view : 'all';
    }
}
