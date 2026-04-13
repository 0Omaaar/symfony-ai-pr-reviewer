<?php

namespace App\Service\Admin;

class AdminJwtService
{
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(env: 'ADMIN_SECRET')]
        private readonly string $secret,
    ) {
    }

    public function generateToken(): string
    {
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode((string) json_encode([
            'sub' => 'admin',
            'role' => 'admin',
            'iat' => time(),
            'exp' => time() + 86400,
        ]));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $this->secret, true));

        return "$header.$payload.$signature";
    }

    public function verifyToken(string $token): bool
    {
        $parts = explode('.', $token);
        if (\count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $expectedSig = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $this->secret, true));

        if (!hash_equals($expectedSig, $signature)) {
            return false;
        }

        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!\is_array($data)) {
            return false;
        }

        return isset($data['exp'], $data['role'])
            && $data['exp'] > time()
            && $data['role'] === 'admin';
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
