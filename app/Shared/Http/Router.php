<?php

declare(strict_types=1);

namespace App\Shared\Http;

use ReflectionMethod;

final class Router
{
    /**
     * @var array<int, array{method: string, path: string, handler: callable|array, middlewares: array<int, class-string>}> 
     */
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * @param callable|array $handler
     * @param array<int, class-string> $middlewares
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $method = strtoupper($requestMethod);
        $path = $this->normalizePath((string) (parse_url($requestUri, PHP_URL_PATH) ?? '/'));

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !$this->matchesPath($path, $route['path'])) {
                continue;
            }

            $authenticatedUser = null;
            foreach ($route['middlewares'] as $middlewareClass) {
                $middleware = new $middlewareClass();
                $result = method_exists($middleware, 'handleWithUser')
                    ? $middleware->handleWithUser($authenticatedUser)
                    : $middleware->handle();

                if ($result === false || (is_array($result) && ($result['error'] ?? false))) {
                    return;
                }

                if (is_array($result) && !isset($result['error'])) {
                    $authenticatedUser = $result;
                }
            }

            $response = $this->callHandler($route['handler'], $authenticatedUser);
            $this->sendResponse($response);
            return;
        }

        $this->sendJsonResponse(404, [
            'status' => 'error',
            'message' => 'Ruta no encontrada',
        ]);
    }

    private function matchesPath(string $requestPath, string $routePath): bool
    {
        $routePath = $this->normalizePath($routePath);

        if ($requestPath === $routePath) {
            return true;
        }

        return strlen($requestPath) > strlen($routePath)
            && str_ends_with($requestPath, '/' . ltrim($routePath, '/'));
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim(preg_replace('#/+#', '/', $path) ?? '', '/');

        if ($path === '/') {
            return $path;
        }

        return $path;
    }

    /**
     * @param callable|array $handler
     * @return mixed
     */
    private function callHandler(array|callable $handler, mixed $authenticatedUser = null): mixed
    {
        $request = $_REQUEST;
        if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'PATCH'], true)
            && str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $request = array_merge($request, $decoded);
            }
        }
        if (is_array($handler)) {
            if (count($handler) !== 2) {
                throw new \InvalidArgumentException('El handler de ruta debe ser un array con [Controller, Metodo].');
            }

            [$controller, $method] = $handler;
            $instance = is_string($controller) ? new $controller() : $controller;

            $reflection = new ReflectionMethod($instance, $method);
            $arguments = [];

            if ($reflection->getNumberOfParameters() >= 1) {
                $arguments[] = $request;
            }

            if ($reflection->getNumberOfParameters() >= 2) {
                $arguments[] = $authenticatedUser;
            }

            return $reflection->invokeArgs($instance, $arguments);
        }

        if (!is_callable($handler)) {
            throw new \InvalidArgumentException('El handler de ruta debe ser un callable o un array [Controller, Metodo].');
        }

        $reflection = new \ReflectionFunction($handler);
        $arguments = [];

        if ($reflection->getNumberOfParameters() >= 1) {
            $arguments[] = $request;
        }

        if ($reflection->getNumberOfParameters() >= 2) {
            $arguments[] = $authenticatedUser;
        }

        return $reflection->invokeArgs($arguments);
    }

    /**
     * @param mixed $response
     */
    private function sendResponse(mixed $response): void
    {
        if ($response === null) {
            return;
        }

        if (is_string($response)) {
            header('Content-Type: text/html; charset=utf-8');
            echo $response;
            return;
        }

        if (is_array($response) || is_object($response)) {
            $statusCode = 200;
            $payload = $response;

            if (is_array($response) && isset($response['status'])) {
                $statusCode = match ($response['status']) {
                    'success' => isset($response['created']) ? 201 : 200,
                    'unauthorized' => 401,
                    'forbidden' => 403,
                    'error' => 400,
                    default => 200,
                };
            }

            $this->sendJsonResponse($statusCode, $payload);
            return;
        }

        $this->sendJsonResponse(200, ['value' => $response]);
    }

    /**
     * @param array<mixed> $data
     */
    private function sendJsonResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
