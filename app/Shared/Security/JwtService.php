<?php

declare(strict_types=1);

namespace App\Shared\Security;

class JwtService
{
    private readonly string $secretKey;

    public function __construct(?string $secretKey = null)
    {
        $configuredSecret = $secretKey ?? (string) getenv('JWT_SECRET');
        $configuredSecret = trim($configuredSecret);

        if ($configuredSecret === '' || strlen($configuredSecret) < 32 || str_starts_with($configuredSecret, 'replace-with')) {
            throw new \RuntimeException('JWT_SECRET debe estar configurado y tener al menos 32 caracteres válidos.');
        }

        $this->secretKey = $configuredSecret;
    }

    public function generateToken(array $payload, int $expirySeconds = 3600): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR);
        $tokenId = bin2hex(random_bytes(16));
        $issuedAt = time();

        $tokenPayload = [
            'iss' => 'profego',
            'iat' => $issuedAt,
            'exp' => $issuedAt + $expirySeconds,
            'jti' => $tokenId,
            ...$payload,
        ];

        $payloadJson = json_encode($tokenPayload, JSON_THROW_ON_ERROR);
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $this->secretKey, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    public function validateToken(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $header = json_decode($this->base64UrlDecode($base64Header), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $validSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $base64Header . '.' . $base64Payload, $this->secretKey, true)
        );

        if (!hash_equals($validSignature, $base64Signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($base64Payload), true);
        if (!is_array($payload)) {
            return null;
        }

        if (($payload['iss'] ?? null) !== 'profego') {
            return null;
        }

        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            return null;
        }

        if (!isset($payload['jti']) || trim((string) $payload['jti']) === '') {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}