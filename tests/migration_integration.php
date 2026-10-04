<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Database\Database;

putenv('DB_PATH=:memory:');
$pdo = Database::getConnection();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
$assert($version === 2, 'La versión de esquema no coincide con las migraciones instaladas.');

$appliedVersions = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
$assert(array_map('intval', $appliedVersions) === [1, 2], 'El historial de migraciones no registra las versiones aplicadas.');

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['subjects', 'education_levels', 'tutor_subjects', 'reviews', 'notifications', 'auth_sessions', 'login_attempts'] as $table) {
    $assert(in_array($table, $tables, true), 'Falta la tabla requerida: ' . $table);
}

foreach (glob(__DIR__ . '/../database/migrations/*.php') ?: [] as $file) {
    $migration = require $file;
    $migration($pdo);
}

$versionsAfterReplay = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
$assert(array_map('intval', $versionsAfterReplay) === [1, 2], 'La repetición de migraciones duplicó el historial.');

$bookingColumns = $pdo->query('PRAGMA table_info(bookings)')->fetchAll(PDO::FETCH_COLUMN, 1);
$assert(in_array('hourly_rate_cents', $bookingColumns, true), 'No se creó hourly_rate_cents.');
$assert(in_array('total_price_cents', $bookingColumns, true), 'No se creó total_price_cents.');

$indexes = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index'")->fetchAll(PDO::FETCH_COLUMN);
$assert(in_array('idx_bookings_tutor_status_starts', $indexes, true), 'No se creó el índice de búsquedas por tutor.');
$assert(in_array('idx_bookings_student_starts', $indexes, true), 'No se creó el índice de búsquedas por estudiante.');

$pdo = null;
echo json_encode([
    'status' => 'ok',
    'message' => 'Migration integration OK',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
