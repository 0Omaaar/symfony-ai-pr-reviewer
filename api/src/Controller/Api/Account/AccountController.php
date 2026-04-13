<?php

namespace App\Controller\Api\Account;

use App\Entity\GithubInstallation;
use App\Entity\User;
use App\Service\Account\AccountService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
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

        $this->accountService->deleteUser($user);

        $this->requestStack->getSession()->invalidate();

        return $this->json(['ok' => true, 'message' => 'Account and all associated data deleted.']);
    }

    #[Route('/api/account/installations/{installationId}', name: 'api_account_installation_remove', methods: ['DELETE'])]
    public function removeInstallation(int $installationId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $installation = $this->em->getRepository(GithubInstallation::class)
            ->findOneBy(['installationId' => $installationId]);

        if ($installation === null) {
            return $this->json(['ok' => false, 'error' => 'Installation not found'], 404);
        }

        try {
            $this->accountService->removeInstallation($user, $installation);
        } catch (\InvalidArgumentException) {
            return $this->json(['ok' => false, 'error' => 'Installation not linked to your account'], 404);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/api/account/notification-preferences', name: 'api_account_notification_preferences_get', methods: ['GET'])]
    public function getNotificationPreferences(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        return $this->json([
            'ok' => true,
            'notification_preferences' => $user->getNotificationPreferences(),
        ]);
    }

    #[Route('/api/account/notification-preferences', name: 'api_account_notification_preferences_patch', methods: ['PATCH'])]
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $data = \json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['ok' => false, 'error' => 'Invalid JSON payload.'], 400);
        }

        $validEventKeys = ['opened', 'closed', 'synchronize', 'ready_for_review', 'converted_to_draft'];
        $current = $user->getNotificationPreferences();

        if (\array_key_exists('events', $data)) {
            if (!\is_array($data['events'])) {
                return $this->json(['ok' => false, 'error' => 'events must be an object.'], 400);
            }
            foreach ($data['events'] as $key => $value) {
                if (!\in_array($key, $validEventKeys, true)) {
                    return $this->json(['ok' => false, 'error' => \sprintf('Unknown event key: %s', $key)], 400);
                }
                if (!\is_bool($value)) {
                    return $this->json(['ok' => false, 'error' => \sprintf('Event value for "%s" must be a boolean.', $key)], 400);
                }
            }
            $current['events'] = \array_merge($current['events'], $data['events']);
        }

        if (\array_key_exists('repos', $data)) {
            if (!\is_array($data['repos'])) {
                return $this->json(['ok' => false, 'error' => 'repos must be an object.'], 400);
            }
            if (\array_key_exists('mode', $data['repos'])) {
                if (!\in_array($data['repos']['mode'], ['all', 'specific'], true)) {
                    return $this->json(['ok' => false, 'error' => 'repos.mode must be "all" or "specific".'], 400);
                }
                $current['repos']['mode'] = $data['repos']['mode'];
            }
            if (\array_key_exists('allowed', $data['repos'])) {
                if (!\is_array($data['repos']['allowed'])) {
                    return $this->json(['ok' => false, 'error' => 'repos.allowed must be an array.'], 400);
                }
                $allowed = [];
                foreach ($data['repos']['allowed'] as $repo) {
                    if (!\is_string($repo) || \trim($repo) === '') {
                        return $this->json(['ok' => false, 'error' => 'Each entry in repos.allowed must be a non-empty string.'], 400);
                    }
                    $allowed[] = \trim($repo);
                }
                $current['repos']['allowed'] = \array_values(\array_unique($allowed));
            }
        }

        $user->setNotificationPreferences($current);
        $user->completeOnboardingStep('preferences_set');
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'notification_preferences' => $user->getNotificationPreferences(),
        ]);
    }

    #[Route('/api/account/notifications', name: 'api_account_notifications', methods: ['PATCH'])]
    public function updateNotifications(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data) || !isset($data['email_notifications_enabled']) || !\is_bool($data['email_notifications_enabled'])) {
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
