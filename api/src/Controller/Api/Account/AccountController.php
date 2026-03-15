<?php

namespace App\Controller\Api\Account;

use App\Entity\GithubInstallation;
use App\Entity\User;
use App\Entity\UserGithubInstallation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {}

    #[Route('/api/account', name: 'api_account_delete', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        // Collect installation IDs before removing links
        $installationIds = [];
        $userLinks = $this->em->getRepository(UserGithubInstallation::class)
            ->findBy(['appUser' => $user]);

        foreach ($userLinks as $link) {
            $installation = $link->getInstallation();
            if ($installation !== null && $installation->getId() !== null) {
                $installationIds[] = $installation->getId();
            }
            $this->em->remove($link);
        }

        // Remove orphaned GithubInstallation records (no other users linked)
        foreach ($installationIds as $installationId) {
            $installation = $this->em->find(GithubInstallation::class, $installationId);
            if ($installation === null) {
                continue;
            }

            $remainingLinks = $this->em->getRepository(UserGithubInstallation::class)
                ->count(['installation' => $installation]);

            if ($remainingLinks === 0) {
                $this->em->remove($installation);
            }
        }

        $this->em->remove($user);
        $this->em->flush();

        // Invalidate the session
        $session = $this->requestStack->getSession();
        $session->invalidate();

        return $this->json(['ok' => true, 'message' => 'Account and all associated data deleted.']);
    }

    #[Route('/api/account/notifications', name: 'api_account_notifications', methods: ['PATCH'])]
    public function updateNotifications(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['email_notifications_enabled']) || !is_bool($data['email_notifications_enabled'])) {
            return $this->json(['ok' => false, 'error' => 'Invalid payload. Expected {"email_notifications_enabled": true|false}'], 400);
        }

        $user->setEmailNotificationsEnabled($data['email_notifications_enabled']);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'email_notifications_enabled' => $user->isEmailNotificationsEnabled(),
        ]);
    }
}
