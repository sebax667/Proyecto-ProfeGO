<?php

declare(strict_types=1);

return [
    'foreign_keys' => false,
    'up' => static function (PDO $pdo): void {
        $slug = static function (string $value): string {
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            $value = strtolower((string) $normalized);
            $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

            return trim($value, '-');
        };
        $uniqueSlug = static function (string $name, string $table = 'subjects') use ($pdo, $slug): string {
            if (!in_array($table, ['subjects', 'education_levels'], true)) {
                throw new RuntimeException('Tabla de catálogo no válida para generar slug.');
            }
            $base = $slug($name);
            if ($base === '') {
                $base = 'materia';
            }
            $candidate = $base;
            $suffix = 0;
            $findSlug = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = :slug LIMIT 1");
            while (true) {
                $findSlug->execute([':slug' => $candidate]);
                if ($findSlug->fetchColumn() === false) {
                    return $candidate;
                }
                $candidate = $base . '-' . substr(hash('sha256', strtolower($name) . ':' . $suffix), 0, 8);
                $suffix++;
            }
        };

        $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS education_levels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $tutorSubjectColumns = $pdo->query('PRAGMA table_info(tutor_subjects)')
            ->fetchAll(PDO::FETCH_COLUMN, 1);
        if ($tutorSubjectColumns === []) {
            $pdo->exec("CREATE TABLE tutor_subjects (
                tutor_id INTEGER NOT NULL,
                subject_id INTEGER NOT NULL,
                level_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tutor_id, subject_id, level_id),
                FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
                FOREIGN KEY (level_id) REFERENCES education_levels(id) ON DELETE CASCADE
            )");
        } elseif (in_array('education_level_id', $tutorSubjectColumns, true)
            && !in_array('level_id', $tutorSubjectColumns, true)) {
            $pdo->exec("CREATE TABLE tutor_subjects_new (
                tutor_id INTEGER NOT NULL,
                subject_id INTEGER NOT NULL,
                level_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tutor_id, subject_id, level_id),
                FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
                FOREIGN KEY (level_id) REFERENCES education_levels(id) ON DELETE CASCADE
            )");
            $pdo->exec("INSERT OR IGNORE INTO tutor_subjects_new (tutor_id, subject_id, level_id, created_at)
                SELECT tutor_id, subject_id, education_level_id, created_at FROM tutor_subjects");
            $pdo->exec('DROP TABLE tutor_subjects');
            $pdo->exec('ALTER TABLE tutor_subjects_new RENAME TO tutor_subjects');
        }

        $tutorSubjectColumns = $pdo->query('PRAGMA table_info(tutor_subjects)')
            ->fetchAll(PDO::FETCH_COLUMN, 1);
        $linkLevelColumn = in_array('level_id', $tutorSubjectColumns, true) ? 'level_id' : 'education_level_id';
        $mergeCatalogDuplicates = static function (string $table, string $linkColumn, string $linkLevelColumn) use ($pdo): void {
            $canonicalIds = [];
            $rows = $pdo->query("SELECT id, name FROM {$table} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = strtolower(trim((string) $row['name']));
                if (!isset($canonicalIds[$key])) {
                    $canonicalIds[$key] = (int) $row['id'];
                    continue;
                }

                $canonicalId = $canonicalIds[$key];
                $duplicateId = (int) $row['id'];
                if ($linkColumn === 'subject_id') {
                    $pdo->prepare("INSERT OR IGNORE INTO tutor_subjects (tutor_id, subject_id, {$linkLevelColumn}, created_at)
                        SELECT tutor_id, :canonical, {$linkLevelColumn}, created_at
                        FROM tutor_subjects WHERE subject_id = :duplicate")
                        ->execute([':canonical' => $canonicalId, ':duplicate' => $duplicateId]);
                } else {
                    $pdo->prepare("INSERT OR IGNORE INTO tutor_subjects (tutor_id, subject_id, {$linkLevelColumn}, created_at)
                        SELECT tutor_id, subject_id, :canonical, created_at
                        FROM tutor_subjects WHERE {$linkColumn} = :duplicate")
                        ->execute([':canonical' => $canonicalId, ':duplicate' => $duplicateId]);
                }
                $pdo->prepare("DELETE FROM tutor_subjects WHERE {$linkColumn} = :duplicate")
                    ->execute([':duplicate' => $duplicateId]);

                if ($linkColumn === 'subject_id') {
                    $bookingColumns = $pdo->query('PRAGMA table_info(bookings)')->fetchAll(PDO::FETCH_COLUMN, 1);
                    if (in_array('subject_id', $bookingColumns, true)) {
                        $pdo->prepare('UPDATE bookings SET subject_id = :canonical WHERE subject_id = :duplicate')
                            ->execute([':canonical' => $canonicalId, ':duplicate' => $duplicateId]);
                    }
                } else {
                    $bookingColumns = $pdo->query('PRAGMA table_info(bookings)')->fetchAll(PDO::FETCH_COLUMN, 1);
                    if (in_array('level_id', $bookingColumns, true)) {
                        $pdo->prepare('UPDATE bookings SET level_id = :canonical WHERE level_id = :duplicate')
                            ->execute([':canonical' => $canonicalId, ':duplicate' => $duplicateId]);
                    }
                }

                $pdo->prepare("DELETE FROM {$table} WHERE id = :id")->execute([':id' => $duplicateId]);
            }
        };
        $mergeCatalogDuplicates('subjects', 'subject_id', $linkLevelColumn);
        $mergeCatalogDuplicates('education_levels', $linkLevelColumn, $linkLevelColumn);
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_subjects_name_nocase ON subjects(name COLLATE NOCASE)');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_education_levels_name_nocase ON education_levels(name COLLATE NOCASE)');

        $subjectNames = ['Matemáticas', 'Física', 'Química', 'Inglés', 'Programación', 'Estadística'];
        $levelNames = ['Primaria', 'Bachillerato', 'Universidad', 'Posgrado', 'General'];
        $insertSubject = $pdo->prepare(
            'INSERT INTO subjects (name, slug) SELECT :name, :slug
             WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = :lookup COLLATE NOCASE)'
        );
        foreach ($subjectNames as $name) {
            $insertSubject->execute([
                ':name' => $name,
                ':slug' => $uniqueSlug($name),
                ':lookup' => $name,
            ]);
        }

        $insertLevel = $pdo->prepare(
            'INSERT INTO education_levels (name, slug) SELECT :name, :slug
             WHERE NOT EXISTS (SELECT 1 FROM education_levels WHERE name = :lookup COLLATE NOCASE)'
        );
        foreach ($levelNames as $name) {
            $insertLevel->execute([
                ':name' => $name,
                ':slug' => $uniqueSlug($name, 'education_levels'),
                ':lookup' => $name,
            ]);
        }

        $generalLevel = $pdo->prepare('SELECT id FROM education_levels WHERE name = :name COLLATE NOCASE LIMIT 1');
        $generalLevel->execute([':name' => 'General']);
        $generalLevelId = (int) $generalLevel->fetchColumn();

        $tutors = $pdo->query('SELECT id, subjects FROM tutor_profiles')->fetchAll(PDO::FETCH_ASSOC);
        $findSubject = $pdo->prepare('SELECT id FROM subjects WHERE name = :name COLLATE NOCASE LIMIT 1');
        $insertMissingSubject = $pdo->prepare(
            'INSERT INTO subjects (name, slug) VALUES (:name, :slug)'
        );
        $insertLink = $pdo->prepare(
            'INSERT OR IGNORE INTO tutor_subjects (tutor_id, subject_id, level_id)
             VALUES (:tutor_id, :subject_id, :level_id)'
        );

        foreach ($tutors as $tutor) {
            // tutor_profiles.subjects se conserva por compatibilidad histórica; es obsoleta
            // y las nuevas lecturas/escrituras deben usar tutor_subjects.
            $legacySubjects = json_decode((string) ($tutor['subjects'] ?? '[]'), true);
            if (!is_array($legacySubjects)) {
                continue;
            }

            foreach ($legacySubjects as $subjectName) {
                if (!is_string($subjectName) || trim($subjectName) === '') {
                    continue;
                }

                $subjectName = trim($subjectName);
                $findSubject->execute([':name' => $subjectName]);
                $subjectId = $findSubject->fetchColumn();
                if ($subjectId === false) {
                    $insertMissingSubject->execute([
                        ':name' => $subjectName,
                        ':slug' => $uniqueSlug($subjectName),
                    ]);
                    $subjectId = $pdo->lastInsertId();
                }

                $insertLink->execute([
                    ':tutor_id' => (int) $tutor['id'],
                    ':subject_id' => (int) $subjectId,
                    ':level_id' => $generalLevelId,
                ]);
            }
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS ix_tutor_subjects_search ON tutor_subjects(subject_id, level_id)');
    },
];
