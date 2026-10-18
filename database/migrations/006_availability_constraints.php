<?php

declare(strict_types=1);

return [
    'foreign_keys' => false,
    'up' => static function (PDO $pdo): void {
        $tableSql = $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'tutor_availabilities'")
            ->fetchColumn();
        if ($tableSql === false) {
            return;
        }

        $hasIntervalCheck = preg_match('/CHECK\s*\(\s*end_at\s*>\s*start_at\s*\)/i', (string) $tableSql) === 1;
        if (!$hasIntervalCheck) {
            $rows = $pdo->query('SELECT * FROM tutor_availabilities ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $columns = $pdo->query('PRAGMA table_info(tutor_availabilities)')->fetchAll(PDO::FETCH_COLUMN, 1);
            $isActiveColumn = in_array('is_active', $columns, true);
            $createdAtColumn = in_array('created_at', $columns, true);
            $invalidCount = 0;

            $pdo->exec("CREATE TABLE tutor_availabilities_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tutor_id INTEGER NOT NULL,
                start_at TEXT NOT NULL,
                end_at TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK (end_at > start_at),
                FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE
            )");
            $insert = $pdo->prepare("INSERT INTO tutor_availabilities_new
                (id, tutor_id, start_at, end_at, is_active, created_at)
                VALUES (:id, :tutor_id, :start_at, :end_at, :is_active, :created_at)");
            foreach ($rows as $row) {
                $startAt = (string) $row['start_at'];
                $endAt = (string) $row['end_at'];
                $active = $isActiveColumn ? (int) $row['is_active'] : 1;
                if ($endAt <= $startAt) {
                    try {
                        $endAt = (new DateTimeImmutable($startAt))->modify('+1 second')->format('Y-m-d\TH:i:s\Z');
                    } catch (Throwable $exception) {
                        throw new RuntimeException(
                            'No se pudo normalizar la disponibilidad ' . (int) $row['id'] . '.',
                            previous: $exception
                        );
                    }
                    $active = 0;
                    $invalidCount++;
                }

                $insert->execute([
                    ':id' => (int) $row['id'],
                    ':tutor_id' => (int) $row['tutor_id'],
                    ':start_at' => $startAt,
                    ':end_at' => $endAt,
                    ':is_active' => $active,
                    ':created_at' => $createdAtColumn && !empty($row['created_at'])
                        ? $row['created_at']
                        : gmdate('Y-m-d\TH:i:s\Z'),
                ]);
            }

            $pdo->exec('DROP TABLE tutor_availabilities');
            $pdo->exec('ALTER TABLE tutor_availabilities_new RENAME TO tutor_availabilities');
            error_log(sprintf(
                'Migración 006: %d disponibilidades con intervalo inválido quedaron inactivas y con fin ajustado al segundo siguiente.',
                $invalidCount
            ));
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS ix_availability_tutor ON tutor_availabilities(tutor_id, start_at)');
    },
];
