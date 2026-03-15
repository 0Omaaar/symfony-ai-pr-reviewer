<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['authenticated' => false], 401);
        }

        $githubAppInstalled = $user->getGithubInstallations()->count() > 0;

        return new JsonResponse([
            'authenticated' => true,
            'githubAppInstalled' => $githubAppInstalled,
            'user' => [
                'id' => $user->getId(),
                'githubId' => $user->getGithubId(),
                'username' => $user->getGithubUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'emailNotificationsEnabled' => $user->isEmailNotificationsEnabled(),
            ]
        ]);
    }
}
