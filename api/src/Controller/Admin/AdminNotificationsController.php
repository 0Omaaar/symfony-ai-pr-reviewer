<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/notifications')]
class AdminNotificationsController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'admin_notifications_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 25)));

        $conn = $this->em->getConnection();

        $total = (int) $conn->fetchOne('SELECT COUNT(*) FROM "user"');

        $users = $conn->fetchAllAssociative(
            'SELECT id, github_username, email, email_notifications_enabled, notification_preferences
             FROM "user"
             ORDER BY id
             LIMIT :limit OFFSET :offset',
            ['limit' => $pageSize, 'offset' => ($page - 1) * $pageSize]
        );

        $usersWithPrefs = array_map(function (array $user) {
            $prefs = json_decode($user['notification_preferences'] ?? '{}', true) ?? [];

            return [
                'id' => $user['id'],
                'github_username' => $user['github_username'],
                'email' => $user['email'],
                'email_notifications_enabled' => (bool) $user['email_notifications_enabled'],
                'notification_preferences' => $prefs,
            ];
        }, $users);

        return $this->json([
            'data' => $usersWithPrefs,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'note' => 'Notifications are not stored persistently. This shows per-user notification configuration.',
        ]);
    }
}
