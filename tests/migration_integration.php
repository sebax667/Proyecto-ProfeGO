<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Bookings\DTOs\CreateBookingDTO;
use App\Modules\Bookings\Exceptions\BookingException;
use App\Modules\Bookings\Repositories\BookingRepository;
use App\Modules\Bookings\Services\BookingService;
use App\Modules\Integrations\Adapters\MockVideoAdapter;
use App\Modules\SearchReputation\DTOs\SearchFiltersDTO;
use App\Modules\SearchReputation\Repositories\SearchTutorRepository;
use App\Shared\Database\Database;
use App\Shared\Middleware\AuthMiddleware;
use App\Shared\Security\JwtService;

$dbPath = sys_get_temp_dir() . '/profego_migration_' . bin2hex(random_bytes(8)) . '.sqlite';
putenv('DB_PATH=' . $dbPath);
putenv('APP_ENV=testing');
putenv('JWT_SECRET=' . bin2hex(random_bytes(32)));
register_shutdown_function(static function () use ($dbPath): void {
    unset(
        $GLOBALS['pdo'],
        $GLOBALS['authService'],
        $GLOBALS['bookingService'],
        $GLOBALS['searchRepository'],
        $GLOBALS['insertReview'],
        $GLOBALS['insertLegacyUser']
    );
    Database::closeConnection();
    gc_collect_cycles();
    $cleanupFailures = [];
    foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $file) {
        if (is_file($file) && !unlink($file)) {
            $cleanupFailures[] = $file;
        }
    }
    if ($cleanupFailures !== []) {
        fwrite(STDERR, 'No se pudieron eliminar las bases temporales: ' . implode(', ', $cleanupFailures) . PHP_EOL);
        exit(1);
    }
});

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assertRejected = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (PDOException) {
        return;
    }
    $assert(false, $message);
};

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL,
    password TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'student', avatar_url TEXT,
    phone TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE tutor_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL UNIQUE,
    headline TEXT NOT NULL, bio TEXT DEFAULT '', hourly_rate REAL NOT NULL DEFAULT 0,
    rating_avg REAL NOT NULL DEFAULT 0, reviews_count INTEGER NOT NULL DEFAULT 0,
    modality TEXT NOT NULL DEFAULT 'virtual', city TEXT, subjects TEXT DEFAULT '[]',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE tutor_availabilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT, tutor_id INTEGER NOT NULL,
    start_at TEXT NOT NULL, end_at TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT, tutor_id INTEGER NOT NULL, student_id INTEGER NOT NULL,
    starts_at TEXT NOT NULL, ends_at TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending',
    hourly_rate REAL NOT NULL, total_price REAL NOT NULL, hourly_rate_cents INTEGER NOT NULL DEFAULT 0,
    total_price_cents INTEGER NOT NULL DEFAULT 0, title TEXT, notes TEXT, meeting_type TEXT DEFAULT 'video',
    meeting_id TEXT, meeting_url TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE UNIQUE INDEX ux_bookings_unique_pending_confirmed
    ON bookings(tutor_id, student_id, starts_at, ends_at)
    WHERE status IN ('pending', 'confirmed')");
$pdo->exec("CREATE TABLE subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE education_levels (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE tutor_subjects (
    tutor_id INTEGER NOT NULL, subject_id INTEGER NOT NULL, education_level_id INTEGER NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_id, subject_id, education_level_id),
    FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (education_level_id) REFERENCES education_levels(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL UNIQUE,
    tutor_id INTEGER NOT NULL, student_id INTEGER NOT NULL, score INTEGER NOT NULL,
    comment TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT, channel TEXT NOT NULL DEFAULT 'email',
    status TEXT NOT NULL DEFAULT 'pending', payload TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    sent_at TEXT
)");
$pdo->exec("CREATE TABLE auth_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, jti TEXT NOT NULL UNIQUE, user_id INTEGER NOT NULL,
    expires_at TEXT NOT NULL, revoked_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, ip TEXT NOT NULL,
    succeeded INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec('CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec('DROP INDEX IF EXISTS ux_users_email_nocase');
$testPassword = bin2hex(random_bytes(16));
$passwordHash = password_hash($testPassword, PASSWORD_ARGON2ID);
$insertLegacyUser = $pdo->prepare(
    'INSERT INTO users (id, name, email, password, role, created_at)
     VALUES (:id, :name, :email, :password, :role, :created_at)'
);
foreach ([
    [1, 'Tutor Demo', 'Tutor@Example.test', 'hash-tutor', 'tutor', '2025-01-01T00:00:00Z'],
    [2, 'Estudiante Demo', 'Student@Example.test', $passwordHash, 'student', '2025-01-02T00:00:00Z'],
    [3, 'Duplicado Demo', 'student@example.test', 'hash-duplicate', 'student', '2025-01-03T00:00:00Z'],
    [4, 'Otro Demo', 'other@example.test', 'hash-other', 'student', '2025-01-04T00:00:00Z'],
] as [$id, $name, $email, $password, $role, $createdAt]) {
    $insertLegacyUser->execute([
        ':id' => $id,
        ':name' => $name,
        ':email' => $email,
        ':password' => $password,
        ':role' => $role,
        ':created_at' => $createdAt,
    ]);
}
$pdo->exec("INSERT INTO tutor_profiles (id, user_id, headline, hourly_rate, subjects)
    VALUES (1, 1, 'Tutor de prueba', 50, '[\"Matemáticas\", \"Rust\", \"matemáticas\", \"C++\", \"C#\"]')");
$pdo->exec("INSERT INTO subjects (id, name, slug) VALUES (1, 'Matemáticas', 'matematicas')");
$pdo->exec("INSERT INTO education_levels (id, name, slug) VALUES (1, 'Primaria', 'primaria')");
$pdo->exec('INSERT INTO tutor_subjects (tutor_id, subject_id, education_level_id) VALUES (1, 1, 1)');
$pdo->exec("INSERT INTO tutor_availabilities (id, tutor_id, start_at, end_at, is_active) VALUES
    (1, 1, '2030-01-01T10:00:00Z', '2030-01-01T11:00:00Z', 1),
    (2, 1, '2030-01-02T11:00:00Z', '2030-01-02T10:00:00Z', 1)");
$pdo->exec("INSERT INTO bookings (
    id, tutor_id, student_id, starts_at, ends_at, status, hourly_rate, total_price, created_at
) VALUES
    (1, 1, 2, '2030-02-01T10:00:00Z', '2030-02-01T11:00:00Z', 'pending', 50, 50, '2025-01-01T00:00:00Z'),
    (2, 1, 3, '2030-02-01T10:00:00Z', '2030-02-01T12:00:00Z', 'confirmed', 50, 100, '2025-01-02T00:00:00Z'),
    (3, 1, 2, '2030-02-02T10:00:00Z', '2030-02-02T11:00:00Z', 'mystery', 50, 50, '2025-01-03T00:00:00Z'),
    (4, 1, 2, '2030-02-03T10:00:00Z', '2030-02-03T11:00:00Z', 'completed', 50, 50, '2025-01-04T00:00:00Z')");
$pdo->exec('INSERT INTO schema_migrations (version) VALUES (1), (2)');
$pdo->exec('PRAGMA user_version = 2');
$insertReview = null;
$insertLegacyUser = null;
$searchRepository = null;
$bookingService = null;
$authService = null;
$pdo = null;
Database::closeConnection();

$pdo = Database::getConnection();
$version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
$assert($version === 9, 'La versión final de migración no es 9.');
$appliedVersions = array_map(
    'intval',
    $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN)
);
$assert($appliedVersions === range(1, 9), 'El historial no incluye todas las migraciones.');

$users = $pdo->query('SELECT id, email, password FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$assert(count($users) === 3, 'No se consolidaron los correos duplicados.');
$assert(array_map('intval', array_column($users, 'id')) === [1, 2, 4], 'Cambió el ID de una cuenta no duplicada.');
$assert($users[1]['id'] === 2 && $users[1]['email'] === 'student@example.test', 'No se conservó la cuenta más antigua normalizada.');
$assert($users[1]['password'] === $passwordHash, 'La contraseña del usuario conservado cambió.');
$assert((int) $pdo->query('SELECT student_id FROM bookings WHERE id = 2')->fetchColumn() === 2, 'No se remapearon las referencias del duplicado.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === 4, 'Se perdieron reservas antiguas.');
$assert(array_map('intval', $pdo->query('SELECT id FROM bookings ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)) === [1, 2, 3, 4], 'No se conservaron los IDs de reservas.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM tutor_availabilities')->fetchColumn() === 2, 'Se perdieron disponibilidades antiguas.');
$assert(array_map('intval', $pdo->query('SELECT id FROM tutor_availabilities ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)) === [1, 2], 'No se conservaron los IDs de disponibilidades.');
$assert((int) $pdo->query('SELECT id FROM bookings WHERE id = 2')->fetchColumn() === 2, 'Cambió un id de reserva.');
$assert(
    (string) $pdo->query('SELECT status FROM bookings WHERE id = 2')->fetchColumn() === 'cancelled'
        && (string) $pdo->query('SELECT cancel_reason FROM bookings WHERE id = 2')->fetchColumn() === 'duplicada (migración)',
    'No se canceló la reserva activa duplicada más reciente.'
);
$assert((string) $pdo->query('SELECT status FROM bookings WHERE id = 3')->fetchColumn() === 'pending', 'No se normalizó el estado desconocido.');
$assert(
    (int) $pdo->query('SELECT is_active FROM tutor_availabilities WHERE id = 2')->fetchColumn() === 0,
    'La disponibilidad inválida no quedó desactivada.'
);
$assert((int) $pdo->query("SELECT COUNT(*) FROM tutor_subjects WHERE tutor_id = 1 AND level_id = (
    SELECT id FROM education_levels WHERE name = 'General' COLLATE NOCASE
)")->fetchColumn() === 4, 'No se migraron las materias JSON al nivel General sin duplicar diferencias de mayúsculas.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM subjects WHERE name IN ('C++', 'C#')")->fetchColumn() === 2, 'No se conservaron materias cuyos slugs normalizados coinciden.');
$assert(in_array('subjects', $pdo->query('PRAGMA table_info(tutor_profiles)')->fetchAll(PDO::FETCH_COLUMN, 1), true), 'Se eliminó la columna JSON histórica.');

$snapshot = static function (PDO $pdo): string {
    $state = [];
    foreach ([
        'users' => 'SELECT id, name, email, password, role, is_active, created_at, updated_at FROM users ORDER BY id',
        'tutor_profiles' => 'SELECT id, user_id, rating_avg, reviews_count, subjects FROM tutor_profiles ORDER BY id',
        'bookings' => 'SELECT id, tutor_id, student_id, subject_id, level_id, status, cancel_reason, starts_at, ends_at FROM bookings ORDER BY id',
        'tutor_availabilities' => 'SELECT id, tutor_id, start_at, end_at, is_active FROM tutor_availabilities ORDER BY id',
        'tutor_subjects' => 'SELECT tutor_id, subject_id, level_id FROM tutor_subjects ORDER BY tutor_id, subject_id, level_id',
    ] as $table => $query) {
        $state[$table] = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    return json_encode($state, JSON_THROW_ON_ERROR);
};
$stateBeforeReplay = $snapshot($pdo);
foreach (glob(__DIR__ . '/../database/migrations/*.php') ?: [] as $file) {
    $migration = require $file;
    $requiresForeignKeysOff = is_array($migration) && ($migration['foreign_keys'] ?? null) === false;
    $up = is_array($migration) ? ($migration['up'] ?? null) : $migration;
    if ($requiresForeignKeysOff) {
        $pdo->exec('PRAGMA foreign_keys = OFF');
    }
    $pdo->beginTransaction();
    try {
        $up($pdo);
        if ($requiresForeignKeysOff) {
            $assert($pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC) === [], 'FK inválidas al repetir una migración.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    } finally {
        if ($requiresForeignKeysOff) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
}
$assert($snapshot($pdo) === $stateBeforeReplay, 'Repetir las migraciones alteró los datos existentes.');

$assert((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'Las claves foráneas quedaron desactivadas.');
$assert($pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC) === [], 'PRAGMA foreign_key_check encontró errores.');
$assert((string) $pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'PRAGMA integrity_check no devolvió ok.');

$assertRejected(static function () use ($pdo): void {
    $pdo->exec("INSERT INTO bookings (tutor_id, student_id, starts_at, ends_at, status, hourly_rate, total_price)
        VALUES (1, 1, '2030-03-01T10:00:00Z', '2030-03-01T11:00:00Z', 'pending', 50, 50)");
}, 'Se permitió la autorreserva.');
$assertRejected(static function () use ($pdo): void {
    $pdo->exec("INSERT INTO bookings (tutor_id, student_id, starts_at, ends_at, status, hourly_rate, total_price)
        VALUES (1, 4, '2030-02-01T10:00:00Z', '2030-02-01T11:00:00Z', 'pending', 50, 50)");
}, 'Se permitió otra reserva activa en el mismo inicio.');
$assertRejected(static function () use ($pdo): void {
    $pdo->exec("INSERT INTO bookings (tutor_id, student_id, starts_at, ends_at, status, hourly_rate, total_price)
        VALUES (1, 4, '2030-03-02T10:00:00Z', '2030-03-02T11:00:00Z', 'unknown', 50, 50)");
}, 'Se aceptó un estado de reserva inválido.');
$assertRejected(static function () use ($pdo): void {
    $pdo->exec("INSERT INTO bookings (tutor_id, student_id, starts_at, ends_at, status, hourly_rate, total_price, meeting_type)
        VALUES (1, 4, '2030-03-03T10:00:00Z', '2030-03-03T11:00:00Z', 'pending', 50, 50, 'unknown')");
}, 'Se aceptó un meeting_type inválido.');
$assertRejected(static function () use ($pdo): void {
    $pdo->exec("INSERT INTO users (name, email, password, role) VALUES ('Demo', 'STUDENT@example.test', 'hash', 'student')");
}, 'Se permitió un correo duplicado con distinta capitalización.');
$assertRejected(static function () use ($pdo): void {
    $pdo->exec("INSERT INTO users (name, email, password, role) VALUES ('Demo', 'new@example.test', 'hash', 'superuser')");
}, 'Se aceptó un role inválido.');
$assertRejected(static function () use ($pdo): void {
    $pdo->exec("INSERT INTO reviews (booking_id, tutor_id, student_id, score)
        VALUES (1, 1, 2, 5)");
}, 'Se permitió reseñar una reserva no completada.');

$pdo->exec("UPDATE bookings SET status = 'completed' WHERE id = 1");
$insertReview = $pdo->prepare(
    'INSERT INTO reviews (booking_id, tutor_id, student_id, score) VALUES (:booking_id, 1, 2, :score)'
);
$insertReview->execute([':booking_id' => 1, ':score' => 4]);
$assertRejected(static function () use ($pdo): void {
    $pdo->exec('INSERT INTO reviews (booking_id, tutor_id, student_id, score) VALUES (1, 1, 2, 5)');
}, 'Se permitió una segunda reseña para la misma reserva.');
$insertReview->execute([':booking_id' => 4, ':score' => 5]);
$profileRating = $pdo->query('SELECT rating_avg, reviews_count FROM tutor_profiles WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$assert((float) $profileRating['rating_avg'] === 4.5 && (int) $profileRating['reviews_count'] === 2, 'No se recalcularon rating_avg y reviews_count.');

$rustId = (int) $pdo->query("SELECT id FROM subjects WHERE name = 'Rust' COLLATE NOCASE")->fetchColumn();
$generalId = (int) $pdo->query("SELECT id FROM education_levels WHERE name = 'General' COLLATE NOCASE")->fetchColumn();
$searchRepository = new SearchTutorRepository($pdo);
$filteredTutors = $searchRepository->search(new SearchFiltersDTO(subjectId: $rustId, levelId: $generalId));
$assert(count($filteredTutors) === 1 && $filteredTutors[0]->id === 1, 'El catálogo no filtra por materia y nivel.');
try {
    SearchFiltersDTO::fromRequest(['subject_id' => '0']);
    $assert(false, 'Se aceptó un subject_id inválido en búsqueda.');
} catch (InvalidArgumentException) {
}

$bookingService = new BookingService(new BookingRepository($pdo), new MockVideoAdapter());
$validBooking = CreateBookingDTO::fromRequest([
    'tutor_id' => 1,
    'student_id' => 4,
    'starts_at' => '2030-01-01T10:00:00Z',
    'ends_at' => '2030-01-01T11:00:00Z',
    'hourly_rate' => 50,
    'subject_id' => $rustId,
    'level_id' => $generalId,
    'meeting_type' => 'video',
]);
$createdBooking = $bookingService->create($validBooking);
$assert(($createdBooking['status'] ?? '') === 'success', 'No se aceptó una materia/nivel que ofrece el tutor.');
$assert((int) $pdo->query('SELECT subject_id FROM bookings ORDER BY id DESC LIMIT 1')->fetchColumn() === $rustId, 'No se guardó subject_id en la reserva.');
$invalidCatalogBooking = CreateBookingDTO::fromRequest([
    'tutor_id' => 1,
    'student_id' => 4,
    'starts_at' => '2030-01-01T12:00:00Z',
    'ends_at' => '2030-01-01T13:00:00Z',
    'hourly_rate' => 50,
    'subject_id' => (int) $pdo->query("SELECT id FROM subjects WHERE name = 'Física' COLLATE NOCASE")->fetchColumn(),
    'level_id' => $generalId,
]);
$catalogBookingRejected = false;
try {
    $bookingService->create($invalidCatalogBooking);
} catch (BookingException) {
    $catalogBookingRejected = true;
}
$assert($catalogBookingRejected, 'Se aceptó una materia/nivel que el tutor no ofrece.');

$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$authService = new AuthService();
for ($attempt = 0; $attempt < 5; $attempt++) {
    $failure = $authService->login('student@example.test', 'WrongPassword!23');
    $assert(($failure['status'] ?? '') === 'unauthorized', 'Un intento fallido no se rechazó.');
}
$blocked = $authService->login('student@example.test', $testPassword);
$assert(($blocked['status'] ?? '') === 'unauthorized', 'No se bloqueó el login tras cinco fallos.');
$unknownAccount = $authService->login('missing@example.test', 'WrongPassword!23');
$assert(($blocked['message'] ?? '') === ($unknownAccount['message'] ?? ''), 'Se revela si existe el correo al bloquear el login.');

$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
$successfulLogin = $authService->login('student@example.test', $testPassword);
$assert(($successfulLogin['status'] ?? '') === 'success', 'Falló el login válido.');
$token = (string) $successfulLogin['token'];
$_COOKIE['profego_token'] = $token;
$logout = (new AuthController($authService))->handleLogout([]);
$assert(($logout['status'] ?? '') === 'success', 'Falló el logout.');
$jti = (string) $pdo->query('SELECT jti FROM auth_sessions ORDER BY id DESC LIMIT 1')->fetchColumn();
$assert((string) $pdo->query('SELECT revoked_at FROM auth_sessions WHERE jti = ' . $pdo->quote($jti))->fetchColumn() !== '', 'Logout no revocó el jti persistido.');
$_SERVER['REQUEST_URI'] = '/api/protected';
ob_start();
$middlewareResult = (new AuthMiddleware())->handle();
$middlewareOutput = ob_get_clean();
$assert(($middlewareResult['error'] ?? false) === true && str_contains($middlewareOutput, 'unauthorized'), 'El middleware no rechazó el token revocado con 401.');
$assert(http_response_code() === 401, 'El token revocado no respondió HTTP 401.');
$unregisteredToken = (new JwtService())->generateToken(['user_id' => 2, 'role' => 'student']);
$_COOKIE['profego_token'] = $unregisteredToken;
ob_start();
$unregisteredResult = (new AuthMiddleware())->handle();
$unregisteredOutput = ob_get_clean();
$assert(
    ($unregisteredResult['error'] ?? false) === true && str_contains($unregisteredOutput, 'unauthorized'),
    'El middleware aceptó un token cuyo jti no está registrado.'
);

$pdo = null;
echo json_encode([
    'status' => 'ok',
    'message' => 'Migraciones incrementales, integridad, reglas y seguridad verificadas.',
    'version' => 9,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
