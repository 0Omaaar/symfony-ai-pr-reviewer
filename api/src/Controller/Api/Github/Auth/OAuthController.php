<?php

namespace App\Controller\Api\Github\Auth;

use App\Entity\GithubInstallation;
use App\Entity\User;
use App\Entity\UserGithubInstallation;
use App\Repository\GithubInstallationRepository;
use App\Repository\UserGithubInstallationRepository;
use App\Service\CacheKeys;
use App\Service\Github\GithubApiClient;
use App\Service\Github\GithubAppJwtService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
        GithubApiClient $apiClient,
        ParameterBagInterface $params,
        LoggerInterface $logger,
        CacheInterface $cache,
        GithubAppJwtService $jwtService
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

            $this->hydrateInstallationAccountFields($installation, $installationId, $apiClient, $jwtService, $logger);

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
                $cache->delete(CacheKeys::userRepositories($userId));
                $cache->delete(CacheKeys::dashboardPayload($userId));
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
        GithubApiClient $apiClient,
        GithubAppJwtService $jwtService,
        LoggerInterface $logger
    ): void {
        $jwt = $jwtService->build();
        if ($jwt === null) {
            return;
        }

        try {
            $data = $apiClient->fetchInstallation($jwt, $installationId);
        } catch (\Throwable $e) {
            $logger->warning('Failed fetching GitHub installation details', [
                'installation_id' => $installationId,
                'error' => $e->getMessage(),
            ]);
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

}

