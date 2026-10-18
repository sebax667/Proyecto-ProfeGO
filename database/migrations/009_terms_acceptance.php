<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('terms_accepted_at', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN terms_accepted_at TEXT NULL');
    }
    if (!in_array('terms_version', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN terms_version TEXT NULL');
    }
};
