<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

class CsrfOriginMiddleware
{
    public function handle(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $secFetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $expectedOrigin = $scheme . '://' . $host;

        if ($origin !== '' && strtolower($origin) !== strtolower($expectedOrigin)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'forbidden',
                'message' => 'Origen no válido.',
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        if ($origin === '' && in_array($secFetchSite, ['cross-site', 'same-site'], true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'forbidden',
                'message' => 'Origen no válido.',
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        return true;
    }
}
