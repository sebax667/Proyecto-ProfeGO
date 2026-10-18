<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Database\Database;

$databasePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'profego_terms_migration_' . bin2hex(random_bytes(8)) . '.sqlite';
$pdo = null;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        password TEXT NOT NULL
    )");
    $pdo->exec("INSERT INTO users (id, name, email, password)
        VALUES (41, 'Usuario existente', 'existing@example.test', 'hash-preservado')");
    $pdo->exec('CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('INSERT INTO schema_migrations (version) VALUES (1), (2), (3), (4), (5), (6), (7), (8)');
    $pdo->exec('PRAGMA user_version = 8');

    Database::migrate($pdo, [__DIR__ . '/../database/migrations/009_terms_acceptance.php'], force: true);
    $assert((int) $pdo->query('PRAGMA user_version')->fetchColumn() === 9, 'La migración no avanzó de la versión 8 a la 9.');

    $user = $pdo->query('SELECT id, name, email, password, terms_accepted_at, terms_version FROM users WHERE id = 41')
        ->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($user), 'Se perdió el usuario existente.');
    $assert(
        $user['name'] === 'Usuario existente'
            && $user['email'] === 'existing@example.test'
            && $user['password'] === 'hash-preservado',
        'La migración alteró los datos existentes del usuario.'
    );
    $assert($user['terms_accepted_at'] === null && $user['terms_version'] === null, 'La migración atribuyó una aceptación no realizada.');

    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    foreach (['terms_accepted_at', 'terms_version'] as $columnName) {
        $column = array_values(array_filter($columns, static fn (array $item): bool => $item['name'] === $columnName))[0] ?? null;
        $assert(is_array($column), 'No se creó la columna ' . $columnName . '.');
        $assert((int) $column['notnull'] === 0, 'La columna ' . $columnName . ' no es nullable.');
    }

    $migration = require __DIR__ . '/../database/migrations/009_terms_acceptance.php';
    $migration($pdo);
    $migration($pdo);
    $assert((int) $pdo->query('PRAGMA user_version')->fetchColumn() === 9, 'La repetición manual cambió la versión de la base.');
    $assert(
        (int) $pdo->query('SELECT COUNT(*) FROM users WHERE id = 41 AND password = \'hash-preservado\'')->fetchColumn() === 1,
        'La repetición de la migración alteró datos existentes.'
    );

    echo json_encode([
        'status' => 'ok',
        'message' => 'Migración 009 desde versión 8 preserva datos y es repetible.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    $pdo = null;
    foreach ([$databasePath, $databasePath . '-wal', $databasePath . '-shm'] as $temporaryFile) {
        if (is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
    }
}
