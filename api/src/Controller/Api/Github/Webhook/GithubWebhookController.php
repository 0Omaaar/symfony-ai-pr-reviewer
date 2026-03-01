<?php

namespace App\Controller\Api\Github\Webhook;

use App\Message\ReviewPullRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class GithubWebhookController extends AbstractController
{
    #[Route('/webhooks/github', name: 'app_webhooks_github', methods: ['POST'])]
    public function handle(
        Request $request,
        ParameterBagInterface $params,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ): Response {
        $rawBody = $request->getContent();
        $githubEvent = (string) $request->headers->get('X-GitHub-Event', '');
        $signature = (string) $request->headers->get('X-Hub-Signature-256', '');
        $deliveryId = (string) $request->headers->get('X-GitHub-Delivery', '');
        $webhookSecret = (string) $params->get('github.webhook_secret');

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret);
        if ($signature === '' || $webhookSecret === '' || !hash_equals($expectedSignature, $signature)) {
            return $this->json(['ok' => false, 'error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Invalid JSON payload'], Response::HTTP_BAD_REQUEST);
        }

        if ($githubEvent !== 'pull_request') {
            return $this->json([
                'ok' => true,
                'ignored' => true,
                'reason' => 'Unsupported event',
                'event' => $githubEvent,
            ]);
        }

        $installationId = $payload['installation']['id'] ?? null;
        $repositoryFullName = $payload['repository']['full_name'] ?? null;
        $pullRequestNumber = $payload['pull_request']['number'] ?? null;
        $headSha = $payload['pull_request']['head']['sha'] ?? null;
        $action = (string) ($payload['action'] ?? '');

        if (!is_int($installationId) || !is_string($repositoryFullName) || !is_int($pullRequestNumber) || !is_string($headSha) || $headSha === '') {
            return $this->json(['ok' => false, 'error' => 'Missing required pull_request fields'], Response::HTTP_BAD_REQUEST);
        }

        $logger->info('GitHub webhook parsed', [
            'delivery_id' => $deliveryId,
            'event' => $githubEvent,
            'action' => $action,
            'installation_id' => $installationId,
            'repository' => $repositoryFullName,
            'pr_number' => $pullRequestNumber,
            'head_sha' => $headSha,
        ]);

        $bus->dispatch(new ReviewPullRequestMessage(
            $installationId,
            $repositoryFullName,
            $pullRequestNumber,
            $headSha,
            $deliveryId
        ));

        return $this->json([
            'ok' => true,
            'event' => $githubEvent,
            'delivery' => $deliveryId,
            'dispatched' => true,
        ]);
    }
}
