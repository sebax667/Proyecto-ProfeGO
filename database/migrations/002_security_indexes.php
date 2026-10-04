<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = $pdo->query("PRAGMA table_info(bookings)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('hourly_rate_cents', $columns, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN hourly_rate_cents INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('total_price_cents', $columns, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN total_price_cents INTEGER NOT NULL DEFAULT 0');
    }

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookings_tutor_status_starts ON bookings(tutor_id, status, starts_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookings_student_starts ON bookings(student_id, starts_at)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_bookings_unique_pending_confirmed
        ON bookings(tutor_id, student_id, starts_at, ends_at)
        WHERE status IN ('pending', 'confirmed')");

    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version INTEGER NOT NULL PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
};
