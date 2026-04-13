<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/onboarding')]
final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'onboarding_get', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json([
            'data' => $this->buildOnboardingResponse($user),
        ]);
    }

    #[Route('/dismiss', name: 'onboarding_dismiss', methods: ['POST'])]
    public function dismiss(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $state = $user->getOnboardingState();
        $state['dismissedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $user->setOnboardingState($state);
        $this->em->flush();

        return $this->json([
            'data' => $this->buildOnboardingResponse($user),
            'status' => 'dismissed',
        ]);
    }

    #[Route('/complete', name: 'onboarding_complete_step', methods: ['POST'])]
    public function completeStep(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->toArray();
        $step = trim((string) ($payload['step'] ?? ''));

        $validSteps = ['github_connected', 'app_installed', 'branch_activated', 'preferences_set', 'first_review_received'];
        if (!\in_array($step, $validSteps, true)) {
            return $this->json([
                'error' => 'Invalid step. Must be one of: ' . implode(', ', $validSteps),
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->completeOnboardingStep($step);
        $this->em->flush();

        return $this->json([
            'data' => $this->buildOnboardingResponse($user),
            'status' => 'step_completed',
        ]);
    }

    private function buildOnboardingResponse(User $user): array
    {
        $state = $user->getOnboardingState();

        $allSteps = [
            ['id' => 'github_connected', 'label' => 'Connect your GitHub account'],
            ['id' => 'app_installed', 'label' => 'Install the GitHub App'],
            ['id' => 'branch_activated', 'label' => 'Activate a branch to monitor'],
            ['id' => 'preferences_set', 'label' => 'Set your notification preferences'],
            ['id' => 'first_review_received', 'label' => 'Get your first AI review'],
        ];

        $completedSteps = $state['completedSteps'] ?? [];
        $steps = [];
        foreach ($allSteps as $step) {
            $steps[] = [
                'id' => $step['id'],
                'label' => $step['label'],
                'isComplete' => \in_array($step['id'], $completedSteps, true),
            ];
        }

        return [
            'steps' => $steps,
            'completedCount' => \count($completedSteps),
            'totalSteps' => 5,
            'isComplete' => $state['completedAt'] !== null,
            'isDismissed' => $state['dismissedAt'] !== null,
            'completedAt' => $state['completedAt'],
            'dismissedAt' => $state['dismissedAt'],
            'firstReviewReceivedAt' => $state['firstReviewReceivedAt'],
        ];
    }
}
