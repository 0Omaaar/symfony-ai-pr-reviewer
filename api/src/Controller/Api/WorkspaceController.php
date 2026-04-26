<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceRepository_;
use App\Repository\WorkspaceRepository;
use App\Repository\WorkspaceRepositoryEntryRepository;
use App\Service\Github\GithubInstallationRepositoriesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/workspaces')]
final class WorkspaceController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepo,
        private readonly WorkspaceRepositoryEntryRepository $entryRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'workspace_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $workspaces = $this->workspaceRepo->findByUser($user);

        return $this->json([
            'data' => array_map([$this, 'serialize'], $workspaces),
            'status' => 'ok',
        ]);
    }

    #[Route('', name: 'workspace_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));
        $description = isset($payload['description']) ? trim((string) $payload['description']) : null;

        if ($name === '') {
            return $this->json(['error' => 'name is required'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($name) > 100) {
            return $this->json(['error' => 'name must be 100 characters or fewer'], Response::HTTP_BAD_REQUEST);
        }

        $workspace = new Workspace();
        $workspace->setAppUser($user);
        $workspace->setName($name);
        $workspace->setDescription($description !== '' ? $description : null);

        try {
            $this->em->persist($workspace);
            $this->em->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return $this->json(['error' => 'A workspace with that name already exists'], Response::HTTP_CONFLICT);
        }

        return $this->json(['data' => $this->serialize($workspace), 'status' => 'ok'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'workspace_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $workspace = $this->workspaceRepo->findOneByUserAndId($user, $id);
        if ($workspace === null) {
            return $this->json(['error' => 'Workspace not found'], 404);
        }

        return $this->json(['data' => $this->serialize($workspace), 'status' => 'ok']);
    }

    #[Route('/{id}', name: 'workspace_update', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $workspace = $this->workspaceRepo->findOneByUserAndId($user, $id);
        if ($workspace === null) {
            return $this->json(['error' => 'Workspace not found'], 404);
        }

        $payload = $request->toArray();

        if (isset($payload['name'])) {
            $name = trim((string) $payload['name']);
            if ($name === '') {
                return $this->json(['error' => 'name cannot be empty'], Response::HTTP_BAD_REQUEST);
            }
            if (mb_strlen($name) > 100) {
                return $this->json(['error' => 'name must be 100 characters or fewer'], Response::HTTP_BAD_REQUEST);
            }
            $workspace->setName($name);
        }

        if (\array_key_exists('description', $payload)) {
            $desc = trim((string) $payload['description']);
            $workspace->setDescription($desc !== '' ? $desc : null);
        }

        try {
            $this->em->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return $this->json(['error' => 'A workspace with that name already exists'], Response::HTTP_CONFLICT);
        }

        return $this->json(['data' => $this->serialize($workspace), 'status' => 'ok']);
    }

    #[Route('/{id}', name: 'workspace_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $workspace = $this->workspaceRepo->findOneByUserAndId($user, $id);
        if ($workspace === null) {
            return $this->json(['error' => 'Workspace not found'], 404);
        }

        $this->em->remove($workspace);
        $this->em->flush();

        return $this->json(['status' => 'ok'], Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/repositories', name: 'workspace_set_repositories', methods: ['PUT'])]
    public function setRepositories(int $id, Request $request, GithubInstallationRepositoriesService $repoService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $workspace = $this->workspaceRepo->findOneByUserAndId($user, $id);
        if ($workspace === null) {
            return $this->json(['error' => 'Workspace not found'], 404);
        }

        $payload = $request->toArray();
        $incoming = $payload['repositories'] ?? [];

        if (!\is_array($incoming)) {
            return $this->json(['error' => 'repositories must be an array'], Response::HTTP_BAD_REQUEST);
        }

        // Validate each entry has required fields
        foreach ($incoming as $item) {
            if (!\is_array($item)
                || !isset($item['repoFullName'], $item['repoId'], $item['installationId'])
                || !\is_string($item['repoFullName']) || $item['repoFullName'] === ''
                || !\is_string($item['repoId']) || $item['repoId'] === ''
                || !\is_string($item['installationId']) || $item['installationId'] === ''
            ) {
                return $this->json(['error' => 'Each repository entry must have repoFullName, repoId, and installationId'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validate all repos belong to the user's accessible repos
        if (!empty($incoming)) {
            $accessibleRepos = $repoService->fetchForUser($user);
            $accessibleFullNames = array_map(
                static fn (array $r): string => (string) ($r['full_name'] ?? ''),
                array_filter($accessibleRepos, static fn (mixed $r): bool => \is_array($r))
            );
            $accessibleSet = array_flip($accessibleFullNames);

            foreach ($incoming as $item) {
                if (!isset($accessibleSet[$item['repoFullName']])) {
                    return $this->json(
                        ['error' => \sprintf('Repository "%s" is not accessible to this user', $item['repoFullName'])],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            }
        }

        // Replace membership: delete existing rows via DBAL, then insert new ones
        $this->entryRepo->deleteByWorkspace($workspace);

        foreach ($incoming as $item) {
            $entry = new WorkspaceRepository_();
            $entry->setWorkspace($workspace);
            $entry->setRepoFullName($item['repoFullName']);
            $entry->setRepoId($item['repoId']);
            $entry->setInstallationId($item['installationId']);
            $this->em->persist($entry);
        }

        $this->em->flush();

        // Reload workspace to get fresh collection
        $this->em->refresh($workspace);

        return $this->json(['data' => $this->serialize($workspace), 'status' => 'ok']);
    }

    private function serialize(Workspace $workspace): array
    {
        $repos = [];
        foreach ($workspace->getRepositories() as $entry) {
            $repos[] = [
                'id' => $entry->getId(),
                'repoFullName' => $entry->getRepoFullName(),
                'repoId' => $entry->getRepoId(),
                'installationId' => $entry->getInstallationId(),
                'createdAt' => $entry->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return [
            'id' => $workspace->getId(),
            'name' => $workspace->getName(),
            'description' => $workspace->getDescription(),
            'repositories' => $repos,
            'createdAt' => $workspace->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $workspace->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
