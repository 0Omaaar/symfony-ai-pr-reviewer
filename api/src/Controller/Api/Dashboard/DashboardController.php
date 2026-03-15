<?php

namespace App\Controller\Api\Dashboard;

use App\Entity\User;
use App\Service\Github\GithubInstallationRepositoriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class DashboardController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.api_user')] private readonly RateLimiterFactory $apiUserLimiter,
    ) {}

    #[Route('/api/dashboard', name: 'app_api_dashboard', methods: ['GET'])]
    public function __invoke(GithubInstallationRepositoriesService $service, CacheInterface $cache): JsonResponse
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

            $payload = $cache->get(sprintf('dashboard.payload.%d', $userId), function (ItemInterface $item) use ($service, $user): array {
                $item->expiresAfter(90);

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

                    $repoId = $repository['id'];
                    $repoFullName = is_string($repository['full_name'] ?? null) ? $repository['full_name'] : (string) ($repository['name'] ?? 'Unknown repository');
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
                $repositoriesConnected = count($repositories) > 0;

                return [
                    'ok' => true,
                    'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    'setup' => [
                        'github_app_installed' => $githubAppInstalled,
                        'repositories_connected' => $repositoriesConnected,
                    ],
                    'kpis' => [
                        'repositories' => count($repositories),
                        'pull_requests_total' => $totalPullRequests,
                        'pull_requests_open' => $openPullRequests,
                        'pull_requests_merged' => $mergedPullRequests,
                        'pull_requests_closed' => $closedPullRequests,
                    ],
                    'recent_pull_requests' => $recentPullRequests,
                    'top_repositories' => $topRepositories,
                ];
            });

            return $this->json($payload);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to load dashboard',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
