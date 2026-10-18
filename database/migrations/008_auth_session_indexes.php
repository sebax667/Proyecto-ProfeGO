<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        jti TEXT NOT NULL UNIQUE,
        user_id INTEGER NOT NULL,
        expires_at TEXT NOT NULL,
        revoked_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL COLLATE NOCASE,
        ip TEXT NOT NULL,
        succeeded INTEGER NOT NULL DEFAULT 0 CHECK (succeeded IN (0, 1)),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $sessionColumns = $pdo->query('PRAGMA table_info(auth_sessions)')->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach ([
        'ip' => 'TEXT',
        'user_agent' => 'TEXT',
    ] as $column => $definition) {
        if (!in_array($column, $sessionColumns, true)) {
            $pdo->exec("ALTER TABLE auth_sessions ADD COLUMN {$column} {$definition}");
        }
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS ix_sessions_user ON auth_sessions(user_id, revoked_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS ix_attempts_email_ip ON login_attempts(email, ip, created_at)');
};
