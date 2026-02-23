<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController extends AbstractController
{
    #[Route('/connect/github', name: 'connect_github_start')]
    public function connectGithub(ClientRegistry $clientRegistry): Response
    {
        // redirects to github authorization page
        return $clientRegistry
            ->getClient('github')
            ->redirect(['read:user', 'user:email']);
    }

    #[Route('/connect/github/check', name: 'connect_github_check')]
    public function connectGithubCheck(): void
    {

    }

    #[Route('/logout', name: 'app_logout', methods: ['POST', 'GET'])]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by Symfony firewall.');
    }
}
