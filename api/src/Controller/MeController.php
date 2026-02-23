<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['authenticated' => false], 401);
        }

        return new JsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => method_exists($user, 'getId') ? $user->getId() : null,
                'githubId' => method_exists($user, 'getGithubId') ? $user->getGithubId() : null,
                'username' => method_exists($user, 'getGithubUsername') ? $user->getGithubUsername() : null,
                'email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
                'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
            ]
        ]);
    }
}
