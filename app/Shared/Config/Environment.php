<?php

declare(strict_types=1);

namespace App\Shared\Config;

final class Environment
{
    public static function loadFromFile(string $filePath): void
    {
        if (!is_file($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException('No se pudo leer el archivo de entorno.');
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
        }
    }
}
