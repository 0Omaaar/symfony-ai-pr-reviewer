<?php

namespace App\Controller\Api\Github\Repositories;

use App\Entity\User;
use App\Service\Github\GithubInstallationRepositoriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class GithubRepositoriesController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.api_user')] private readonly RateLimiterFactory $apiUserLimiter,
    ) {}

    private function authorizeAndLimit(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (!$this->apiUserLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            return $this->json(['ok' => false, 'error' => 'Too many requests'], 429);
        }

        return $user;
    }

    #[Route('/api/github/repositories', name: 'app_api_github_repositories', methods: ['GET'])]
    public function list(GithubInstallationRepositoriesService $service): JsonResponse
    {
        $result = $this->authorizeAndLimit();
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $user = $result;

        try {
            $repos = $service->fetchForUser($user);

            return $this->json([
                'ok' => true,
                'count' => count($repos),
                'repositories' => $repos,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to fetch repositories',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/api/github/repositories/{id}', name: 'app_api_github_repository_details', methods: ['GET'])]
    public function details(int $id, GithubInstallationRepositoriesService $service): JsonResponse
    {
        $result = $this->authorizeAndLimit();
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $user = $result;

        try {
            $details = $service->fetchDetailsForUserRepository($user, $id);
            if ($details === null) {
                return $this->json([
                    'ok' => false,
                    'error' => 'Repository not found',
                ], 404);
            }

            if (!$service->hasMeaningfulDetailsData($details)) {
                $freshDetails = $service->refreshDetailsForUserRepository($user, $id);
                if ($freshDetails !== null) {
                    $details = $freshDetails;
                }
            }

            return $this->json([
                'ok' => true,
                'repository' => $details['repository'] ?? null,
                'branches' => $details['branches'] ?? [],
                'pull_requests' => $details['pull_requests'] ?? [],
                'insights' => $details['insights'] ?? [],
                'latest_pr_event' => $service->fetchLatestPullRequestEventForUserRepository($user, $id),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to fetch repository details',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/api/github/pull-requests/{id}', name: 'app_api_github_pull_request_details', methods: ['GET'])]
    public function pullRequestDetails(int $id, GithubInstallationRepositoriesService $service): JsonResponse
    {
        $result = $this->authorizeAndLimit();
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $user = $result;

        try {
            $details = $service->fetchPullRequestByIdForUser($user, $id);
            if ($details === null) {
                return $this->json([
                    'ok' => false,
                    'error' => 'Pull request not found',
                ], 404);
            }

            return $this->json([
                'ok' => true,
                'repository' => $details['repository'] ?? null,
                'pull_request' => $details['pull_request'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to fetch pull request details',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/api/github/pull-requests/{id}/changes', name: 'app_api_github_pull_request_changes', methods: ['GET'])]
    public function pullRequestChanges(int $id, GithubInstallationRepositoriesService $service): JsonResponse
    {
        $result = $this->authorizeAndLimit();
        if ($result instanceof JsonResponse) {
            return $result;
        }
        $user = $result;

        try {
            $changes = $service->fetchPullRequestChangesByIdForUser($user, $id);
            if ($changes === null) {
                return $this->json([
                    'ok' => false,
                    'error' => 'Pull request not found',
                ], 404);
            }

            return $this->json([
                'ok' => true,
                'summary' => $changes['summary'] ?? [],
                'files' => $changes['files'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Failed to fetch pull request changes',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
