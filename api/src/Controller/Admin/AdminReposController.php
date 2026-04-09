<?php

namespace App\Controller\Admin;

use App\Entity\AdminLog;
use App\Entity\GithubInstallation;
use App\Repository\GithubInstallationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/repos')]
class AdminReposController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GithubInstallationRepository $installationRepository,
    ) {
    }

    #[Route('', name: 'admin_repos_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 25)));
        $search = $request->query->get('search', '');

        $conn = $this->em->getConnection();

        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(gi.account_login ILIKE :search OR gi.account_type ILIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $where);

        $total = (int) $conn->fetchOne(
            "SELECT COUNT(DISTINCT gi.id) FROM github_installation gi WHERE $whereClause",
            $params
        );

        $installations = $conn->fetchAllAssociative(
            "SELECT gi.id, gi.installation_id, gi.account_login, gi.account_type,
                    gi.created_at, gi.updated_at,
                    COUNT(DISTINCT ugi.id) as user_count,
                    STRING_AGG(DISTINCT u.github_username, ', ') as connected_by
             FROM github_installation gi
             LEFT JOIN user_github_installation ugi ON ugi.installation_id = gi.id
             LEFT JOIN \"user\" u ON u.id = ugi.app_user_id
             WHERE $whereClause
             GROUP BY gi.id
             ORDER BY gi.created_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $pageSize, 'offset' => ($page - 1) * $pageSize])
        );

        return $this->json([
            'data' => $installations,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/{id}', name: 'admin_repos_disconnect', methods: ['DELETE'])]
    public function disconnect(int $id): JsonResponse
    {
        $installation = $this->installationRepository->find($id);
        if ($installation === null) {
            return $this->json(['error' => 'Not Found', 'message' => 'Installation not found.'], Response::HTTP_NOT_FOUND);
        }

        $accountLogin = $installation->getAccountLogin();

        $log = new AdminLog();
        $log->setAction('installation_disconnected');
        $log->setTargetType('installation');
        $log->setTargetId((string) $id);
        $log->setMetadata(['account_login' => $accountLogin, 'installation_id' => $installation->getInstallationId()]);

        $this->em->remove($installation);
        $this->em->persist($log);
        $this->em->flush();

        return $this->json(['disconnected' => true]);
    }
}
