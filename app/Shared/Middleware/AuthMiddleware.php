<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use App\Shared\Security\JwtService;

class AuthMiddleware
{
    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
    }

    public function handle(): ?array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_starts_with($authHeader, 'Bearer ')
            ? substr($authHeader, 7)
            : (string) ($_COOKIE['profego_token'] ?? '');

        if ($token === '') {
            $this->denyAccess('Acceso denegado. Se requiere autenticación.');
            return ['error' => true];
        }

        $payload = $this->jwtService->validateToken($token);

        if (!$payload) {
            $this->denyAccess('Acceso denegado. Token expirado o firma inválida.');
            return ['error' => true];
        }

        return $payload;
    }

    private function denyAccess(string $message): void
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        $acceptsJson = str_contains(
            strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')),
            'application/json'
        );

        if (!str_starts_with($path, '/api/') && !$acceptsJson) {
            $redirect = '/login?redirect=' . rawurlencode($path);
            header('Location: ' . $redirect, true, 302);
            return;
        }

        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'unauthorized',
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
    }
}