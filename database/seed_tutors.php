<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Shared\Database\Database;

$force = in_array('--force', $argv, true);
if (strtolower((string) getenv('APP_ENV')) === 'production' && !$force) {
    fwrite(STDOUT, "Seed omitido en production; usa --force solo con autorización explícita.\n");
    exit(0);
}

try {
    $pdo = Database::getConnection();
    $tutors = [
        [
            'name' => 'Tutor Demo 1',
            'email' => 'tutor1@example.test',
            'headline' => 'Tutor de programación',
            'bio' => 'Perfil de ejemplo para pruebas locales.',
            'hourly_rate' => 45000,
            'modality' => 'virtual',
            'city' => 'Ciudad Demo',
            'subjects' => ['Python', 'C++', 'SQL', 'C#'],
        ],
        [
            'name' => 'Tutor Demo 2',
            'email' => 'tutor2@example.test',
            'headline' => 'Tutor de inglés',
            'bio' => 'Perfil de ejemplo para pruebas locales.',
            'hourly_rate' => 35000,
            'modality' => 'hybrid',
            'city' => 'Ciudad Demo',
            'subjects' => ['Inglés', 'Conversación', 'Gramática'],
        ],
        [
            'name' => 'Tutor Demo 3',
            'email' => 'tutor3@example.test',
            'headline' => 'Tutor de ciencias',
            'bio' => 'Perfil de ejemplo para pruebas locales.',
            'hourly_rate' => 50000,
            'modality' => 'presential',
            'city' => 'Ciudad Demo',
            'subjects' => ['Arduino', 'Circuitos', 'Física'],
        ],
    ];

    $pdo->beginTransaction();
    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = :email COLLATE NOCASE LIMIT 1');
    $insertUser = $pdo->prepare(
        "INSERT INTO users (name, email, password, role, is_active)
         VALUES (:name, :email, :password, 'tutor', 1)"
    );
    $findProfile = $pdo->prepare('SELECT id FROM tutor_profiles WHERE user_id = :user_id LIMIT 1');
    $insertProfile = $pdo->prepare(
        "INSERT INTO tutor_profiles
            (user_id, headline, bio, hourly_rate, rating_avg, reviews_count, modality, city, subjects)
         VALUES (:user_id, :headline, :bio, :hourly_rate, 0, 0, :modality, :city, '[]')"
    );
    $findSubject = $pdo->prepare('SELECT id FROM subjects WHERE name = :name COLLATE NOCASE LIMIT 1');
    $insertSubject = $pdo->prepare('INSERT INTO subjects (name, slug) VALUES (:name, :slug)');
    $slugForSubject = static function (string $subject) use ($pdo): string {
        $base = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $subject));
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');
        if ($base === '') {
            $base = 'materia';
        }
        $candidate = $base;
        $suffix = 0;
        $findSlug = $pdo->prepare('SELECT 1 FROM subjects WHERE slug = :slug LIMIT 1');
        while (true) {
            $findSlug->execute([':slug' => $candidate]);
            if ($findSlug->fetchColumn() === false) {
                return $candidate;
            }
            $candidate = $base . '-' . substr(hash('sha256', strtolower($subject) . ':' . $suffix), 0, 8);
            $suffix++;
        }
    };
    $findGeneral = $pdo->prepare('SELECT id FROM education_levels WHERE name = :name COLLATE NOCASE LIMIT 1');
    $findLink = $pdo->prepare(
        'SELECT 1 FROM tutor_subjects WHERE tutor_id = :tutor_id AND subject_id = :subject_id AND level_id = :level_id'
    );
    $insertLink = $pdo->prepare(
        'INSERT OR IGNORE INTO tutor_subjects (tutor_id, subject_id, level_id)
         VALUES (:tutor_id, :subject_id, :level_id)'
    );
    $findGeneral->execute([':name' => 'General']);
    $generalId = (int) $findGeneral->fetchColumn();

    foreach ($tutors as $tutor) {
        $findUser->execute([':email' => $tutor['email']]);
        $userId = $findUser->fetchColumn();
        if ($userId === false) {
            $insertUser->execute([
                ':name' => $tutor['name'],
                ':email' => $tutor['email'],
                ':password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID),
            ]);
            $userId = (int) $pdo->lastInsertId();
        } else {
            $userId = (int) $userId;
        }

        $findProfile->execute([':user_id' => $userId]);
        $tutorId = $findProfile->fetchColumn();
        if ($tutorId === false) {
            $insertProfile->execute([
                ':user_id' => $userId,
                ':headline' => $tutor['headline'],
                ':bio' => $tutor['bio'],
                ':hourly_rate' => $tutor['hourly_rate'],
                ':modality' => $tutor['modality'],
                ':city' => $tutor['city'],
            ]);
            $tutorId = (int) $pdo->lastInsertId();
        } else {
            $tutorId = (int) $tutorId;
        }

        foreach ($tutor['subjects'] as $subject) {
            $findSubject->execute([':name' => $subject]);
            $subjectId = $findSubject->fetchColumn();
            if ($subjectId === false) {
                $insertSubject->execute([':name' => $subject, ':slug' => $slugForSubject($subject)]);
                $subjectId = (int) $pdo->lastInsertId();
            } else {
                $subjectId = (int) $subjectId;
            }

            $findLink->execute([
                ':tutor_id' => $tutorId,
                ':subject_id' => $subjectId,
                ':level_id' => $generalId,
            ]);
            if ($findLink->fetchColumn() === false) {
                $insertLink->execute([
                    ':tutor_id' => $tutorId,
                    ':subject_id' => $subjectId,
                    ':level_id' => $generalId,
                ]);
            }
        }
    }

    $pdo->commit();
    fwrite(STDOUT, "Seed completado sin eliminar tutores ni sus relaciones.\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Error al sembrar tutores: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
