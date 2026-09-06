<?php

declare(strict_types=1);

if (!function_exists('request')) {
    function request(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_REQUEST;
        }

        return $_REQUEST[$key] ?? $default;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): object
    {
        $viewFile = __DIR__ . '/../../resources/views/' . str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("View not found: {$template} ({$viewFile})");
        }

        return new class($viewFile, $data) {
            public function __construct(
                private string $viewFile,
                private array $data
            ) {}

            public function render(): string
            {
                extract($this->data, EXTR_SKIP);
                ob_start();
                include $this->viewFile;
                return (string) ob_get_clean();
            }
        };
    }
}
