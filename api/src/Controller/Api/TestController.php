<?php

namespace App\Controller\Api;

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
}
