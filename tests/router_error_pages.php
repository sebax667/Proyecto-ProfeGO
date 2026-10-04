<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Http\Router;
use App\Shared\Middleware\RoleMiddleware;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$dispatch = static function (Router $router, string $method, string $uri): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    http_response_code(200);
    ob_start();
    $router->dispatch($method, $uri);
    $body = (string) ob_get_clean();

    return [http_response_code(), $body];
};

$router = new Router();
$router->get('/throws', static function (): never {
    throw new RuntimeException('Do not expose this exception.');
});
$router->get('/api/throws', static function (): never {
    throw new RuntimeException('Do not expose this API exception.');
});
$router->get('/forbidden-response', static fn (): array => [
    'status' => 'forbidden',
    'message' => 'No raw JSON on web pages.',
]);

[$notFoundStatus, $notFoundBody] = $dispatch($router, 'GET', '/missing');
$assert($notFoundStatus === 404 && str_contains($notFoundBody, '<!DOCTYPE html>') && str_contains($notFoundBody, 'Error 404'), 'Las rutas web desconocidas deben devolver una página HTML 404.');

[$serverErrorStatus, $serverErrorBody] = $dispatch($router, 'GET', '/throws');
$assert($serverErrorStatus === 500 && str_contains($serverErrorBody, '<!DOCTYPE html>') && str_contains($serverErrorBody, 'Error 500'), 'Los errores web deben devolver una página HTML 500.');
$assert(!str_contains($serverErrorBody, 'Do not expose this exception.'), 'La página 500 expuso el mensaje interno de la excepción.');

[$apiNotFoundStatus, $apiNotFoundBody] = $dispatch($router, 'GET', '/api/missing');
$apiNotFound = json_decode($apiNotFoundBody, true);
$assert($apiNotFoundStatus === 404 && is_array($apiNotFound) && ($apiNotFound['message'] ?? '') === 'Ruta no encontrada', 'Las rutas API desconocidas deben conservar JSON 404.');

[$apiServerErrorStatus, $apiServerErrorBody] = $dispatch($router, 'GET', '/api/throws');
$apiServerError = json_decode($apiServerErrorBody, true);
$assert($apiServerErrorStatus === 500 && is_array($apiServerError) && ($apiServerError['message'] ?? '') === 'Error interno del servidor.', 'Los errores API deben conservar JSON 500 sin detalles internos.');

[$forbiddenResponseStatus, $forbiddenResponseBody] = $dispatch($router, 'GET', '/forbidden-response');
$assert($forbiddenResponseStatus === 403 && str_contains($forbiddenResponseBody, 'Error 403') && !str_contains($forbiddenResponseBody, 'No raw JSON'), 'Las respuestas web forbidden deben renderizar HTML 403.');

$_SERVER['REQUEST_URI'] = '/restricted';
http_response_code(200);
ob_start();
(new RoleMiddleware(['tutor']))->handleWithUser(['role' => 'student']);
$forbiddenBody = (string) ob_get_clean();
$assert(http_response_code() === 403 && str_contains($forbiddenBody, '<!DOCTYPE html>') && str_contains($forbiddenBody, 'Error 403'), 'El acceso web denegado debe devolver una página HTML 403.');

$_SERVER['REQUEST_URI'] = '/api/restricted';
http_response_code(200);
ob_start();
(new RoleMiddleware(['tutor']))->handleWithUser(['role' => 'student']);
$apiForbiddenBody = (string) ob_get_clean();
$apiForbidden = json_decode($apiForbiddenBody, true);
$assert(http_response_code() === 403 && is_array($apiForbidden) && ($apiForbidden['status'] ?? '') === 'forbidden', 'El acceso API denegado debe conservar JSON 403.');

echo json_encode([
    'status' => 'ok',
    'message' => 'Router error pages OK',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
