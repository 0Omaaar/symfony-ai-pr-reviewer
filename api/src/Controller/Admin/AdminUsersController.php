<?php

namespace App\Controller\Admin;

use App\Entity\AdminLog;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Account\AccountService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/users')]
class AdminUsersController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly AccountService $accountService,
    ) {
    }

    #[Route('', name: 'admin_users_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 25)));
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');

        $conn = $this->em->getConnection();

        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(u.github_username ILIKE :search OR u.email ILIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status === 'suspended') {
            $where[] = 'u.suspended_at IS NOT NULL';
        } elseif ($status === 'active') {
            $where[] = 'u.suspended_at IS NULL';
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM \"user\" u WHERE $whereClause",
            $params
        );

        $users = $conn->fetchAllAssociative(
            "SELECT u.id, u.github_username, u.email, u.email_notifications_enabled,
                    u.created_at, u.suspended_at,
                    COUNT(DISTINCT ugi.id) as installation_count
             FROM \"user\" u
             LEFT JOIN user_github_installation ugi ON ugi.app_user_id = u.id
             WHERE $whereClause
             GROUP BY u.id
             ORDER BY u.created_at DESC NULLS LAST
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $pageSize, 'offset' => ($page - 1) * $pageSize])
        );

        return $this->json([
            'data' => $users,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/{id}', name: 'admin_users_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return $this->json(['error' => 'Not Found', 'message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $conn = $this->em->getConnection();

        $installations = $conn->fetchAllAssociative(
            'SELECT gi.id, gi.installation_id, gi.account_login, gi.account_type, gi.created_at
             FROM github_installation gi
             JOIN user_github_installation ugi ON ugi.installation_id = gi.id
             WHERE ugi.app_user_id = :userId',
            ['userId' => $id]
        );

        $webhookEvents = $conn->fetchAllAssociative(
            'SELECT delivery_id, processed_at FROM processed_webhook_delivery
             ORDER BY processed_at DESC LIMIT 20'
        );

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'github_username' => $user->getGithubUsername(),
            'github_id' => $user->getGithubId(),
            'email_notifications_enabled' => $user->isEmailNotificationsEnabled(),
            'notification_preferences' => $user->getNotificationPreferences(),
            'created_at' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'suspended_at' => $user->getSuspendedAt()?->format(\DateTimeInterface::ATOM),
            'is_suspended' => $user->isSuspended(),
            'installations' => $installations,
            'recent_webhook_events' => $webhookEvents,
        ]);
    }

    #[Route('/{id}/suspend', name: 'admin_users_suspend', methods: ['PATCH'])]
    public function suspend(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return $this->json(['error' => 'Not Found', 'message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $wasSuspended = $user->isSuspended();
        if ($wasSuspended) {
            $user->setSuspendedAt(null);
            $action = 'user_unsuspended';
        } else {
            $user->setSuspendedAt(new \DateTimeImmutable());
            $action = 'user_suspended';
        }

        $log = new AdminLog();
        $log->setAction($action);
        $log->setTargetType('user');
        $log->setTargetId((string) $id);
        $log->setMetadata(['github_username' => $user->getGithubUsername(), 'email' => $user->getEmail()]);

        $this->em->persist($user);
        $this->em->persist($log);
        $this->em->flush();

        return $this->json([
            'suspended' => !$wasSuspended,
            'suspended_at' => $user->getSuspendedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}', name: 'admin_users_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return $this->json(['error' => 'Not Found', 'message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $username = $user->getGithubUsername();
        $email = $user->getEmail();

        // Log before deleting since deleteUser flushes immediately
        $log = new AdminLog();
        $log->setAction('user_deleted');
        $log->setTargetType('user');
        $log->setTargetId((string) $id);
        $log->setMetadata(['github_username' => $username, 'email' => $email]);
        $this->em->persist($log);
        $this->em->flush();

        $this->accountService->deleteUser($user);

        return $this->json(['deleted' => true], Response::HTTP_OK);
    }
}
