<?php

namespace App\Controller\Api\Dashboard;

use App\Entity\User;
use App\Repository\WorkspaceRepository;
use App\Service\CacheKeys;
use App\Service\Github\GithubInstallationRepositoriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class DashboardController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.api_user')] private readonly RateLimiterFactory $apiUserLimiter,
        private readonly WorkspaceRepository $workspaceRepo,
    ) {}

    #[Route('/api/dashboard', name: 'app_api_dashboard', methods: ['GET'])]
    public function __invoke(Request $request, GithubInstallationRepositoriesService $service, CacheInterface $cache): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (!$this->apiUserLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            return $this->json(['ok' => false, 'error' => 'Too many requests'], 429);
        }

        try {
            $userId = $user->getId();
            if (!is_int($userId)) {
                return $this->json(['ok' => false, 'error' => 'Invalid user context'], 400);
            }

            // Resolve optional workspace scope
            $workspaceRepoFilter = null;
            $workspaceIdParam = $request->query->get('workspaceId');
            if ($workspaceIdParam !== null && $workspaceIdParam !== '') {
                $workspace = $this->workspaceRepo->findOneByUserAndId($user, (int) $workspaceIdParam);
                if ($workspace === null) {
                    return $this->json(['ok' => false, 'error' => 'Workspace not found'], 404);
                }
                $workspaceRepoFilter = [];
                foreach ($workspace->getRepositories() as $entry) {
                    $workspaceRepoFilter[$entry->getRepoFullName()] = true;
                }
            }

            // Use cache only for the unscoped (all repos) view
            if ($workspaceRepoFilter === null) {
                $payload = $cache->get(CacheKeys::dashboardPayload($userId), function (ItemInterface $item) use ($service, $user): array {
                    $item->expiresAfter(90);
                    return $this->buildPayload($service, $user, null);
                });
            } else {
                $payload = $this->buildPayload($service, $user, $workspaceRepoFilter);
            }

            return $this->json($payload);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to load dashboard',
            ], 500);
        }
    }

    /**
     * @param array<string, true>|null $repoFilter null = all repos, empty array = workspace with no repos
     */
    private function buildPayload(GithubInstallationRepositoriesService $service, User $user, ?array $repoFilter): array
    {
        $repositories = $service->fetchForUser($user);

        $recentPullRequests = [];
        $topRepositories = [];
        $totalPullRequests = 0;
        $openPullRequests = 0;
        $mergedPullRequests = 0;
        $closedPullRequests = 0;

        foreach ($repositories as $repository) {
            if (!is_array($repository) || !is_int($repository['id'] ?? null)) {
                continue;
            }

            $repoFullName = is_string($repository['full_name'] ?? null) ? $repository['full_name'] : (string) ($repository['name'] ?? 'Unknown repository');

            // Apply workspace filter
            if ($repoFilter !== null && !isset($repoFilter[$repoFullName])) {
                continue;
            }

            $repoId = $repository['id'];
            $pullRequests = $service->fetchPullRequestsForUserRepository($user, $repoId);

            $repoTotal = 0;
            $repoOpen = 0;

            foreach ($pullRequests as $pullRequest) {
                if (!is_array($pullRequest) || !is_int($pullRequest['id'] ?? null) || !is_int($pullRequest['number'] ?? null)) {
                    continue;
                }

                $repoTotal++;
                $totalPullRequests++;

                $status = is_string($pullRequest['status'] ?? null) ? $pullRequest['status'] : 'open';
                if ($status === 'merged') {
                    $mergedPullRequests++;
                } elseif ($status === 'closed') {
                    $closedPullRequests++;
                } else {
                    $openPullRequests++;
                    $repoOpen++;
                }

                $recentPullRequests[] = [
                    'id' => $pullRequest['id'],
                    'repo_id' => $repoId,
                    'repo_full_name' => $repoFullName,
                    'number' => $pullRequest['number'],
                    'title' => is_string($pullRequest['title'] ?? null) && $pullRequest['title'] !== '' ? $pullRequest['title'] : '(No title)',
                    'status' => $status,
                    'updated_at' => is_string($pullRequest['updated_at'] ?? null) ? $pullRequest['updated_at'] : null,
                ];
            }

            $topRepositories[] = [
                'repo_id' => $repoId,
                'full_name' => $repoFullName,
                'open_pull_requests' => $repoOpen,
                'total_pull_requests' => $repoTotal,
            ];
        }

        usort($recentPullRequests, static function (array $a, array $b): int {
            $aTime = strtotime((string) ($a['updated_at'] ?? '')) ?: 0;
            $bTime = strtotime((string) ($b['updated_at'] ?? '')) ?: 0;
            return $bTime <=> $aTime;
        });
        $recentPullRequests = array_slice($recentPullRequests, 0, 12);

        usort($topRepositories, static function (array $a, array $b): int {
            $openDiff = ((int) ($b['open_pull_requests'] ?? 0)) <=> ((int) ($a['open_pull_requests'] ?? 0));
            if ($openDiff !== 0) {
                return $openDiff;
            }
            return ((int) ($b['total_pull_requests'] ?? 0)) <=> ((int) ($a['total_pull_requests'] ?? 0));
        });
        $topRepositories = array_slice($topRepositories, 0, 8);

        $githubAppInstalled = $user->getGithubInstallations()->count() > 0;
        $allRepos = $service->fetchForUser($user);
        $repositoriesConnected = count($allRepos) > 0;

        return [
            'ok' => true,
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'setup' => [
                'github_app_installed' => $githubAppInstalled,
                'repositories_connected' => $repositoriesConnected,
            ],
            'kpis' => [
                'repositories' => $repoFilter !== null ? count($repoFilter) : count($repositories),
                'pull_requests_total' => $totalPullRequests,
                'pull_requests_open' => $openPullRequests,
                'pull_requests_merged' => $mergedPullRequests,
                'pull_requests_closed' => $closedPullRequests,
            ],
            'recent_pull_requests' => $recentPullRequests,
            'top_repositories' => $topRepositories,
        ];
    }
}
