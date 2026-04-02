<?php

namespace App\Controller\Api\Github\Auth;

use App\Entity\GithubInstallation;
use App\Entity\User;
use App\Entity\UserGithubInstallation;
use App\Repository\GithubInstallationRepository;
use App\Repository\UserGithubInstallationRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Cache\CacheInterface;

class OAuthController extends AbstractController
{
    #[Route('/connect/github', name: 'connect_github_start')]
    public function connectGithub(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('github')
            ->redirect(['read:user', 'user:email'], []);
    }

    #[Route('/connect/github/check', name: 'connect_github_check')]
    public function connectGithubCheck(): void
    {
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST', 'GET'])]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by Symfony firewall.');
    }

    #[Route('/api/logout', name: 'app_api_logout', methods: ['POST'])]
    public function apiLogout(Request $request, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $sessionName = null;
        if ($request->hasSession()) {
            $session = $request->getSession();
            $sessionName = $session->getName();
            $session->invalidate();
        }

        $tokenStorage->setToken(null);

        $response = $this->json(['ok' => true]);
        if (\is_string($sessionName) && $sessionName !== '') {
            $response->headers->clearCookie($sessionName, '/');
        }

        return $response;
    }

    #[Route('/connect/github/app/install', name: 'connect_github_app_install', methods: ['GET'])]
    public function installGithubApp(ParameterBagInterface $params): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $installUrl = (string) $params->get('github.app_install_url');
        if ($installUrl === '') {
            return $this->json([
                'ok' => false,
                'error' => 'GITHUB_APP_INSTALL_URL is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new RedirectResponse($installUrl);
    }

    #[Route('/connect/github/app/setup', name: 'connect_github_app_setup', methods: ['GET'])]
    public function githubAppSetup(
        Request $request,
        GithubInstallationRepository $installationRepository,
        UserGithubInstallationRepository $userInstallationRepository,
        EntityManagerInterface $em,
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        LoggerInterface $logger,
        CacheInterface $cache
    ): Response {
        $frontUrl = \rtrim((string) $params->get('front_url'), '/') ?: 'http://localhost:5173';
        $installationId = $request->query->getInt('installation_id', 0);
        $setupStatus = 'success';
        $currentUser = $this->getUser();

        if ($installationId > 0) {
            $installation = $installationRepository->findOneBy(['installationId' => $installationId]);
            if (!$installation) {
                $installation = new GithubInstallation();
                $installation->setInstallationId($installationId);
                $em->persist($installation);
            }

            $this->hydrateInstallationAccountFields($installation, $installationId, $httpClient, $params, $logger);

            if ($currentUser instanceof User) {
                $link = $userInstallationRepository->findOneBy([
                    'appUser' => $currentUser,
                    'installation' => $installation,
                ]);

                if (!$link) {
                    $link = new UserGithubInstallation();
                    $link->setAppUser($currentUser)->setInstallation($installation);
                    $em->persist($link);
                }
            } else {
                $setupStatus = 'missing_user_session';
            }

            $em->flush();

            // Bust caches so the new installation is reflected immediately
            if ($currentUser instanceof User && $currentUser->getId() !== null) {
                $userId = $currentUser->getId();
                $cache->delete("github_user_repositories.{$userId}");
                $cache->delete("dashboard.payload.{$userId}");
            }
        } else {
            $setupStatus = 'missing_installation_id';
        }

        $query = \array_filter([
            'github_app_setup' => $setupStatus,
            'installation_id' => $installationId > 0 ? (string) $installationId : null,
            'setup_action' => $request->query->get('setup_action'),
            'state' => $request->query->get('state'),
        ], static fn (mixed $value): bool => \is_string($value) && $value !== '');

        $separator = \str_contains($frontUrl, '?') ? '&' : '?';
        $targetUrl = $frontUrl . (empty($query) ? '' : $separator . \http_build_query($query));

        return new RedirectResponse($targetUrl);
    }

    private function hydrateInstallationAccountFields(
        GithubInstallation $installation,
        int $installationId,
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ): void {
        $jwt = $this->buildGithubAppJwt($params);
        if ($jwt === null) {
            return;
        }

        try {
            $response = $httpClient->request('GET', "https://api.github.com/app/installations/{$installationId}", [
                'headers' => [
                    'Authorization' => "Bearer {$jwt}",
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]);
            $data = $response->toArray(false);
        } catch (HttpClientExceptionInterface|\JsonException $e) {
            $logger->warning('Failed fetching GitHub installation details', [
                'installation_id' => $installationId,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if (!\is_array($data)) {
            return;
        }

        $account = $data['account'] ?? null;
        if (!\is_array($account)) {
            return;
        }

        $login = $account['login'] ?? null;
        if (\is_string($login) && $login !== '') {
            $installation->setAccountLogin($login);
        }

        $type = $account['type'] ?? null;
        if (\is_string($type) && $type !== '') {
            $installation->setAccountType($type);
        }
    }

    private function buildGithubAppJwt(ParameterBagInterface $params): ?string
    {
        $appId = (string) $params->get('github.app_id');
        $privateKeyPath = (string) $params->get('github.private_key_path');

        if ($appId === '' || $privateKeyPath === '' || !\is_file($privateKeyPath)) {
            return null;
        }

        $privateKey = \file_get_contents($privateKeyPath);
        if (!\is_string($privateKey) || $privateKey === '') {
            return null;
        }

        $header = $this->base64UrlEncode(\json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $now = \time();
        $payload = $this->base64UrlEncode(\json_encode([
            'iat' => $now - 60,
            'exp' => $now + 540,
            'iss' => $appId,
        ], JSON_THROW_ON_ERROR));

        $unsignedToken = "{$header}.{$payload}";
        $signature = '';
        if (!\openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return "{$unsignedToken}.{$this->base64UrlEncode($signature)}";
    }

    private function base64UrlEncode(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }
}
