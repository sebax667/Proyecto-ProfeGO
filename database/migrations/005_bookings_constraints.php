<?php

declare(strict_types=1);

return [
    'foreign_keys' => false,
    'up' => static function (PDO $pdo): void {
        $columns = $pdo->query('PRAGMA table_info(bookings)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $hasColumn = static fn (string $column): bool => in_array($column, $columns, true);
        $rows = $pdo->query('SELECT * FROM bookings ORDER BY created_at, id')->fetchAll(PDO::FETCH_ASSOC);

        $pdo->exec("CREATE TABLE bookings_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tutor_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            subject_id INTEGER NULL,
            level_id INTEGER NULL,
            starts_at TEXT NOT NULL,
            ends_at TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending'
                CHECK (status IN ('pending', 'confirmed', 'rejected', 'cancelled', 'completed')),
            hourly_rate REAL NOT NULL,
            total_price REAL NOT NULL,
            hourly_rate_cents INTEGER NOT NULL DEFAULT 0,
            total_price_cents INTEGER NOT NULL DEFAULT 0,
            title TEXT,
            notes TEXT,
            meeting_type TEXT NOT NULL DEFAULT 'video'
                CHECK (meeting_type IN ('video', 'presencial')),
            meeting_id TEXT,
            meeting_url TEXT,
            cancel_reason TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CHECK (ends_at > starts_at),
            FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id),
            FOREIGN KEY (level_id) REFERENCES education_levels(id)
        )");

        $knownStatuses = ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'];
        $rankedIds = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'pending');
            if (!in_array($status, $knownStatuses, true)) {
                $status = 'pending';
            }
            if (in_array($status, ['pending', 'confirmed'], true)) {
                $key = (string) $row['tutor_id'] . "\0" . (string) $row['starts_at'];
                $rankedIds[$key][] = (int) $row['id'];
            }
        }
        $cancelledDuplicates = [];
        foreach ($rankedIds as $ids) {
            if (count($ids) > 1) {
                $cancelledDuplicates = [...$cancelledDuplicates, ...array_slice($ids, 1)];
            }
        }
        $duplicateIdSet = array_fill_keys($cancelledDuplicates, true);

        $insert = $pdo->prepare("INSERT INTO bookings_new (
            id, tutor_id, student_id, subject_id, level_id, starts_at, ends_at, status,
            hourly_rate, total_price, hourly_rate_cents, total_price_cents, title, notes,
            meeting_type, meeting_id, meeting_url, cancel_reason, created_at, updated_at
        ) VALUES (
            :id, :tutor_id, :student_id, :subject_id, :level_id, :starts_at, :ends_at, :status,
            :hourly_rate, :total_price, :hourly_rate_cents, :total_price_cents, :title, :notes,
            :meeting_type, :meeting_id, :meeting_url, :cancel_reason, :created_at, :updated_at
        )");

        $unknownStatusCount = 0;
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'pending');
            if (!in_array($status, $knownStatuses, true)) {
                $status = 'pending';
                $unknownStatusCount++;
            }
            if (isset($duplicateIdSet[(int) $row['id']])) {
                $status = 'cancelled';
            }

            $startsAt = (string) $row['starts_at'];
            $endsAt = (string) $row['ends_at'];
            if ($endsAt <= $startsAt) {
                throw new RuntimeException(
                    'La migración 005 no puede conservar la reserva ' . (int) $row['id']
                    . ': ends_at debe ser posterior a starts_at.'
                );
            }

            $meetingType = (string) ($row['meeting_type'] ?? 'video');
            if (in_array(strtolower($meetingType), ['presential', 'hybrid', 'presencial'], true)) {
                $meetingType = 'presencial';
            } elseif ($meetingType !== 'video') {
                $meetingType = 'video';
            }

            $insert->execute([
                ':id' => (int) $row['id'],
                ':tutor_id' => (int) $row['tutor_id'],
                ':student_id' => (int) $row['student_id'],
                ':subject_id' => $hasColumn('subject_id') ? ($row['subject_id'] ?? null) : null,
                ':level_id' => $hasColumn('level_id') ? ($row['level_id'] ?? null) : null,
                ':starts_at' => $startsAt,
                ':ends_at' => $endsAt,
                ':status' => $status,
                ':hourly_rate' => $row['hourly_rate'],
                ':total_price' => $row['total_price'],
                ':hourly_rate_cents' => $hasColumn('hourly_rate_cents') ? (int) $row['hourly_rate_cents'] : 0,
                ':total_price_cents' => $hasColumn('total_price_cents') ? (int) $row['total_price_cents'] : 0,
                ':title' => $row['title'] ?? null,
                ':notes' => $row['notes'] ?? null,
                ':meeting_type' => $meetingType,
                ':meeting_id' => $row['meeting_id'] ?? null,
                ':meeting_url' => $row['meeting_url'] ?? null,
                ':cancel_reason' => isset($duplicateIdSet[(int) $row['id']])
                    ? 'duplicada (migración)'
                    : ($row['cancel_reason'] ?? null),
                ':created_at' => $row['created_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
                ':updated_at' => $hasColumn('updated_at') && !empty($row['updated_at'])
                    ? $row['updated_at']
                    : ($row['created_at'] ?? gmdate('Y-m-d\TH:i:s\Z')),
            ]);
        }

        foreach ([
            'trg_reviews_only_completed',
            'trg_reviews_update_rating',
            'trg_reviews_recalculate_update',
            'trg_reviews_recalculate_delete',
        ] as $trigger) {
            $pdo->exec('DROP TRIGGER IF EXISTS ' . $trigger);
        }
        $pdo->exec('DROP TABLE bookings');
        $pdo->exec('ALTER TABLE bookings_new RENAME TO bookings');
        $pdo->exec('CREATE INDEX ix_bookings_student ON bookings(student_id, starts_at)');
        $pdo->exec('CREATE INDEX ix_bookings_tutor ON bookings(tutor_id, status, starts_at)');
        $pdo->exec("CREATE UNIQUE INDEX ux_bookings_tutor_slot
            ON bookings(tutor_id, starts_at) WHERE status IN ('pending', 'confirmed')");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookings_tutor_status_starts
            ON bookings(tutor_id, status, starts_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bookings_student_starts
            ON bookings(student_id, starts_at)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_bookings_unique_pending_confirmed
            ON bookings(tutor_id, student_id, starts_at, ends_at)
            WHERE status IN ('pending', 'confirmed')");
        $pdo->exec("CREATE TRIGGER trg_bookings_no_self
            BEFORE INSERT ON bookings
            WHEN NEW.student_id = (SELECT user_id FROM tutor_profiles WHERE id = NEW.tutor_id)
            BEGIN
                SELECT RAISE(ABORT, 'No puedes reservar tus propias tutorías');
            END");

        if ($pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'reviews'")->fetchColumn()) {
            $reviewColumns = $pdo->query('PRAGMA table_info(reviews)')->fetchAll(PDO::FETCH_COLUMN, 1);
            $scoreColumn = in_array('score', $reviewColumns, true) ? 'score' : 'rating';
            $pdo->exec("CREATE TRIGGER trg_reviews_only_completed
                BEFORE INSERT ON reviews
                WHEN (SELECT status FROM bookings WHERE id = NEW.booking_id) IS NOT 'completed'
                BEGIN
                    SELECT RAISE(ABORT, 'Solo se puede calificar una tutoría completada');
                END");
            $pdo->exec("CREATE TRIGGER trg_reviews_update_rating
                AFTER INSERT ON reviews
                BEGIN
                    UPDATE tutor_profiles
                    SET reviews_count = (SELECT COUNT(*) FROM reviews WHERE tutor_id = NEW.tutor_id),
                        rating_avg = COALESCE((SELECT ROUND(AVG({$scoreColumn}), 2)
                            FROM reviews WHERE tutor_id = NEW.tutor_id), 0)
                    WHERE id = NEW.tutor_id;
                END");
            $pdo->exec("CREATE TRIGGER trg_reviews_recalculate_update
                AFTER UPDATE OF {$scoreColumn}, tutor_id ON reviews
                BEGIN
                    UPDATE tutor_profiles
                    SET reviews_count = (SELECT COUNT(*) FROM reviews WHERE tutor_id = OLD.tutor_id),
                        rating_avg = COALESCE((SELECT ROUND(AVG({$scoreColumn}), 2)
                            FROM reviews WHERE tutor_id = OLD.tutor_id), 0)
                    WHERE id = OLD.tutor_id;
                    UPDATE tutor_profiles
                    SET reviews_count = (SELECT COUNT(*) FROM reviews WHERE tutor_id = NEW.tutor_id),
                        rating_avg = COALESCE((SELECT ROUND(AVG({$scoreColumn}), 2)
                            FROM reviews WHERE tutor_id = NEW.tutor_id), 0)
                    WHERE id = NEW.tutor_id;
                END");
            $pdo->exec("CREATE TRIGGER trg_reviews_recalculate_delete
                AFTER DELETE ON reviews
                BEGIN
                    UPDATE tutor_profiles
                    SET reviews_count = (SELECT COUNT(*) FROM reviews WHERE tutor_id = OLD.tutor_id),
                        rating_avg = COALESCE((SELECT ROUND(AVG({$scoreColumn}), 2)
                            FROM reviews WHERE tutor_id = OLD.tutor_id), 0)
                    WHERE id = OLD.tutor_id;
                END");
        }

        error_log(sprintf(
            'Migración 005: %d estados desconocidos convertidos a pending; %d reservas duplicadas recientes canceladas.',
            $unknownStatusCount,
            count($cancelledDuplicates)
        ));
    },
];
