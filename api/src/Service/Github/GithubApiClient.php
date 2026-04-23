<?php

namespace App\Service\Github;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Low-level GitHub API HTTP client.
 * Knows only how to talk to GitHub — no caching, no business logic.
 */
final class GithubApiClient
{
    private const GITHUB_API_VERSION = '2022-11-28';
    private const ACCEPT_HEADER = 'application/vnd.github+json';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function createInstallationToken(string $appJwt, int $installationId): ?string
    {
        $response = $this->httpClient->request(
            'POST',
            \sprintf('https://api.github.com/app/installations/%d/access_tokens', $installationId),
            ['headers' => $this->authHeaders($appJwt)]
        );

        $data = $response->toArray(false);
        $token = $data['token'] ?? null;

        return \is_string($token) && $token !== '' ? $token : null;
    }

    public function fetchInstallation(string $appJwt, int $installationId): array
    {
        $response = $this->httpClient->request(
            'GET',
            "https://api.github.com/app/installations/{$installationId}",
            ['headers' => $this->authHeaders($appJwt)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    /** @return array{repositories: array, total_count: int} */
    public function fetchInstallationRepositories(string $installationToken, int $page): array
    {
        $response = $this->httpClient->request(
            'GET',
            'https://api.github.com/installation/repositories?per_page=100&page=' . $page,
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return [
            'repositories' => \is_array($data['repositories'] ?? null) ? $data['repositories'] : [],
            'total_count' => \is_int($data['total_count'] ?? null) ? $data['total_count'] : 0,
        ];
    }

    public function fetchBranches(string $installationToken, string $fullName, int $page): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/branches?per_page=100&page=%d', $fullName, $page),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchPullRequests(string $installationToken, string $fullName, int $page): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/pulls?state=all&per_page=100&page=%d', $fullName, $page),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchPullRequest(string $installationToken, string $fullName, int $number): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/pulls/%d', $fullName, $number),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchPullRequestFiles(string $installationToken, string $fullName, int $number, int $page): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/pulls/%d/files?per_page=100&page=%d', $fullName, $number, $page),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchPullRequestCommits(string $installationToken, string $fullName, int $number, int $page = 1): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/pulls/%d/commits?per_page=100&page=%d', $fullName, $number, $page),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchRepository(string $installationToken, string $fullName): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s', $fullName),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchContributors(string $installationToken, string $fullName): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/contributors?per_page=100&anon=1', $fullName),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    /**
     * Returns the total commit count by reading the last page number from the Link header.
     * Falls back to counting the single returned item.
     */
    public function fetchCommitCount(string $installationToken, string $fullName): int
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/commits?per_page=1&page=1', $fullName),
            ['headers' => $this->authHeaders($installationToken)]
        );

        if ($response->getStatusCode() >= 400) {
            return 0;
        }

        $headers = $response->getHeaders(false);
        $linkHeader = \is_array($headers['link'] ?? null) && \is_string($headers['link'][0] ?? null)
            ? $headers['link'][0]
            : null;

        if ($linkHeader !== null) {
            $lastPage = $this->extractLastPageFromLinkHeader($linkHeader);
            if ($lastPage !== null) {
                return $lastPage;
            }
        }

        $data = $response->toArray(false);

        return \is_array($data) ? \count($data) : 0;
    }

    public function fetchPullRequestReviews(string $installationToken, string $fullName, int $number): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/pulls/%d/reviews?per_page=100', $fullName, $number),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchPullRequestRequestedReviewers(string $installationToken, string $fullName, int $number): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/pulls/%d/requested_reviewers', $fullName, $number),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    public function fetchCombinedStatus(string $installationToken, string $fullName, string $ref): array
    {
        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/commits/%s/check-runs?per_page=100', $fullName, $ref),
            ['headers' => $this->authHeaders($installationToken)]
        );

        $data = $response->toArray(false);

        return \is_array($data) ? $data : [];
    }

    private function extractLastPageFromLinkHeader(string $linkHeader): ?int
    {
        if (!\preg_match('/[?&]page=(\d+)>;\s*rel="last"/', $linkHeader, $matches)) {
            return null;
        }

        $lastPage = (int) ($matches[1] ?? 0);

        return $lastPage > 0 ? $lastPage : null;
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => self::ACCEPT_HEADER,
            'X-GitHub-Api-Version' => self::GITHUB_API_VERSION,
        ];
    }
}
