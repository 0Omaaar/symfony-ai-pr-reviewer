<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Message\ProcessPullRequestMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhooks')]
final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/github', name: 'webhook_github', methods: ['POST'])]
    public function github(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $payload = $request->toArray();

        $this->bus->dispatch(new ProcessPullRequestMessage(
            provider: 'github',
            prId: (string) ($payload['pr_id'] ?? ''),
            title: (string) ($payload['title'] ?? ''),
            description: (string) ($payload['description'] ?? ''),
            repoUrl: (string) ($payload['repo_url'] ?? ''),
            branch: (string) ($payload['branch'] ?? ''),
            authorToken: (string) ($payload['author_token'] ?? ''),
        ));

        return $this->json(['ok' => true, 'dispatched' => true], Response::HTTP_ACCEPTED);
    }

    #[Route('/gitlab', name: 'webhook_gitlab', methods: ['POST'])]
    public function gitlab(Request $request): JsonResponse
    {
        $this->validateToken($request);

        $payload = $request->toArray();

        $this->bus->dispatch(new ProcessPullRequestMessage(
            provider: 'gitlab',
            prId: (string) ($payload['pr_id'] ?? ''),
            title: (string) ($payload['title'] ?? ''),
            description: (string) ($payload['description'] ?? ''),
            repoUrl: (string) ($payload['repo_url'] ?? ''),
            branch: (string) ($payload['branch'] ?? ''),
            authorToken: (string) ($payload['author_token'] ?? ''),
        ));

        return $this->json(['ok' => true, 'dispatched' => true], Response::HTTP_ACCEPTED);
    }

    private function validateToken(Request $request): void
    {
        $provided = (string) $request->headers->get('X-Internal-Token', '');
        $expected = (string) $this->getParameter('n8n.internal_token');

        if (!hash_equals($expected, $provided)) {
            throw $this->createAccessDeniedException('Invalid internal token.');
        }
    }
}
