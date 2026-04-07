<?php

namespace App\Controller\Admin;

use App\Repository\AdminLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/logs')]
class AdminLogsController extends AbstractController
{
    public function __construct(
        private readonly AdminLogRepository $logRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_logs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 50)));
        $search = $request->query->get('search', '') ?: null;

        $result = $this->logRepository->findPaginated($page, $pageSize, $search);

        $data = array_map(fn ($log) => [
            'id' => $log->getId(),
            'action' => $log->getAction(),
            'target_type' => $log->getTargetType(),
            'target_id' => $log->getTargetId(),
            'performed_by' => $log->getPerformedBy(),
            'metadata' => $log->getMetadata(),
            'created_at' => $log->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], $result['data']);

        return $this->json([
            'data' => $data,
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/export', name: 'admin_logs_export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        $conn = $this->em->getConnection();
        $logs = $conn->fetchAllAssociative(
            'SELECT id, action, target_type, target_id, performed_by, metadata, created_at
             FROM admin_log ORDER BY created_at DESC'
        );

        $response = new StreamedResponse(function () use ($logs): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['ID', 'Action', 'Target Type', 'Target ID', 'Performed By', 'Metadata', 'Created At']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log['id'],
                    $log['action'],
                    $log['target_type'] ?? '',
                    $log['target_id'] ?? '',
                    $log['performed_by'],
                    json_encode($log['metadata'] ?? []),
                    $log['created_at'],
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="admin_logs_' . date('Ymd_His') . '.csv"');

        return $response;
    }
}
