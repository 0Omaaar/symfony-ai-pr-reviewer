<?php

namespace App\Controller\Api\Github\Webhook;

use App\Entity\ProcessedWebhookDelivery;
use App\Message\CleanupGithubInstallationMessage;
use App\Message\ReviewPullRequestMessage;
use App\Repository\ProcessedWebhookDeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class GithubWebhookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProcessedWebhookDeliveryRepository $deliveryRepo,
        #[Autowire(service: 'limiter.github_webhook')] private readonly RateLimiterFactory $githubWebhookLimiter,
    ) {}

    #[Route('/webhooks/github', name: 'app_webhooks_github', methods: ['POST'])]
    public function handle(
        Request $request,
        ParameterBagInterface $params,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ): Response {
        $limiter = $this->githubWebhookLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['ok' => false, 'error' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

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

        if ($githubEvent === 'installation') {
            $action = (string) ($payload['action'] ?? '');
            $installationId = $payload['installation']['id'] ?? null;

            if (in_array($action, ['deleted', 'suspended'], true) && is_int($installationId)) {
                if ($deliveryId !== '') {
                    $alreadyProcessed = $this->deliveryRepo->existsByDeliveryId($deliveryId);

                    if ($alreadyProcessed) {
                        return $this->json(['ok' => true, 'event' => $githubEvent, 'delivery' => $deliveryId, 'dispatched' => false, 'reason' => 'already_processed']);
                    }

                    $this->em->persist(new ProcessedWebhookDelivery($deliveryId));
                    $this->em->flush();
                }

                $bus->dispatch(new CleanupGithubInstallationMessage($installationId, $action, $deliveryId));

                return $this->json(['ok' => true, 'event' => $githubEvent, 'delivery' => $deliveryId, 'dispatched' => true]);
            }

            return $this->json(['ok' => true, 'ignored' => true, 'reason' => 'Unhandled installation action', 'event' => $githubEvent]);
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
        $repositoryId = $payload['repository']['id'] ?? null;
        $repositoryFullName = $payload['repository']['full_name'] ?? null;
        $pullRequestNumber = $payload['pull_request']['number'] ?? null;
        $headSha = $payload['pull_request']['head']['sha'] ?? null;
        $action = (string) ($payload['action'] ?? '');

        if (!is_int($installationId) || !is_int($repositoryId) || !is_string($repositoryFullName) || !is_int($pullRequestNumber) || !is_string($headSha) || $headSha === '') {
            return $this->json(['ok' => false, 'error' => 'Missing required pull_request fields'], Response::HTTP_BAD_REQUEST);
        }

        if ($deliveryId !== '') {
            $alreadyProcessed = $this->deliveryRepo->existsByDeliveryId($deliveryId);

            if ($alreadyProcessed) {
                $logger->info('GitHub webhook delivery already processed, skipping', ['delivery_id' => $deliveryId]);

                return $this->json(['ok' => true, 'event' => $githubEvent, 'delivery' => $deliveryId, 'dispatched' => false, 'reason' => 'already_processed']);
            }

            $this->em->persist(new ProcessedWebhookDelivery($deliveryId));
            $this->em->flush();
        }

        $logger->info('GitHub webhook parsed', [
            'delivery_id' => $deliveryId,
            'event' => $githubEvent,
            'action' => $action,
            'installation_id' => $installationId,
            'repository_id' => $repositoryId,
            'repository' => $repositoryFullName,
            'pr_number' => $pullRequestNumber,
            'head_sha' => $headSha,
        ]);

        $bus->dispatch(new ReviewPullRequestMessage(
            $installationId,
            $repositoryId,
            $repositoryFullName,
            $pullRequestNumber,
            $action,
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
