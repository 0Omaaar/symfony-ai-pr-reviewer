<?php

namespace App\Controller\Api;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route('/api/ping', name: 'app_api_test', methods: ['GET'])]
    public function index(): Response
    {
        return $this->json(['message' => 'PINGGG !!!!']);
    }

    #[Route('/debug/github-config', name: 'app_debug_github_config', methods: ['GET'])]
    public function githubConfigCheck(ParameterBagInterface $params): Response
    {
        $appId = (string) $params->get('github.app_id');
        $privateKeyPath = (string) $params->get('github.private_key_path');

        return $this->json([
            'app_id' => $appId,
            'app_id_is_not_empty' => $appId !== '',
            'private_key_path' => $privateKeyPath,
            'file_exists' => $privateKeyPath !== '' && file_exists($privateKeyPath),
        ]);
    }

}
