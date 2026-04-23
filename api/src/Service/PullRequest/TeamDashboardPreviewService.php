<?php

declare(strict_types=1);

namespace App\Service\PullRequest;

use App\Entity\PullRequestSnapshot;
use App\Service\Github\GithubApiClient;
use App\Service\Github\GithubAppJwtService;

final class TeamDashboardPreviewService
{
    public function __construct(
        private readonly GithubApiClient $apiClient,
        private readonly GithubAppJwtService $jwtService,
    ) {
    }

    public function buildPreview(PullRequestSnapshot $snapshot): array
    {
        $installationId = (int) ($snapshot->getInstallationId() ?? 0);
        $repoFullName = $snapshot->getRepoFullName();
        $prNumber = $snapshot->getPrNumber();

        if ($installationId <= 0 || !\is_string($repoFullName) || $repoFullName === '' || !\is_int($prNumber)) {
            return [
                'commitCount' => 0,
                'commits' => [],
                'checkRuns' => [],
                'checkSummary' => $this->emptyCheckSummary(),
            ];
        }

        $installationToken = $this->getInstallationToken($installationId);
        if ($installationToken === null) {
            return [
                'commitCount' => 0,
                'commits' => [],
                'checkRuns' => [],
                'checkSummary' => $this->emptyCheckSummary(),
            ];
        }

        $pullData = $this->apiClient->fetchPullRequest($installationToken, $repoFullName, $prNumber);
        $commits = $this->transformCommits(
            $this->apiClient->fetchPullRequestCommits($installationToken, $repoFullName, $prNumber)
        );

        $headSha = \is_array($pullData['head'] ?? null) ? (string) ($pullData['head']['sha'] ?? '') : '';
        $checkRuns = $headSha !== ''
            ? $this->transformCheckRuns($this->apiClient->fetchCombinedStatus($installationToken, $repoFullName, $headSha))
            : [];

        return [
            'commitCount' => \is_int($pullData['commits'] ?? null) ? $pullData['commits'] : \count($commits),
            'commits' => \array_slice($commits, 0, 8),
            'checkRuns' => $checkRuns,
            'checkSummary' => $this->summarizeChecks($checkRuns),
        ];
    }

    private function getInstallationToken(int $installationId): ?string
    {
        $jwt = $this->jwtService->createJwt();

        return $this->apiClient->createInstallationToken($jwt, $installationId);
    }

    private function transformCommits(array $commitItems): array
    {
        $commits = [];

        foreach ($commitItems as $commitItem) {
            if (!\is_array($commitItem)) {
                continue;
            }

            $sha = \is_string($commitItem['sha'] ?? null) ? $commitItem['sha'] : '';
            $commit = \is_array($commitItem['commit'] ?? null) ? $commitItem['commit'] : [];
            $author = \is_array($commitItem['author'] ?? null) ? $commitItem['author'] : [];
            $commitAuthor = \is_array($commit['author'] ?? null) ? $commit['author'] : [];
            $message = \is_string($commit['message'] ?? null) ? $commit['message'] : '';
            [$headline, $body] = $this->splitCommitMessage($message);

            $commits[] = [
                'sha' => $sha,
                'shortSha' => $sha !== '' ? \substr($sha, 0, 7) : '',
                'headline' => $headline,
                'body' => $body,
                'authorLogin' => \is_string($author['login'] ?? null) ? $author['login'] : null,
                'authorName' => \is_string($commitAuthor['name'] ?? null) ? $commitAuthor['name'] : null,
                'authorAvatarUrl' => \is_string($author['avatar_url'] ?? null) ? $author['avatar_url'] : null,
                'committedAt' => \is_string($commitAuthor['date'] ?? null) ? $commitAuthor['date'] : null,
                'htmlUrl' => \is_string($commitItem['html_url'] ?? null) ? $commitItem['html_url'] : null,
            ];
        }

        return $commits;
    }

    private function transformCheckRuns(array $checkData): array
    {
        $checkRuns = [];

        foreach ($checkData['check_runs'] ?? [] as $checkRun) {
            if (!\is_array($checkRun)) {
                continue;
            }

            $app = \is_array($checkRun['app'] ?? null) ? $checkRun['app'] : [];
            $checkRuns[] = [
                'name' => \is_string($checkRun['name'] ?? null) ? $checkRun['name'] : 'Unnamed check',
                'status' => \is_string($checkRun['status'] ?? null) ? $checkRun['status'] : 'queued',
                'conclusion' => \is_string($checkRun['conclusion'] ?? null) ? $checkRun['conclusion'] : null,
                'detailsUrl' => \is_string($checkRun['details_url'] ?? null) ? $checkRun['details_url'] : null,
                'appName' => \is_string($app['name'] ?? null) ? $app['name'] : null,
                'startedAt' => \is_string($checkRun['started_at'] ?? null) ? $checkRun['started_at'] : null,
                'completedAt' => \is_string($checkRun['completed_at'] ?? null) ? $checkRun['completed_at'] : null,
            ];
        }

        return $checkRuns;
    }

    private function summarizeChecks(array $checkRuns): array
    {
        $summary = $this->emptyCheckSummary();

        foreach ($checkRuns as $checkRun) {
            if (!\is_array($checkRun)) {
                continue;
            }

            $summary['total']++;

            $status = (string) ($checkRun['status'] ?? '');
            $conclusion = (string) ($checkRun['conclusion'] ?? '');

            if ($status !== 'completed') {
                $summary['pending']++;
                continue;
            }

            if (\in_array($conclusion, ['success', 'neutral', 'skipped'], true)) {
                $summary['passing']++;
                continue;
            }

            if (\in_array($conclusion, ['cancelled', 'timed_out', 'action_required', 'failure', 'startup_failure'], true)) {
                $summary['failing']++;
                continue;
            }

            $summary['pending']++;
        }

        return $summary;
    }

    private function emptyCheckSummary(): array
    {
        return [
            'total' => 0,
            'passing' => 0,
            'pending' => 0,
            'failing' => 0,
        ];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitCommitMessage(string $message): array
    {
        if ($message === '') {
            return ['No commit message', null];
        }

        $parts = \preg_split("/\r?\n\r?\n/", $message, 2);
        $headline = \trim((string) ($parts[0] ?? ''));
        $body = isset($parts[1]) ? \trim((string) $parts[1]) : null;

        return [$headline !== '' ? $headline : 'No commit message', $body !== '' ? $body : null];
    }
}
