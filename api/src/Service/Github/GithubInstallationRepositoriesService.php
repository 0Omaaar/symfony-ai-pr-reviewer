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
            $item->expiresAfter(120);

            return $this->fetchFreshForUser($user);
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

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
