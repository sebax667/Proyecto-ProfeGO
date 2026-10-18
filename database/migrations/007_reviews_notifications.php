<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $reviewColumns = $pdo->query('PRAGMA table_info(reviews)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if ($reviewColumns === []) {
        $pdo->exec("CREATE TABLE reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            booking_id INTEGER NOT NULL UNIQUE,
            tutor_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            score INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
            comment TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $reviewColumns = ['id', 'booking_id', 'tutor_id', 'student_id', 'score', 'comment', 'created_at'];
    }
    $scoreColumn = in_array('score', $reviewColumns, true) ? 'score' : 'rating';
    if (!in_array($scoreColumn, $reviewColumns, true)) {
        throw new RuntimeException('La tabla reviews no contiene una columna score o rating reconocida.');
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS ix_reviews_tutor ON reviews(tutor_id)');
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS trg_reviews_only_completed
        BEFORE INSERT ON reviews
        WHEN (SELECT status FROM bookings WHERE id = NEW.booking_id) IS NOT 'completed'
        BEGIN
            SELECT RAISE(ABORT, 'Solo se puede calificar una tutoría completada');
        END");
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS trg_reviews_update_rating
        AFTER INSERT ON reviews
        BEGIN
            UPDATE tutor_profiles
            SET reviews_count = (SELECT COUNT(*) FROM reviews WHERE tutor_id = NEW.tutor_id),
                rating_avg = COALESCE((SELECT ROUND(AVG({$scoreColumn}), 2)
                    FROM reviews WHERE tutor_id = NEW.tutor_id), 0)
            WHERE id = NEW.tutor_id;
        END");
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS trg_reviews_recalculate_update
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
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS trg_reviews_recalculate_delete
        AFTER DELETE ON reviews
        BEGIN
            UPDATE tutor_profiles
            SET reviews_count = (SELECT COUNT(*) FROM reviews WHERE tutor_id = OLD.tutor_id),
                rating_avg = COALESCE((SELECT ROUND(AVG({$scoreColumn}), 2)
                    FROM reviews WHERE tutor_id = OLD.tutor_id), 0)
            WHERE id = OLD.tutor_id;
        END");

    $notificationColumns = $pdo->query('PRAGMA table_info(notifications)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if ($notificationColumns === []) {
        $pdo->exec("CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            booking_id INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
            channel TEXT NOT NULL DEFAULT 'email',
            subject TEXT,
            body TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            payload TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at TEXT
        )");
        $notificationColumns = ['id', 'status', 'created_at'];
    }

    foreach ([
        'user_id' => 'INTEGER REFERENCES users(id) ON DELETE CASCADE',
        'booking_id' => 'INTEGER REFERENCES bookings(id) ON DELETE SET NULL',
        'subject' => 'TEXT',
        'body' => 'TEXT',
    ] as $column => $definition) {
        if (!in_array($column, $notificationColumns, true)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN {$column} {$definition}");
        }
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS ix_notifications_pending ON notifications(status, created_at)');
};
