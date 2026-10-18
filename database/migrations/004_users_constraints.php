<?php

declare(strict_types=1);

return [
    'foreign_keys' => false,
    'up' => static function (PDO $pdo): void {
        $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
        $hasColumn = static fn (string $column): bool => in_array($column, $columns, true);
        $rows = $pdo->query('SELECT id, email, created_at FROM users ORDER BY created_at, id')
            ->fetchAll(PDO::FETCH_ASSOC);
        $canonicalByEmail = [];
        $duplicateCount = 0;
        $roleCount = 0;

        foreach ($rows as $row) {
            $email = strtolower(trim((string) $row['email']));
            $key = strtolower($email);
            if (!isset($canonicalByEmail[$key])) {
                $canonicalByEmail[$key] = (int) $row['id'];
                continue;
            }

            $canonicalId = $canonicalByEmail[$key];
            $duplicateId = (int) $row['id'];
            $canonicalProfile = $pdo->prepare('SELECT id FROM tutor_profiles WHERE user_id = :user_id');
            $canonicalProfile->execute([':user_id' => $canonicalId]);
            $canonicalTutorId = $canonicalProfile->fetchColumn();
            $duplicateProfile = $pdo->prepare('SELECT id FROM tutor_profiles WHERE user_id = :user_id');
            $duplicateProfile->execute([':user_id' => $duplicateId]);
            $duplicateTutorId = $duplicateProfile->fetchColumn();

            if ($duplicateTutorId !== false && $canonicalTutorId === false) {
                $pdo->prepare('UPDATE tutor_profiles SET user_id = :canonical WHERE id = :duplicate')
                    ->execute([':canonical' => $canonicalId, ':duplicate' => (int) $duplicateTutorId]);
            } elseif ($duplicateTutorId !== false && $canonicalTutorId !== false) {
                $canonicalTutorId = (int) $canonicalTutorId;
                $duplicateTutorId = (int) $duplicateTutorId;
                $pdo->prepare('UPDATE tutor_availabilities SET tutor_id = :canonical WHERE tutor_id = :duplicate')
                    ->execute([':canonical' => $canonicalTutorId, ':duplicate' => $duplicateTutorId]);
                $pdo->prepare('UPDATE bookings SET tutor_id = :canonical WHERE tutor_id = :duplicate')
                    ->execute([':canonical' => $canonicalTutorId, ':duplicate' => $duplicateTutorId]);
                $pdo->prepare('UPDATE reviews SET tutor_id = :canonical WHERE tutor_id = :duplicate')
                    ->execute([':canonical' => $canonicalTutorId, ':duplicate' => $duplicateTutorId]);
                $linkColumns = $pdo->query('PRAGMA table_info(tutor_subjects)')->fetchAll(PDO::FETCH_COLUMN, 1);
                if ($linkColumns !== []) {
                    $levelColumn = in_array('level_id', $linkColumns, true) ? 'level_id' : 'education_level_id';
                    $mergeLinks = $pdo->prepare("INSERT OR IGNORE INTO tutor_subjects (tutor_id, subject_id, {$levelColumn}, created_at)
                        SELECT :canonical_tutor, subject_id, {$levelColumn}, created_at
                        FROM tutor_subjects WHERE tutor_id = :duplicate_tutor");
                    $mergeLinks->execute([
                        ':canonical_tutor' => $canonicalTutorId,
                        ':duplicate_tutor' => $duplicateTutorId,
                    ]);
                    $pdo->prepare('DELETE FROM tutor_subjects WHERE tutor_id = :duplicate')
                        ->execute([':duplicate' => $duplicateTutorId]);
                }
                $pdo->prepare('DELETE FROM tutor_profiles WHERE id = :id')
                    ->execute([':id' => $duplicateTutorId]);
            }

            foreach (['bookings', 'reviews'] as $table) {
                $pdo->prepare("UPDATE {$table} SET student_id = :canonical WHERE student_id = :duplicate")
                    ->execute([':canonical' => $canonicalId, ':duplicate' => $duplicateId]);
            }
            if ($pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'auth_sessions'")->fetchColumn()) {
                $pdo->prepare('UPDATE auth_sessions SET user_id = :canonical WHERE user_id = :duplicate')
                    ->execute([':canonical' => $canonicalId, ':duplicate' => $duplicateId]);
            }

            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $duplicateId]);
            $pdo->prepare('UPDATE users SET email = :email WHERE id = :id')
                ->execute([':email' => $email, ':id' => $canonicalId]);
            $duplicateCount++;
        }

        foreach ($canonicalByEmail as $key => $id) {
            $email = '';
            foreach ($rows as $row) {
                if (strtolower(trim((string) $row['email'])) === $key) {
                    $email = strtolower(trim((string) $row['email']));
                    break;
                }
            }
            $pdo->prepare('UPDATE users SET email = :email WHERE id = :id')
                ->execute([':email' => $email, ':id' => $id]);
        }

        foreach ($pdo->query('SELECT id, role FROM users')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!in_array($row['role'], ['student', 'tutor', 'admin'], true)) {
                $pdo->prepare("UPDATE users SET role = 'student' WHERE id = :id")->execute([':id' => (int) $row['id']]);
                $roleCount++;
            }
        }

        $isActive = $hasColumn('is_active') ? 'COALESCE(is_active, 1)' : '1';
        $updatedAt = $hasColumn('updated_at') ? 'COALESCE(updated_at, CURRENT_TIMESTAMP)' : 'CURRENT_TIMESTAMP';
        $avatar = $hasColumn('avatar_url') ? 'avatar_url' : 'NULL';
        $phone = $hasColumn('phone') ? 'phone' : 'NULL';
        $createdAt = $hasColumn('created_at') ? 'COALESCE(created_at, CURRENT_TIMESTAMP)' : 'CURRENT_TIMESTAMP';

        $pdo->exec("CREATE TABLE users_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL COLLATE NOCASE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student', 'tutor', 'admin')),
            avatar_url TEXT,
            phone TEXT,
            is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("INSERT INTO users_new
            (id, name, email, password, role, avatar_url, phone, is_active, created_at, updated_at)
            SELECT id, name, LOWER(TRIM(email)), password, role, {$avatar}, {$phone},
                {$isActive}, {$createdAt}, {$updatedAt} FROM users");
        $pdo->exec('DROP TABLE users');
        $pdo->exec('ALTER TABLE users_new RENAME TO users');
        $pdo->exec('CREATE UNIQUE INDEX ux_users_email_nocase ON users(email COLLATE NOCASE)');

        if ($duplicateCount > 0 || $roleCount > 0) {
            error_log(sprintf(
                'Migración 004: se conservaron las cuentas más antiguas y se consolidaron %d emails duplicados; %d roles desconocidos pasaron a student.',
                $duplicateCount,
                $roleCount
            ));
        }
    },
];
