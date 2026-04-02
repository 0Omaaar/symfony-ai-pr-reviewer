<?php

namespace App\Service\Github;

use App\Entity\User;
use App\Entity\UserGithubInstallation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GithubInstallationRepositoriesService
{
    public function __construct(private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly ParameterBagInterface $params,
        private readonly CacheInterface $cache,)
    {
    }

    public function fetchForUser(User $user): array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching repositories.');
        }

        $cacheKey = sprintf('github_user_repositories.%d', $userId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user): array {
            $repos = $this->fetchFreshForUser($user);

            // Don't cache empty results — the user may not have installations yet
            $item->expiresAfter(empty($repos) ? 10 : 120);

            return $repos;
        });
    }

    public function fetchDetailsForUserRepository(User $user, int $repoId): ?array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching repository details.');
        }

        $cacheKey = sprintf('github_user_repository_details.%d.%d', $userId, $repoId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $repoId): ?array {
            $item->expiresAfter(120);

            return $this->buildRepositoryDetailsPayload($user, $repoId);
        });
    }

    public function refreshDetailsForUserRepository(User $user, int $repoId): ?array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before refreshing repository details.');
        }

        $cacheKey = sprintf('github_user_repository_details.%d.%d', $userId, $repoId);
        $this->cache->delete($cacheKey);

        return $this->buildRepositoryDetailsPayload($user, $repoId);
    }

    public function hasMeaningfulDetailsData(array $details): bool
    {
        $branches = is_array($details['branches'] ?? null) ? $details['branches'] : [];
        $pullRequests = is_array($details['pull_requests'] ?? null) ? $details['pull_requests'] : [];
        $insights = is_array($details['insights'] ?? null) ? $details['insights'] : [];
        $commits = is_int($insights['commits_count'] ?? null) ? $insights['commits_count'] : 0;
        $participants = is_int($insights['participants_count'] ?? null) ? $insights['participants_count'] : 0;

        return count($branches) > 0 || count($pullRequests) > 0 || $commits > 0 || $participants > 0;
    }

    public function fetchLatestPullRequestEventForUserRepository(User $user, int $repoId): ?array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching repository events.');
        }

        $event = $this->cache->get(sprintf('github_user_repository_latest_event.%d.%d', $userId, $repoId), static function (ItemInterface $item): ?array {
            $item->expiresAfter(1);

            return null;
        });

        return is_array($event) ? $event : null;
    }

    public function processPullRequestWebhookEvent(
        int $installationId,
        int $repoId,
        int $prNumber,
        string $action,
        string $deliveryId,
        string $headSha
    ): array {
        $links = $this->em->getRepository(UserGithubInstallation::class)
            ->createQueryBuilder('link')
            ->innerJoin('link.installation', 'installation')
            ->innerJoin('link.appUser', 'app_user')
            ->andWhere('installation.installationId = :installationId')
            ->setParameter('installationId', $installationId)
            ->getQuery()
            ->getResult();

        $recipients = [];
        $processedUserIds = [];

        foreach ($links as $link) {
            if (!$link instanceof UserGithubInstallation) {
                continue;
            }

            $appUser = $link->getAppUser();
            if (!$appUser instanceof User) {
                continue;
            }

            $userId = $appUser->getId();
            if (!is_int($userId)) {
                continue;
            }

            if (isset($processedUserIds[$userId])) {
                continue;
            }
            $processedUserIds[$userId] = true;

            if ($this->findUserRepositoryById($appUser, $repoId) === null) {
                continue;
            }

            $this->cache->delete(sprintf('github_user_repositories.%d', $userId));
            $this->cache->delete(sprintf('dashboard.payload.%d', $userId));
            $this->cache->delete(sprintf('github_user_repository_details.%d.%d', $userId, $repoId));
            $this->cache->delete(sprintf('github_user_repository_pull_requests.%d.%d', $userId, $repoId));
            $this->cache->delete(sprintf('github_user_repository_branches.%d.%d', $userId, $repoId));
            $this->cache->delete(sprintf('github_user_repository_insights.%d.%d', $userId, $repoId));
            $this->cache->delete(sprintf('github_user_pr_id_to_repo_id.%d', $userId));

            $eventPayload = [
                'delivery_id' => $deliveryId,
                'action' => $action,
                'repo_id' => $repoId,
                'pr_number' => $prNumber,
                'head_sha' => $headSha,
                'message' => $this->buildPullRequestEventMessage($action, $prNumber),
                'occurred_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];

            $eventKey = sprintf('github_user_repository_latest_event.%d.%d', $userId, $repoId);
            $this->cache->delete($eventKey);
            $this->cache->get($eventKey, static function (ItemInterface $item) use ($eventPayload): array {
                $item->expiresAfter(3600);

                return $eventPayload;
            });

            $recipients[] = [
                'user_id' => $userId,
                'email' => $appUser->getEmail(),
                'github_username' => $appUser->getGithubUsername(),
                'email_notifications_enabled' => $appUser->isEmailNotificationsEnabled(),
                'unsubscribe_token' => $appUser->getUnsubscribeToken(),
            ];
        }

        return $recipients;
    }

    public function fetchPullRequestByIdForUser(User $user, int $pullRequestId): ?array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching pull request details.');
        }

        $cacheKey = sprintf('github_user_pull_request_details.%d.%d', $userId, $pullRequestId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $pullRequestId): ?array {
            $item->expiresAfter(120);

            $map = $this->buildPrIdToRepoIdMap($user);
            $repoId = $map[$pullRequestId] ?? null;
            if ($repoId === null) {
                return null;
            }

            $repository = $this->findUserRepositoryById($user, $repoId);
            if ($repository === null) {
                return null;
            }

            $pullRequests = $this->fetchPullRequestsForUserRepository($user, $repoId);
            foreach ($pullRequests as $pullRequest) {
                if (is_array($pullRequest) && isset($pullRequest['id']) && (int) $pullRequest['id'] === $pullRequestId) {
                    return [
                        'repository' => $repository,
                        'pull_request' => $pullRequest,
                    ];
                }
            }

            return null;
        });
    }

    private function buildPrIdToRepoIdMap(User $user): array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before building PR index.');
        }

        $cacheKey = sprintf('github_user_pr_id_to_repo_id.%d', $userId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user): array {
            $item->expiresAfter(120);

            $map = [];
            $repositories = $this->fetchForUser($user);
            foreach ($repositories as $repository) {
                if (!is_array($repository) || !is_int($repository['id'] ?? null)) {
                    continue;
                }

                $repoId = $repository['id'];
                $pullRequests = $this->fetchPullRequestsForUserRepository($user, $repoId);
                foreach ($pullRequests as $pullRequest) {
                    if (is_array($pullRequest) && is_int($pullRequest['id'] ?? null)) {
                        $map[$pullRequest['id']] = $repoId;
                    }
                }
            }

            return $map;
        });
    }

    public function fetchPullRequestChangesByIdForUser(User $user, int $pullRequestId): ?array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching pull request changes.');
        }

        $cacheKey = sprintf('github_user_pull_request_changes.%d.%d', $userId, $pullRequestId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $pullRequestId): ?array {
            $item->expiresAfter(120);

            $details = $this->fetchPullRequestByIdForUser($user, $pullRequestId);
            if ($details === null) {
                return null;
            }

            $repository = is_array($details['repository'] ?? null) ? $details['repository'] : null;
            $pullRequest = is_array($details['pull_request'] ?? null) ? $details['pull_request'] : null;
            $fullName = is_array($repository) ? ($repository['full_name'] ?? null) : null;
            $installationId = is_array($repository) ? ($repository['installation_id'] ?? null) : null;
            $pullNumber = is_array($pullRequest) ? ($pullRequest['number'] ?? null) : null;

            if (!is_string($fullName) || $fullName === '' || !is_int($installationId) || !is_int($pullNumber)) {
                return null;
            }

            $jwt = $this->buildGithubAppJwt();
            if ($jwt === null) {
                throw new \RuntimeException('GITHUB APP JWT Cannot be created !');
            }

            $installationToken = $this->createInstallationToken($jwt, $installationId);
            if ($installationToken === null) {
                return null;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $installationToken,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ];

            $pullResponse = $this->httpClient->request(
                'GET',
                sprintf('https://api.github.com/repos/%s/pulls/%d', $fullName, $pullNumber),
                ['headers' => $headers]
            );
            $pullData = $pullResponse->toArray(false);

            $files = [];
            $page = 1;
            $hasMore = true;

            do {
                $filesResponse = $this->httpClient->request(
                    'GET',
                    sprintf('https://api.github.com/repos/%s/pulls/%d/files?per_page=100&page=%d', $fullName, $pullNumber, $page),
                    ['headers' => $headers]
                );

                $filesData = $filesResponse->toArray(false);
                $fileItems = is_array($filesData) ? $filesData : [];

                foreach ($fileItems as $fileItem) {
                    if (!is_array($fileItem) || !is_string($fileItem['filename'] ?? null)) {
                        continue;
                    }

                    $files[] = [
                        'filename' => $fileItem['filename'],
                        'status' => is_string($fileItem['status'] ?? null) ? $fileItem['status'] : 'modified',
                        'additions' => is_int($fileItem['additions'] ?? null) ? $fileItem['additions'] : 0,
                        'deletions' => is_int($fileItem['deletions'] ?? null) ? $fileItem['deletions'] : 0,
                        'changes' => is_int($fileItem['changes'] ?? null) ? $fileItem['changes'] : 0,
                        'patch' => is_string($fileItem['patch'] ?? null) ? $fileItem['patch'] : null,
                        'previous_filename' => is_string($fileItem['previous_filename'] ?? null) ? $fileItem['previous_filename'] : null,
                    ];
                }

                $hasMore = count($fileItems) === 100;
                $page++;
            } while ($hasMore);

            return [
                'summary' => [
                    'changed_files' => is_int($pullData['changed_files'] ?? null) ? $pullData['changed_files'] : count($files),
                    'additions' => is_int($pullData['additions'] ?? null) ? $pullData['additions'] : 0,
                    'deletions' => is_int($pullData['deletions'] ?? null) ? $pullData['deletions'] : 0,
                    'commits' => is_int($pullData['commits'] ?? null) ? $pullData['commits'] : 0,
                    'comments' => is_int($pullData['comments'] ?? null) ? $pullData['comments'] : 0,
                    'review_comments' => is_int($pullData['review_comments'] ?? null) ? $pullData['review_comments'] : 0,
                ],
                'files' => $files,
            ];
        });
    }

    public function fetchBranchesForUserRepository(User $user, int $repoId): array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching branches.');
        }

        $cacheKey = sprintf('github_user_repository_branches.%d.%d', $userId, $repoId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $repoId): array {
            $item->expiresAfter(120);

            $repository = $this->findUserRepositoryById($user, $repoId);
            if ($repository === null) {
                return [];
            }

            $fullName = $repository['full_name'] ?? null;
            $installationId = $repository['installation_id'] ?? null;
            if (!is_string($fullName) || $fullName === '' || !is_int($installationId)) {
                return [];
            }

            $jwt = $this->buildGithubAppJwt();
            if ($jwt === null) {
                throw new \RuntimeException('GITHUB APP JWT Cannot be created !');
            }

            $installationToken = $this->createInstallationToken($jwt, $installationId);
            if ($installationToken === null) {
                return [];
            }

            $page = 1;
            $branches = [];
            $hasMore = true;

            do {
                $response = $this->httpClient->request(
                    'GET',
                    sprintf('https://api.github.com/repos/%s/branches?per_page=100&page=%d', $fullName, $page),
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $installationToken,
                            'Accept' => 'application/vnd.github+json',
                            'X-GitHub-Api-Version' => '2022-11-28',
                        ],
                    ]
                );

                $data = $response->toArray(false);
                $branchItems = is_array($data) ? $data : [];

                foreach ($branchItems as $branchItem) {
                    if (!is_array($branchItem) || !isset($branchItem['name'])) {
                        continue;
                    }

                    $branches[] = [
                        'name' => $branchItem['name'] ?? null,
                        'protected' => $branchItem['protected'] ?? false,
                        'commit_sha' => is_array($branchItem['commit'] ?? null) ? ($branchItem['commit']['sha'] ?? null) : null,
                    ];
                }

                $hasMore = count($branchItems) === 100;
                $page++;
            } while ($hasMore);

            return $branches;
        });
    }

    public function fetchPullRequestsForUserRepository(User $user, int $repoId): array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching pull requests.');
        }

        $cacheKey = sprintf('github_user_repository_pull_requests.%d.%d', $userId, $repoId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $repoId): array {
            $item->expiresAfter(120);

            $repository = $this->findUserRepositoryById($user, $repoId);
            if ($repository === null) {
                return [];
            }

            $fullName = $repository['full_name'] ?? null;
            $installationId = $repository['installation_id'] ?? null;
            if (!is_string($fullName) || $fullName === '' || !is_int($installationId)) {
                return [];
            }

            $jwt = $this->buildGithubAppJwt();
            if ($jwt === null) {
                throw new \RuntimeException('GITHUB APP JWT Cannot be created !');
            }

            $installationToken = $this->createInstallationToken($jwt, $installationId);
            if ($installationToken === null) {
                return [];
            }

            $page = 1;
            $pullRequests = [];
            $hasMore = true;

            do {
                $response = $this->httpClient->request(
                    'GET',
                    sprintf('https://api.github.com/repos/%s/pulls?state=all&per_page=100&page=%d', $fullName, $page),
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $installationToken,
                            'Accept' => 'application/vnd.github+json',
                            'X-GitHub-Api-Version' => '2022-11-28',
                        ],
                    ]
                );

                $data = $response->toArray(false);
                $prItems = is_array($data) ? $data : [];

                foreach ($prItems as $prItem) {
                    if (!is_array($prItem) || !isset($prItem['id']) || !isset($prItem['number'])) {
                        continue;
                    }

                    $state = is_string($prItem['state'] ?? null) ? $prItem['state'] : 'open';
                    $status = $state;
                    if (($prItem['merged_at'] ?? null) !== null) {
                        $status = 'merged';
                    } elseif ($state !== 'open') {
                        $status = 'closed';
                    }

                    $pullRequests[] = [
                        'id' => $prItem['id'] ?? null,
                        'repo_id' => $repoId,
                        'number' => $prItem['number'] ?? null,
                        'title' => $prItem['title'] ?? '',
                        'status' => $status,
                        'head_sha' => is_array($prItem['head'] ?? null) ? ($prItem['head']['sha'] ?? null) : null,
                        'updated_at' => $prItem['updated_at'] ?? null,
                    ];
                }

                $hasMore = count($prItems) === 100;
                $page++;
            } while ($hasMore);

            return $pullRequests;
        });
    }

    public function fetchInsightsForUserRepository(User $user, int $repoId): array
    {
        $userId = $user->getId();
        if (!is_int($userId)) {
            throw new \RuntimeException('User must be persisted before fetching repository insights.');
        }

        $cacheKey = sprintf('github_user_repository_insights.%d.%d', $userId, $repoId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $repoId): array {
            $item->expiresAfter(120);

            $repository = $this->findUserRepositoryById($user, $repoId);
            if ($repository === null) {
                return [];
            }

            $fullName = $repository['full_name'] ?? null;
            $installationId = $repository['installation_id'] ?? null;
            if (!is_string($fullName) || $fullName === '' || !is_int($installationId)) {
                return [];
            }

            $jwt = $this->buildGithubAppJwt();
            if ($jwt === null) {
                throw new \RuntimeException('GITHUB APP JWT Cannot be created !');
            }

            $installationToken = $this->createInstallationToken($jwt, $installationId);
            if ($installationToken === null) {
                return [];
            }

            $repoResponse = $this->httpClient->request(
                'GET',
                sprintf('https://api.github.com/repos/%s', $fullName),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $installationToken,
                        'Accept' => 'application/vnd.github+json',
                        'X-GitHub-Api-Version' => '2022-11-28',
                    ],
                ]
            );
            $repoData = $repoResponse->toArray(false);

            $contributorsResponse = $this->httpClient->request(
                'GET',
                sprintf('https://api.github.com/repos/%s/contributors?per_page=100&anon=1', $fullName),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $installationToken,
                        'Accept' => 'application/vnd.github+json',
                        'X-GitHub-Api-Version' => '2022-11-28',
                    ],
                ]
            );
            $contributorsData = $contributorsResponse->toArray(false);
            $contributors = is_array($contributorsData) ? $contributorsData : [];

            $participants = [];
            foreach ($contributors as $contributor) {
                if (!is_array($contributor)) {
                    continue;
                }

                $login = $contributor['login'] ?? $contributor['name'] ?? null;
                if (!is_string($login) || $login === '') {
                    continue;
                }

                $participants[] = [
                    'login' => $login,
                    'avatar_url' => is_string($contributor['avatar_url'] ?? null) ? $contributor['avatar_url'] : null,
                    'html_url' => is_string($contributor['html_url'] ?? null) ? $contributor['html_url'] : null,
                    'contributions' => is_int($contributor['contributions'] ?? null) ? $contributor['contributions'] : null,
                ];
            }

            $commitsCount = $this->fetchCommitCount($fullName, $installationToken);

            return [
                'commits_count' => $commitsCount,
                'participants_count' => count($participants),
                'participants' => array_slice($participants, 0, 12),
                'stargazers_count' => is_int($repoData['stargazers_count'] ?? null) ? $repoData['stargazers_count'] : 0,
                'forks_count' => is_int($repoData['forks_count'] ?? null) ? $repoData['forks_count'] : 0,
                'open_issues_count' => is_int($repoData['open_issues_count'] ?? null) ? $repoData['open_issues_count'] : 0,
                'watchers_count' => is_int($repoData['subscribers_count'] ?? null) ? $repoData['subscribers_count'] : 0,
                'size_kb' => is_int($repoData['size'] ?? null) ? $repoData['size'] : 0,
                'primary_language' => is_string($repoData['language'] ?? null) ? $repoData['language'] : null,
                'topics' => is_array($repoData['topics'] ?? null) ? $repoData['topics'] : [],
                'created_at' => is_string($repoData['created_at'] ?? null) ? $repoData['created_at'] : null,
                'updated_at' => is_string($repoData['updated_at'] ?? null) ? $repoData['updated_at'] : null,
                'pushed_at' => is_string($repoData['pushed_at'] ?? null) ? $repoData['pushed_at'] : null,
            ];
        });
    }

    private function fetchFreshForUser(User $user): array
    {
        $links = $this->em->getRepository(UserGithubInstallation::class)
            ->findBy(['appUser' => $user]);

        $jwt = $this->buildGithubAppJwt();
        if ($jwt === null) {
            throw new \RuntimeException('GITHUB APP JWT Cannot be created !');
        }

        $byRepoId = [];

        foreach ($links as $link) {
            $installation = $link->getInstallation();
            if ($installation === null || $installation->getInstallationId() === null) {
                continue;
            }

            $installationId = $installation->getInstallationId();
            $installationToken = $this->createInstallationToken($jwt, $installationId);
            if ($installationToken === null) {
                continue;
            }

            $page = 1;
            $hasMore = true;
            do {
                $response = $this->httpClient->request(
                    'GET',
                    'https://api.github.com/installation/repositories?per_page=100&page=' . $page,
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $installationToken,
                            'Accept' => 'application/vnd.github+json',
                            'X-GitHub-Api-Version' => '2022-11-28',
                        ],
                    ]
                );

                $data = $response->toArray(false);
                $repos = is_array($data['repositories'] ?? null) ? $data['repositories'] : [];

                foreach ($repos as $repo) {
                    if (!is_array($repo) || !isset($repo['id'])) {
                        continue;
                    }

                    $repoId = (string) $repo['id'];
                    $byRepoId[$repoId] = [
                        'id' => $repo['id'] ?? null,
                        'name' => $repo['name'] ?? null,
                        'full_name' => $repo['full_name'] ?? null,
                        'private' => $repo['private'] ?? null,
                        'html_url' => $repo['html_url'] ?? null,
                        'default_branch' => $repo['default_branch'] ?? null,
                        'installation_id' => $installationId,
                    ];
                }

                $totalCount = is_int($data['total_count'] ?? null) ? $data['total_count'] : 0;
                $hasMore = ($page * 100) < $totalCount;
                $page++;
            } while($hasMore);
        }

        return array_values($byRepoId);
    }

    private function findUserRepositoryById(User $user, int $repoId): ?array
    {
        $repos = $this->fetchForUser($user);

        foreach ($repos as $repo) {
            if (is_array($repo) && isset($repo['id']) && (int) $repo['id'] === $repoId) {
                return $repo;
            }
        }

        return null;
    }

    private function buildRepositoryDetailsPayload(User $user, int $repoId): ?array
    {
        $repository = $this->findUserRepositoryById($user, $repoId);
        if ($repository === null) {
            return null;
        }

        return [
            'repository' => $repository,
            'branches' => $this->fetchBranchesForUserRepository($user, $repoId),
            'pull_requests' => $this->fetchPullRequestsForUserRepository($user, $repoId),
            'insights' => $this->fetchInsightsForUserRepository($user, $repoId),
        ];
    }

    private function createInstallationToken(string $appJwt, int $installationId): ?string
    {
        $response = $this->httpClient->request(
            'POST',
            sprintf('https://api.github.com/app/installations/%d/access_tokens', $installationId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $appJwt,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]
        );

        $data = $response->toArray(false);
        $token = $data['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function fetchCommitCount(string $fullName, string $installationToken): int
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('https://api.github.com/repos/%s/commits?per_page=1&page=1', $fullName),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $installationToken,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            return 0;
        }

        $headers = $response->getHeaders(false);
        $linkHeader = null;
        if (is_array($headers['link'] ?? null) && isset($headers['link'][0]) && is_string($headers['link'][0])) {
            $linkHeader = $headers['link'][0];
        }

        if (is_string($linkHeader)) {
            $lastPage = $this->extractLastPageFromLinkHeader($linkHeader);
            if ($lastPage !== null) {
                return $lastPage;
            }
        }

        $data = $response->toArray(false);
        return is_array($data) ? count($data) : 0;
    }

    private function extractLastPageFromLinkHeader(string $linkHeader): ?int
    {
        if (!preg_match('/[?&]page=(\d+)>;\s*rel="last"/', $linkHeader, $matches)) {
            return null;
        }

        $lastPage = (int) ($matches[1] ?? 0);
        return $lastPage > 0 ? $lastPage : null;
    }

    private function buildGithubAppJwt(): ?string
    {
        $appId = (string) $this->params->get('github.app_id');
        $privateKeyPath = (string) $this->params->get('github.private_key_path');

        if ($appId === '' || $privateKeyPath === '' || !is_file($privateKeyPath)) {
            return null;
        }

        $privateKey = file_get_contents($privateKeyPath);
        if (!is_string($privateKey) || $privateKey === '') {
            return null;
        }

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $now = time();
        $payload = $this->base64UrlEncode(json_encode([
            'iat' => $now - 60,
            'exp' => $now + 540,
            'iss' => $appId,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header . "." . $payload;
        $signature = '';
        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }

    private function buildPullRequestEventMessage(string $action, int $prNumber): string
    {
        return match ($action) {
            'opened' => sprintf('Pull request #%d was opened.', $prNumber),
            'reopened' => sprintf('Pull request #%d was reopened.', $prNumber),
            'closed' => sprintf('Pull request #%d was closed.', $prNumber),
            'synchronize' => sprintf('Pull request #%d received new commits.', $prNumber),
            'ready_for_review' => sprintf('Pull request #%d is ready for review.', $prNumber),
            'converted_to_draft' => sprintf('Pull request #%d was converted to draft.', $prNumber),
            default => sprintf('Pull request #%d event received (%s).', $prNumber, $action !== '' ? $action : 'unknown'),
        };
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
