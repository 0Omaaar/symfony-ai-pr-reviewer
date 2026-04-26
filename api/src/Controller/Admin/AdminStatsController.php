<?php

namespace App\Controller\Admin;

use App\Entity\AdminLog;
use App\Entity\GithubInstallation;
use App\Entity\ProcessedWebhookDelivery;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminStatsController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $conn = $this->em->getConnection();

        $totalUsers = (int) $conn->fetchOne('SELECT COUNT(*) FROM "user"');
        $newUsersThisWeek = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM "user" WHERE created_at >= NOW() - INTERVAL \'7 days\''
        );
        $suspendedUsers = (int) $conn->fetchOne('SELECT COUNT(*) FROM "user" WHERE suspended_at IS NOT NULL');
        $totalInstallations = (int) $conn->fetchOne('SELECT COUNT(*) FROM github_installation');
        $totalWebhookEvents = (int) $conn->fetchOne('SELECT COUNT(*) FROM processed_webhook_delivery');

        $todayWebhookEvents = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM processed_webhook_delivery WHERE processed_at >= CURRENT_DATE'
        );

        $usersWithNotificationsEnabled = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM "user" WHERE email_notifications_enabled = true'
        );

        $recentSignups = $conn->fetchAllAssociative(
            'SELECT id, github_username, email, created_at FROM "user" ORDER BY created_at DESC NULLS LAST LIMIT 5'
        );

        $webhookEventsByDay = $conn->fetchAllAssociative(
            'SELECT DATE(processed_at) as day, COUNT(*) as count
             FROM processed_webhook_delivery
             WHERE processed_at >= NOW() - INTERVAL \'30 days\'
             GROUP BY DATE(processed_at)
             ORDER BY day ASC'
        );

        $userSignupsByDay = $conn->fetchAllAssociative(
            'SELECT DATE(created_at) as day, COUNT(*) as count
             FROM "user"
             WHERE created_at >= NOW() - INTERVAL \'30 days\'
             GROUP BY DATE(created_at)
             ORDER BY day ASC'
        );

        $recentAdminActions = $conn->fetchAllAssociative(
            'SELECT action, target_type, target_id, performed_by, created_at
             FROM admin_log ORDER BY created_at DESC LIMIT 10'
        );

        return $this->json([
            'users' => [
                'total' => $totalUsers,
                'new_this_week' => $newUsersThisWeek,
                'suspended' => $suspendedUsers,
                'notifications_enabled' => $usersWithNotificationsEnabled,
            ],
            'installations' => [
                'total' => $totalInstallations,
            ],
            'webhook_events' => [
                'total' => $totalWebhookEvents,
                'today' => $todayWebhookEvents,
            ],
            'charts' => [
                'webhook_events_by_day' => $webhookEventsByDay,
                'user_signups_by_day' => $userSignupsByDay,
            ],
            'recent_signups' => $recentSignups,
            'recent_admin_actions' => $recentAdminActions,
        ]);
    }
}
