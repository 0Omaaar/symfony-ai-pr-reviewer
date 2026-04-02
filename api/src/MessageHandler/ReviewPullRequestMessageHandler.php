<?php

namespace App\MessageHandler;

use App\Message\ReviewPullRequestMessage;
use App\Service\Github\GithubInstallationRepositoriesService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final readonly class ReviewPullRequestMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private GithubInstallationRepositoriesService $repositoriesService,
        private MailerInterface $mailer,
        private ParameterBagInterface $params
    )
    {
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
            $sentCount = $this->sendPullRequestAlertEmails($message, $recipients);

            $this->logger->info('Worker processed pull request webhook message', [
                'delivery_id' => $message->deliveryId,
                'installation_id' => $message->installationId,
                'repository_id' => $message->repoId,
                'repository' => $message->repoFullName,
                'action' => $message->action,
                'pr_number' => $message->prNumber,
                'head_sha' => $message->headSha,
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

            $githubUsername = isset($recipient['github_username']) && \is_string($recipient['github_username']) && $recipient['github_username'] !== ''
                ? $recipient['github_username']
                : 'there';

            $unsubscribeToken = isset($recipient['unsubscribe_token']) && \is_string($recipient['unsubscribe_token']) ? $recipient['unsubscribe_token'] : null;
            $htmlBody = $this->buildHtmlBody($message, $githubUsername, $repoUrl, $unsubscribeToken);
            $textBody = $this->buildTextBody($message, $githubUsername, $repoUrl);
            $email = new Email();
            $email->from(new Address($fromEmail, $fromName !== '' ? $fromName : 'autoPMR'))
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

    private function buildHtmlBody(ReviewPullRequestMessage $message, string $githubUsername, ?string $repoUrl, ?string $unsubscribeToken): string
    {
        $actionLabel = $this->formatAction($message->action);
        $safeUsername = $this->escapeHtml($githubUsername);
        $safeRepo = $this->escapeHtml($message->repoFullName);
        $safeAction = $this->escapeHtml($actionLabel);
        $safeSha = $this->escapeHtml($message->headSha);
        $safeDeliveryId = $this->escapeHtml($message->deliveryId);
        $safeRepoUrl = $repoUrl !== null ? $this->escapeHtml($repoUrl) : null;
        $safeRepoUrlAttr = $repoUrl !== null ? htmlspecialchars($repoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;

        $repoLinkSection = '';
        if ($safeRepoUrl !== null && $safeRepoUrlAttr !== null) {
            $repoLinkSection = "<a href=\"{$safeRepoUrlAttr}\" style=\"display:inline-block;margin-top:18px;padding:12px 18px;background:linear-gradient(135deg,#1a73e8,#00b3ff);color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;font-size:14px;\">Open Repository</a><p style=\"margin:14px 0 0;color:#7f8ea3;font-size:12px;line-height:1.5;\">If the button does not work, copy this URL:<br><span style=\"word-break:break-all;color:#a8b6cb;\">{$safeRepoUrl}</span></p>";
        }

        $unsubscribeSection = '';
        if ($unsubscribeToken !== null) {
            $frontUrl = \rtrim(\trim((string) $this->params->get('pr_alert.front_url')), '/');
            $unsubscribeUrl = $frontUrl !== ''
                ? \htmlspecialchars("{$frontUrl}/unsubscribe/{$unsubscribeToken}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : \htmlspecialchars("/unsubscribe/{$unsubscribeToken}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $unsubscribeSection = "<p style=\"margin:12px 0 0;text-align:center;\"><a href=\"{$unsubscribeUrl}\" style=\"color:#8a97aa;font-size:11px;text-decoration:underline;\">Unsubscribe from PR alerts</a></p>";
        }

        return \sprintf(
            '<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#f3f6fb;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#13233a;">
  <table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e6ecf5;box-shadow:0 10px 30px rgba(14,34,61,0.08);">
          <tr>
            <td style="background:linear-gradient(135deg,#0f172a,#1f3d73 55%%,#0ea5e9);padding:28px 28px 24px;">
              <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#9cc9ff;font-weight:700;">autoPMR Alert</p>
              <h1 style="margin:0;font-size:24px;line-height:1.3;color:#ffffff;font-weight:700;">PR #%d %s</h1>
              <p style="margin:10px 0 0;color:#d8e7ff;font-size:14px;">Repository: <strong style="color:#ffffff;">%s</strong></p>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#233a57;">Hello %s,</p>
              <p style="margin:0 0 22px;font-size:15px;line-height:1.7;color:#3a4d68;">A pull request event was received and processed by autoPMR. Here are the details:</p>

              <table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 8px;">
                <tr>
                  <td style="width:145px;padding:10px 12px;background:#f7faff;border-radius:8px;color:#5f6f85;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">Action</td>
                  <td style="padding:10px 12px;background:#f7faff;border-radius:8px;color:#13233a;font-size:14px;font-weight:600;">%s</td>
                </tr>
                <tr>
                  <td style="padding:10px 12px;background:#f7faff;border-radius:8px;color:#5f6f85;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">Head SHA</td>
                  <td style="padding:10px 12px;background:#f7faff;border-radius:8px;color:#13233a;font-size:13px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace;">%s</td>
                </tr>
                <tr>
                  <td style="padding:10px 12px;background:#f7faff;border-radius:8px;color:#5f6f85;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">Delivery ID</td>
                  <td style="padding:10px 12px;background:#f7faff;border-radius:8px;color:#13233a;font-size:13px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace;">%s</td>
                </tr>
              </table>

              %s

              <p style="margin:24px 0 0;color:#7b8ba1;font-size:12px;line-height:1.6;">You are receiving this because your account is linked to the GitHub installation for this repository.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 28px;background:#f8fbff;border-top:1px solid #e6ecf5;color:#8a97aa;font-size:12px;">
              autoPMR • Pull Request Monitoring
              %s
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>',
            $message->prNumber,
            $safeAction,
            $safeRepo,
            $safeUsername,
            $safeAction,
            $safeSha,
            $safeDeliveryId,
            $repoLinkSection,
            $unsubscribeSection
        );
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
