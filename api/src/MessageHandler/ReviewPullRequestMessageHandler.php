<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\ReviewPullRequestMessage;
use App\Repository\RepoSubscriptionRepository;
use App\Service\Github\GithubInstallationRepositoriesService;
use App\Service\PullRequest\PullRequestSnapshotService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
final readonly class ReviewPullRequestMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private GithubInstallationRepositoriesService $repositoriesService,
        private RepoSubscriptionRepository $subscriptionRepo,
        private PullRequestSnapshotService $snapshotService,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
        private Environment $twig,
    ) {
    }

    public function __invoke(ReviewPullRequestMessage $message): void
    {
        try {
            $recipients = $this->repositoriesService->processPullRequestWebhookEvent(
                $message->installationId,
                $message->repoId,
                $message->prNumber,
                $message->action,
                $message->deliveryId,
                $message->headSha
            );

            // Filter recipients by active repo+branch subscriptions (if baseBranch is available)
            if ($message->baseBranch !== '') {
                $subscribedUserIds = [];
                $activeSubscriptions = $this->subscriptionRepo->findActiveByRepoAndBranch(
                    $message->repoFullName,
                    $message->baseBranch
                );
                foreach ($activeSubscriptions as $sub) {
                    $userId = $sub->getAppUser()?->getId();
                    if ($userId !== null) {
                        $subscribedUserIds[$userId] = true;
                    }
                }

                if (empty($subscribedUserIds)) {
                    $this->logger->debug('No active subscriptions for repo+branch, skipping notifications', [
                        'delivery_id' => $message->deliveryId,
                        'repository' => $message->repoFullName,
                        'base_branch' => $message->baseBranch,
                    ]);

                    return;
                }

                $recipients = array_filter($recipients, static function (array $r) use ($subscribedUserIds): bool {
                    return isset($subscribedUserIds[$r['user_id'] ?? null]);
                });
                $recipients = array_values($recipients);
            }

            $sentCount = $this->sendPullRequestAlertEmails($message, $recipients);

            // Refresh PR snapshots for all affected users
            try {
                $refreshedUserIds = [];
                foreach ($recipients as $r) {
                    $uid = $r['user_id'] ?? null;
                    if ($uid === null || isset($refreshedUserIds[$uid])) {
                        continue;
                    }
                    $refreshedUserIds[$uid] = true;
                    $userEntity = $this->em->find(User::class, $uid);
                    if ($userEntity instanceof User) {
                        $this->snapshotService->refreshForRepo($userEntity, $message->repoFullName, $message->installationId);
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Snapshot refresh failed after webhook processing', [
                    'delivery_id' => $message->deliveryId,
                    'repo' => $message->repoFullName,
                    'error' => $e->getMessage(),
                ]);
            }

            // If this event triggers an AI review, mark snapshot as processing
            if (\in_array($message->action, ['opened', 'synchronize', 'ready_for_review'], true)) {
                try {
                    $this->snapshotService->markAiReviewProcessing($message->repoFullName, $message->prNumber);
                } catch (\Throwable $e) {
                    $this->logger->debug('Failed to mark AI review processing', ['error' => $e->getMessage()]);
                }
            }

            $this->logger->info('Worker processed pull request webhook message', [
                'delivery_id' => $message->deliveryId,
                'installation_id' => $message->installationId,
                'repository_id' => $message->repoId,
                'repository' => $message->repoFullName,
                'action' => $message->action,
                'pr_number' => $message->prNumber,
                'head_sha' => $message->headSha,
                'base_branch' => $message->baseBranch,
                'affected_users' => \count($recipients),
                'emails_sent' => $sentCount,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Worker failed while processing pull request webhook message', [
                'delivery_id' => $message->deliveryId,
                'installation_id' => $message->installationId,
                'repository_id' => $message->repoId,
                'repository' => $message->repoFullName,
                'action' => $message->action,
                'pr_number' => $message->prNumber,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function sendPullRequestAlertEmails(ReviewPullRequestMessage $message, array $recipients): int
    {
        $fromEmail = trim((string) $this->params->get('pr_alert.from_email'));
        $fromName = trim((string) $this->params->get('pr_alert.from_name'));
        $replyTo = trim((string) $this->params->get('pr_alert.reply_to'));
        $frontUrl = rtrim(trim((string) $this->params->get('pr_alert.front_url')), '/');

        if ($fromEmail === '') {
            $this->logger->warning('PR alert email sender is not configured. Skipping email notifications.', [
                'delivery_id' => $message->deliveryId,
            ]);

            return 0;
        }

        $actionLabel = $this->formatAction($message->action);
        $subject = \sprintf('[autoPMR] PR #%d %s in %s', $message->prNumber, $actionLabel, $message->repoFullName);
        $repoUrl = $frontUrl !== '' ? \sprintf('%s/repos/%d', $frontUrl, $message->repoId) : null;

        $sentCount = 0;
        foreach ($recipients as $recipient) {
            $toEmail = isset($recipient['email']) && \is_string($recipient['email']) ? trim($recipient['email']) : '';
            if ($toEmail === '') {
                continue;
            }

            if (isset($recipient['email_notifications_enabled']) && $recipient['email_notifications_enabled'] === false) {
                continue;
            }

            $prefs = isset($recipient['notification_preferences']) && \is_array($recipient['notification_preferences'])
                ? $recipient['notification_preferences']
                : [];
            if (!$this->shouldSendForPreferences($prefs, $message->action, $message->repoFullName)) {
                continue;
            }

            $githubUsername = isset($recipient['github_username']) && \is_string($recipient['github_username']) && $recipient['github_username'] !== ''
                ? $recipient['github_username']
                : 'there';

            $unsubscribeToken = isset($recipient['unsubscribe_token']) && \is_string($recipient['unsubscribe_token']) ? $recipient['unsubscribe_token'] : null;
            $unsubscribeUrl = $unsubscribeToken !== null && $frontUrl !== ''
                ? \sprintf('%s/unsubscribe/%s', $frontUrl, $unsubscribeToken)
                : null;

            $htmlBody = $this->twig->render('email/pr_notification.html.twig', [
                'pr_number' => $message->prNumber,
                'action_label' => $actionLabel,
                'repo_full_name' => $message->repoFullName,
                'github_username' => $githubUsername,
                'head_sha' => $message->headSha,
                'delivery_id' => $message->deliveryId,
                'repo_url' => $repoUrl,
                'unsubscribe_url' => $unsubscribeUrl,
            ]);

            $textBody = $this->buildTextBody($message, $githubUsername, $repoUrl);

            $email = (new Email())
                ->from(new Address($fromEmail, $fromName !== '' ? $fromName : 'autoPMR'))
                ->to($toEmail)
                ->subject($subject)
                ->text($textBody)
                ->html($htmlBody);

            if ($replyTo !== '') {
                $email->replyTo($replyTo);
            }

            try {
                $this->mailer->send($email);
                $sentCount++;
            } catch (TransportExceptionInterface $exception) {
                $this->logger->error('Failed to send PR alert email', [
                    'delivery_id' => $message->deliveryId,
                    'recipient' => $toEmail,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sentCount;
    }

    private function shouldSendForPreferences(array $prefs, string $action, string $repoFullName): bool
    {
        $events = isset($prefs['events']) && \is_array($prefs['events']) ? $prefs['events'] : [];
        $eventKey = $this->mapActionToEventKey($action);
        if ($eventKey !== null && \array_key_exists($eventKey, $events) && $events[$eventKey] === false) {
            return false;
        }

        $repos = isset($prefs['repos']) && \is_array($prefs['repos']) ? $prefs['repos'] : [];
        $mode = isset($repos['mode']) && \is_string($repos['mode']) ? $repos['mode'] : 'all';
        if ($mode === 'specific') {
            $allowed = isset($repos['allowed']) && \is_array($repos['allowed']) ? $repos['allowed'] : [];
            if (!\in_array($repoFullName, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function mapActionToEventKey(string $action): ?string
    {
        return match ($action) {
            'opened', 'reopened' => 'opened',
            'closed' => 'closed',
            'synchronize' => 'synchronize',
            'ready_for_review' => 'ready_for_review',
            'converted_to_draft' => 'converted_to_draft',
            default => null,
        };
    }

    private function formatAction(string $action): string
    {
        return match ($action) {
            'opened' => 'opened',
            'reopened' => 'reopened',
            'closed' => 'closed',
            'synchronize' => 'updated',
            'ready_for_review' => 'ready for review',
            'converted_to_draft' => 'converted to draft',
            default => 'updated',
        };
    }

    private function buildTextBody(ReviewPullRequestMessage $message, string $githubUsername, ?string $repoUrl): string
    {
        $action = $this->formatAction($message->action);
        $lines = [
            "Hello {$githubUsername},",
            '',
            \sprintf('A pull request event was received: PR #%d %s in %s.', $message->prNumber, $action, $message->repoFullName),
            "Head SHA: {$message->headSha}",
            "Delivery ID: {$message->deliveryId}",
        ];

        if ($repoUrl !== null) {
            $lines[] = "Open repository: {$repoUrl}";
        }

        $lines[] = '';
        $lines[] = 'autoPMR';

        return \implode("\n", $lines);
    }
}
