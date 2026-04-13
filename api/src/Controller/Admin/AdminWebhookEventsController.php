<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/pull-requests')]
class AdminWebhookEventsController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'admin_pull_requests_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 25)));
        $search = $request->query->get('search', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');

        $conn = $this->em->getConnection();

        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = 'delivery_id ILIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        if ($dateFrom !== '') {
            $where[] = 'processed_at >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'processed_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM processed_webhook_delivery WHERE $whereClause",
            $params
        );

        $events = $conn->fetchAllAssociative(
            "SELECT id, delivery_id, processed_at
             FROM processed_webhook_delivery
             WHERE $whereClause
             ORDER BY processed_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $pageSize, 'offset' => ($page - 1) * $pageSize])
        );

        return $this->json([
            'data' => $events,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'note' => 'PR events are tracked by webhook delivery ID. Detailed PR data is not stored persistently.',
        ]);
    }
}
