<?php

namespace App\Controller\Admin;

use App\Entity\AdminLog;
use App\Service\Admin\AdminJwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/auth')]
class AdminAuthController extends AbstractController
{
    public function __construct(
        private readonly AdminJwtService $jwtService,
        private readonly EntityManagerInterface $em,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(param: 'admin.email')]
        private readonly string $adminEmail,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(param: 'admin.password')]
        private readonly string $adminPassword,
    ) {
    }

    #[Route('/login', name: 'admin_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = isset($data['email']) && \is_string($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) && \is_string($data['password']) ? $data['password'] : '';

        if ($email !== $this->adminEmail || $password !== $this->adminPassword) {
            return $this->json(['error' => 'Unauthorized', 'message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtService->generateToken();

        $log = new AdminLog();
        $log->setAction('admin_login');
        $log->setPerformedBy('admin');
        $this->em->persist($log);
        $this->em->flush();

        return $this->json(['token' => $token, 'expires_in' => 86400]);
    }

    #[Route('/me', name: 'admin_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json(['authenticated' => true, 'role' => 'admin']);
    }
}
