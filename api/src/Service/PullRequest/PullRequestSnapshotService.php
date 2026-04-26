<?php

declare(strict_types=1);

namespace App\Service\PullRequest;

use App\Entity\PullRequestSnapshot;
use App\Entity\User;
use App\Repository\PullRequestSnapshotRepository;
use App\Repository\RepoSubscriptionRepository;
use App\Service\CacheKeys;
use App\Service\Github\GithubApiClient;
use App\Service\Github\GithubAppJwtService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class PullRequestSnapshotService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PullRequestSnapshotRepository $snapshotRepo,
        private readonly RepoSubscriptionRepository $subscriptionRepo,
        private readonly GithubApiClient $apiClient,
        private readonly GithubAppJwtService $jwtService,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function refreshForUser(User $user): void
    {
        $subscriptions = $this->subscriptionRepo->findActiveByUser($user);
        $repoMap = [];
        foreach ($subscriptions as $sub) {
            $repo = $sub->getRepoFullName();
            if ($repo !== null && !isset($repoMap[$repo])) {
                $repoMap[$repo] = $sub->getInstallationId();
            }
        }

        foreach ($repoMap as $repoFullName => $installationId) {
            try {
                $this->refreshForRepo($user, $repoFullName, (int) $installationId);
            } catch (\Throwable $e) {
                $this->logger->error('Snapshot refresh failed for repo', [
                    'user_id' => $user->getId(),
                    'repo' => $repoFullName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->snapshotRepo->markStale();

        // Bust stats cache
        if ($user->getId() !== null) {
            $this->cache->delete(CacheKeys::teamDashboardStats($user->getId()));
        }
    }

    public function refreshForRepo(User $user, string $repoFullName, ?int $installationIdHint = null): void
    {
        $installationId = $installationIdHint;
        if ($installationId === null) {
            $sub = $this->subscriptionRepo->findActiveByUser($user);
            foreach ($sub as $s) {
                if ($s->getRepoFullName() === $repoFullName) {
                    $installationId = (int) $s->getInstallationId();
                    break;
                }
            }
        }

        if ($installationId === null) {
            return;
        }

        $installationToken = $this->getInstallationToken($installationId);
        if ($installationToken === null) {
            return;
        }

        $userId = $user->getId();

        // Fetch open PRs with caching (120s TTL, busted by webhook)
        $cacheKey = CacheKeys::prSnapshotRepoList((int) $userId, $repoFullName);
        $openPrs = $this->cache->get($cacheKey, function (ItemInterface $item) use ($installationToken, $repoFullName): array {
            $item->expiresAfter(120);

            $page = 1;
            $allPrs = [];
            do {
                $prs = $this->apiClient->fetchPullRequests($installationToken, $repoFullName, $page);
                foreach ($prs as $pr) {
                    if (\is_array($pr) && ($pr['state'] ?? '') === 'open') {
                        $allPrs[] = $pr;
                    }
                }
                $hasMore = \count($prs) === 100;
                $page++;
            } while ($hasMore);

            return $allPrs;
        });

        $openPrNumbers = [];
        foreach ($openPrs as $prData) {
            if (!\is_array($prData) || !isset($prData['number'])) {
                continue;
            }

            $openPrNumbers[] = (int) $prData['number'];

            try {
                $this->upsertFromGithub($user, $prData, $repoFullName, (string) $installationId, $installationToken);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to upsert PR snapshot', [
                    'repo' => $repoFullName,
                    'pr' => $prData['number'] ?? '?',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Mark PRs that are no longer open as closed
        $this->snapshotRepo->removeClosedForRepo($user, $repoFullName, $openPrNumbers);

        $this->em->flush();

        $this->logger->debug('Snapshot refresh complete for repo', [
            'user_id' => $userId,
            'repo' => $repoFullName,
            'open_prs' => \count($openPrNumbers),
        ]);
    }

    public function markAiReviewCompleted(string $repoFullName, int $prNumber, string $summary, int $issueCount): void
    {
        $snapshots = $this->snapshotRepo->findBy([
            'repoFullName' => $repoFullName,
            'prNumber' => $prNumber,
        ]);

        foreach ($snapshots as $snapshot) {
            $snapshot->setAiReviewStatus('completed');
            $snapshot->setAiReviewSummary($summary);
            $snapshot->setAiIssueCount($issueCount);
            $snapshot->setSnapshotUpdatedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    public function markAiReviewProcessing(string $repoFullName, int $prNumber): void
    {
        $snapshots = $this->snapshotRepo->findBy([
            'repoFullName' => $repoFullName,
            'prNumber' => $prNumber,
        ]);

        foreach ($snapshots as $snapshot) {
            $snapshot->setAiReviewStatus('processing');
            $snapshot->setSnapshotUpdatedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    private function upsertFromGithub(User $user, array $prData, string $repoFullName, string $installationId, string $installationToken): void
    {
        $prNumber = (int) $prData['number'];

        $snapshot = $this->snapshotRepo->findOneByUserRepoAndPr($user, $repoFullName, $prNumber);
        if ($snapshot === null) {
            $snapshot = new PullRequestSnapshot();
            $snapshot->setAppUser($user);
            $snapshot->setRepoFullName($repoFullName);
            $snapshot->setPrNumber($prNumber);
            $this->em->persist($snapshot);
        }

        $snapshot->setInstallationId($installationId);
        $snapshot->setRepoId((string) ($prData['base']['repo']['id'] ?? ''));
        $snapshot->setPrId((string) ($prData['node_id'] ?? $prData['id'] ?? ''));
        $snapshot->setTitle((string) ($prData['title'] ?? ''));
        $snapshot->setDescription($prData['body'] ?? null);

        $authorLogin = \is_array($prData['user'] ?? null) ? (string) ($prData['user']['login'] ?? '') : '';
        $authorAvatar = \is_array($prData['user'] ?? null) ? ($prData['user']['avatar_url'] ?? null) : null;
        $snapshot->setAuthorLogin($authorLogin);
        $snapshot->setAuthorAvatarUrl(\is_string($authorAvatar) ? $authorAvatar : null);

        $snapshot->setSourceBranch((string) (\is_array($prData['head'] ?? null) ? ($prData['head']['ref'] ?? '') : ''));
        $snapshot->setTargetBranch((string) (\is_array($prData['base'] ?? null) ? ($prData['base']['ref'] ?? '') : ''));

        $isDraft = (bool) ($prData['draft'] ?? false);
        $snapshot->setIsDraft($isDraft);

        $state = (string) ($prData['state'] ?? 'open');
        $merged = ($prData['merged_at'] ?? null) !== null;
        if ($merged) {
            $snapshot->setStatus('merged');
        } elseif ($state === 'closed') {
            $snapshot->setStatus('closed');
        } elseif ($isDraft) {
            $snapshot->setStatus('draft');
        } else {
            $snapshot->setStatus('open');
        }

        $snapshot->setCommentCount((int) ($prData['comments'] ?? 0) + (int) ($prData['review_comments'] ?? 0));
        $snapshot->setChangedFiles((int) ($prData['changed_files'] ?? 0));
        $snapshot->setAdditions((int) ($prData['additions'] ?? 0));
        $snapshot->setDeletions((int) ($prData['deletions'] ?? 0));

        $ghUrl = \is_string($prData['html_url'] ?? null) ? $prData['html_url'] : \sprintf('https://github.com/%s/pull/%d', $repoFullName, $prNumber);
        $snapshot->setGithubUrl($ghUrl);

        // Labels
        $labels = [];
        if (\is_array($prData['labels'] ?? null)) {
            foreach ($prData['labels'] as $label) {
                if (\is_array($label) && \is_string($label['name'] ?? null)) {
                    $labels[] = $label['name'];
                }
            }
        }
        $snapshot->setLabels($labels);

        // Timestamps
        $openedAt = \is_string($prData['created_at'] ?? null) ? new \DateTimeImmutable($prData['created_at']) : new \DateTimeImmutable();
        $snapshot->setOpenedAt($openedAt);

        $updatedAt = \is_string($prData['updated_at'] ?? null) ? new \DateTimeImmutable($prData['updated_at']) : $openedAt;
        $snapshot->setLastActivityAt($updatedAt);
        $snapshot->setSnapshotUpdatedAt(new \DateTimeImmutable());

        // Staleness check
        $threshold = new \DateTimeImmutable("-{$snapshot->getStalenessThresholdDays()} days");
        $snapshot->setIsStale($updatedAt < $threshold);

        // Fetch review status + assigned reviewers
        $this->enrichReviewData($snapshot, $installationToken, $repoFullName, $prNumber);

        // Fetch CI status
        $this->enrichCiStatus($snapshot, $installationToken, $repoFullName, $prData);
    }

    private function enrichReviewData(PullRequestSnapshot $snapshot, string $token, string $repo, int $prNumber): void
    {
        try {
            // Requested reviewers
            $requestedData = $this->apiClient->fetchPullRequestRequestedReviewers($token, $repo, $prNumber);
            $assigned = [];
            foreach ($requestedData['users'] ?? [] as $reviewer) {
                if (\is_array($reviewer)) {
                    $assigned[] = [
                        'login' => $reviewer['login'] ?? '',
                        'avatarUrl' => $reviewer['avatar_url'] ?? null,
                    ];
                }
            }
            $snapshot->setAssignedReviewers($assigned);

            // Completed reviews
            $reviews = $this->apiClient->fetchPullRequestReviews($token, $repo, $prNumber);
            $completed = [];
            $latestByUser = [];
            foreach ($reviews as $review) {
                if (!\is_array($review)) {
                    continue;
                }
                $login = \is_array($review['user'] ?? null) ? (string) ($review['user']['login'] ?? '') : '';
                $state = (string) ($review['state'] ?? '');
                if ($login === '' || $state === 'PENDING') {
                    continue;
                }
                $latestByUser[$login] = [
                    'login' => $login,
                    'avatarUrl' => $review['user']['avatar_url'] ?? null,
                    'state' => $state,
                ];
            }
            $completed = \array_values($latestByUser);
            $snapshot->setCompletedReviews($completed);

            // Determine review status
            $hasApproved = false;
            $hasChangesRequested = false;
            foreach ($completed as $r) {
                if ($r['state'] === 'APPROVED') {
                    $hasApproved = true;
                }
                if ($r['state'] === 'CHANGES_REQUESTED') {
                    $hasChangesRequested = true;
                }
            }

            if ($hasChangesRequested) {
                $snapshot->setReviewStatus('changes_requested');
            } elseif ($hasApproved) {
                $snapshot->setReviewStatus('approved');
            } elseif (!empty($assigned)) {
                $snapshot->setReviewStatus('review_requested');
            } else {
                $snapshot->setReviewStatus('none');
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Failed to enrich review data', [
                'repo' => $repo,
                'pr' => $prNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function enrichCiStatus(PullRequestSnapshot $snapshot, string $token, string $repo, array $prData): void
    {
        try {
            $headSha = \is_array($prData['head'] ?? null) ? (string) ($prData['head']['sha'] ?? '') : '';
            if ($headSha === '') {
                return;
            }

            $checkData = $this->apiClient->fetchCombinedStatus($token, $repo, $headSha);
            $checkRuns = $checkData['check_runs'] ?? [];

            if (!\is_array($checkRuns) || empty($checkRuns)) {
                $snapshot->setCiStatus(null);
                return;
            }

            $hasFailure = false;
            $hasPending = false;
            foreach ($checkRuns as $run) {
                if (!\is_array($run)) {
                    continue;
                }
                $conclusion = $run['conclusion'] ?? null;
                $status = $run['status'] ?? null;
                if ($status !== 'completed') {
                    $hasPending = true;
                } elseif (\in_array($conclusion, ['failure', 'timed_out', 'action_required'], true)) {
                    $hasFailure = true;
                }
            }

            if ($hasFailure) {
                $snapshot->setCiStatus('failure');
            } elseif ($hasPending) {
                $snapshot->setCiStatus('pending');
            } else {
                $snapshot->setCiStatus('success');
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Failed to enrich CI status', [
                'repo' => $repo,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getInstallationToken(int $installationId): ?string
    {
        $jwt = $this->jwtService->build();
        if ($jwt === null) {
            return null;
        }

        return $this->apiClient->createInstallationToken($jwt, $installationId);
    }
}
