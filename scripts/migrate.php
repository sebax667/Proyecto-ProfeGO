<?php

declare(strict_types=1);

use App\Shared\Config\Environment;
use App\Shared\Database\Database;

if (PHP_SAPI !== 'cli') {
    file_put_contents('php://stderr', "Este script solo puede ejecutarse desde la línea de comandos.\n");
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

try {
    Environment::loadFromFile(dirname(__DIR__) . '/.env');

    $arguments = array_slice($argv, 1);
    if (count($arguments) > 1 || ($arguments !== [] && $arguments[0] !== '--status')) {
        fwrite(STDERR, "Uso: php scripts/migrate.php [--status]\n");
        exit(2);
    }
    $statusOnly = $arguments === ['--status'];
    $configuredPath = Database::getDatabasePath();
    $absolutePath = $configuredPath;
    if (!str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
        && preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) !== 1) {
        $absolutePath = getcwd() . DIRECTORY_SEPARATOR . $configuredPath;
    }
    $realPath = realpath($absolutePath);
    if ($realPath === false) {
        $parent = realpath(dirname($absolutePath));
        $realPath = ($parent === false ? dirname($absolutePath) : $parent)
            . DIRECTORY_SEPARATOR . basename($absolutePath);
    }
    fwrite(STDOUT, 'Base de datos: ' . $realPath . PHP_EOL);

    $files = Database::migrationFiles();
    if ($statusOnly) {
        if (is_file($absolutePath)) {
            $readOnlyPath = str_replace('\\', '/', $absolutePath);
            $pdo = new PDO('sqlite:file:' . $readOnlyPath . '?mode=ro');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } else {
            $pdo = new PDO('sqlite::memory:');
        }
        $status = Database::migrationStatus($pdo, $files);
        fwrite(STDOUT, 'user_version: ' . $status['version'] . PHP_EOL);
        fwrite(STDOUT, 'versión máxima: ' . $status['maximum_version'] . PHP_EOL);
        fwrite(STDOUT, 'migraciones pendientes: '
            . ($status['pending'] === [] ? 'ninguna' : implode(', ', $status['pending'])) . PHP_EOL);
        exit(0);
    }

    $pdo = Database::getConnection(forceMigrate: true);
    $status = Database::migrationStatus($pdo, $files);
    $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    $foreignKeyViolations = $pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);

    fwrite(STDOUT, 'user_version: ' . $status['version'] . PHP_EOL);
    fwrite(STDOUT, 'integrity_check: ' . $integrity . PHP_EOL);
    fwrite(STDOUT, 'foreign_key_check: '
        . ($foreignKeyViolations === [] ? 'sin errores' : json_encode($foreignKeyViolations, JSON_UNESCAPED_UNICODE))
        . PHP_EOL);

    if ($status['version'] !== $status['maximum_version']
        || $status['pending'] !== []
        || $integrity !== 'ok'
        || $foreignKeyViolations !== []) {
        exit(1);
    }
} catch (Throwable $exception) {
    error_log('Error en scripts/migrate.php: ' . $exception->getMessage());
    fwrite(STDERR, "No se pudo completar la operación de migración.\n");
    exit(1);
}
