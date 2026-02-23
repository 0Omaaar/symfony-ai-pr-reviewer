<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GithubAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private RouterInterface $router
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_github_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('github');
        try {
            // exchange the authorization code for an access token
            $accessToken = $client->getAccessToken();

            // fetch github user profile
            $githubUser = $client->fetchUserFromToken($accessToken);

            $githubId = (string) $githubUser->getId();
            $username = $githubUser->getNickname();
            $username = is_string($username) && $username !== '' ? $username : null;

            $email = method_exists($githubUser, 'getEmail') ? $githubUser->getEmail() : null;
            $email = is_string($email) && $email !== '' ? $email : sprintf('%s@users.noreply.github.com', $githubId);
        } catch (IdentityProviderException $e) {
            throw new AuthenticationException('Failed to get access token from GitHub', 0, $e);
        }

        return new SelfValidatingPassport(
            new UserBadge($githubId, function (string $githubId) use ($username, $email) {
                $repo = $this->em->getRepository(User::class);

                /** @var User|null $user */
                $user = $repo->findOneBy(['githubId' => $githubId]);

                if (!$user) {
                    $user = new User();
                    $user->setGithubId($githubId);
                    $user->setRoles(['ROLE_USER']);
                    if ($user instanceof PasswordAuthenticatedUserInterface) {
                        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
                    }
                }

                // Refresh profile fields on every login.
                $user->setGithubUsername($username ?? '');
                $user->setEmail($email);

                $this->em->persist($user);
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirect back to frontend after successful login
        return new RedirectResponse($_ENV['FRONT_URL'] ?? 'http://localhost:5173');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // redirect to frontend with an error
        $url = ($_ENV['FRONT_URL'] ?? 'http://localhost:5173') . '/login?error=github_auth_failed';
        return new RedirectResponse($url);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['authenticated' => false], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse('/connect/github');
    }
}
