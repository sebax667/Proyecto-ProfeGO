<?php

declare(strict_types=1);

namespace App\Shared\Http;

final class ErrorPage
{
    public static function send(int $statusCode): void
    {
        $viewPath = dirname(__DIR__, 3) . '/resources/views/errors/' . $statusCode . '.php';
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');

        if (is_file($viewPath)) {
            require $viewPath;
            return;
        }

        echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>Error</title><h1>Error</h1></html>';
    }
}