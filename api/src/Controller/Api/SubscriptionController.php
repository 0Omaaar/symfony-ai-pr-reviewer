<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\RepoSubscription;
use App\Entity\User;
use App\Repository\RepoSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/subscriptions')]
final class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly RepoSubscriptionRepository $subscriptionRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'subscription_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $subscriptions = $this->subscriptionRepo->findActiveByUser($user);

        return $this->json([
            'data' => array_map([$this, 'serialize'], $subscriptions),
            'status' => 'ok',
            'count' => $this->subscriptionRepo->countActiveByUser($user),
        ]);
    }

    #[Route('', name: 'subscription_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = $request->toArray();
        $repoFullName = trim((string) ($payload['repoFullName'] ?? ''));
        $repoId = trim((string) ($payload['repoId'] ?? ''));
        $installationId = trim((string) ($payload['installationId'] ?? ''));
        $branch = trim((string) ($payload['branch'] ?? ''));

        if ($repoFullName === '' || $repoId === '' || $installationId === '' || $branch === '') {
            return $this->json([
                'data' => null,
                'status' => 'error',
                'error' => 'Missing required fields: repoFullName, repoId, installationId, branch',
            ], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->subscriptionRepo->findOneByUserRepoBranch($user, $repoFullName, $branch);

        if ($existing !== null) {
            if ($existing->isActive()) {
                return $this->json([
                    'data' => $this->serialize($existing),
                    'status' => 'already_active',
                ]);
            }

            // Reactivate
            $existing->activate();
            $existing->setInstallationId($installationId);
            $existing->setRepoId($repoId);
            $this->em->flush();

            return $this->json([
                'data' => $this->serialize($existing),
                'status' => 'reactivated',
            ]);
        }

        $subscription = new RepoSubscription();
        $subscription->setAppUser($user);
        $subscription->setRepoFullName($repoFullName);
        $subscription->setRepoId($repoId);
        $subscription->setInstallationId($installationId);
        $subscription->setBranch($branch);

        $this->em->persist($subscription);
        $this->em->flush();

        return $this->json([
            'data' => $this->serialize($subscription),
            'status' => 'created',
        ], Response::HTTP_CREATED);
    }

    #[Route('/{repoFullName}/{branch}', name: 'subscription_delete', methods: ['DELETE'], requirements: ['repoFullName' => '.+/[^/]+', 'branch' => '.+'])]
    public function delete(string $repoFullName, string $branch): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $subscription = $this->subscriptionRepo->findOneByUserRepoBranch($user, $repoFullName, $branch);

        if ($subscription === null || !$subscription->isActive()) {
            return $this->json([
                'data' => null,
                'status' => 'not_found',
            ], Response::HTTP_NOT_FOUND);
        }

        $subscription->deactivate();
        $this->em->flush();

        return $this->json([
            'data' => $this->serialize($subscription),
            'status' => 'deactivated',
        ]);
    }

    #[Route('/repo/{repoFullName}', name: 'subscription_repo', methods: ['GET'], requirements: ['repoFullName' => '.+/[^/]+'])]
    public function repo(string $repoFullName): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $subscriptions = $this->subscriptionRepo->findByUserAndRepo($user, $repoFullName);

        return $this->json([
            'data' => array_map([$this, 'serialize'], $subscriptions),
            'status' => 'ok',
        ]);
    }

    private function serialize(RepoSubscription $sub): array
    {
        return [
            'id' => $sub->getId(),
            'repoFullName' => $sub->getRepoFullName(),
            'repoId' => $sub->getRepoId(),
            'installationId' => $sub->getInstallationId(),
            'branch' => $sub->getBranch(),
            'isActive' => $sub->isActive(),
            'activatedAt' => $sub->getActivatedAt()?->format(\DateTimeInterface::ATOM),
            'deactivatedAt' => $sub->getDeactivatedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $sub->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
