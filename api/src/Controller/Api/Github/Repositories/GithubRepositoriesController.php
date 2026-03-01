<?php

namespace App\Controller\Api\Github\Repositories;

use App\Entity\User;
use App\Service\Github\GithubInstallationRepositoriesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GithubRepositoriesController  extends AbstractController
{

    #[Route('/api/github/repositories', name: 'app_api_github_repositories', methods: ['GET'])]
    public function list(GithubInstallationRepositoriesService $service): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

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
}