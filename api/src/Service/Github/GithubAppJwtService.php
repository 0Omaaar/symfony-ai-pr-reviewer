<?php

namespace App\Service\Github;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds a short-lived RS256 JWT signed with the GitHub App private key.
 * Used to authenticate as the GitHub App before exchanging for installation tokens.
 */
final class GithubAppJwtService
{
    public function __construct(
        #[Autowire(param: 'github.app_id')] private readonly string $appId,
        #[Autowire(param: 'github.private_key_path')] private readonly string $privateKeyPath,
    ) {
    }

    public function build(): ?string
    {
        if ($this->appId === '' || $this->privateKeyPath === '' || !\is_file($this->privateKeyPath)) {
            return null;
        }

        $privateKey = \file_get_contents($this->privateKeyPath);
        if (!\is_string($privateKey) || $privateKey === '') {
            return null;
        }

        $header = $this->base64UrlEncode(\json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $now = \time();
        $payload = $this->base64UrlEncode(\json_encode([
            'iat' => $now - 60,
            'exp' => $now + 540,
            'iss' => $this->appId,
        ], JSON_THROW_ON_ERROR));

        $unsigned = "{$header}.{$payload}";
        $signature = '';
        if (!\openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return "{$unsigned}.{$this->base64UrlEncode($signature)}";
    }

    private function base64UrlEncode(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }
}
