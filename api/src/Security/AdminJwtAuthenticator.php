<?php

namespace App\Security;

use App\Service\Admin\AdminJwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AdminJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly AdminJwtService $jwtService)
    {
    }

    private function extractToken(Request $request): ?string
    {
        $auth = $request->headers->get('Authorization', '');
        if (str_starts_with((string) $auth, 'Bearer ')) {
            return substr((string) $auth, 7);
        }

        $query = $request->query->get('token');
        if (\is_string($query) && $query !== '') {
            return $query;
        }

        return null;
    }

    public function supports(Request $request): ?bool
    {
        return $this->extractToken($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);

        if ($token === null || !$this->jwtService->verifyToken($token)) {
            throw new AuthenticationException('Invalid or expired admin token.');
        }

        return new SelfValidatingPassport(
            new UserBadge('admin', fn () => new AdminUser())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized', 'message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }
}
