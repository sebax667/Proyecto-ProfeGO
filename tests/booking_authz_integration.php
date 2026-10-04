<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Bookings\Controllers\BookingController;
use App\Modules\Bookings\Repositories\BookingRepository;
use App\Modules\Bookings\Services\BookingService;

$basePath = sys_get_temp_dir() . '/profego_booking_authz_' . uniqid('', true) . '.sqlite';
$pdo = new PDO('sqlite:' . $basePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('DROP TABLE IF EXISTS bookings');
$pdo->exec('DROP TABLE IF EXISTS tutor_availabilities');
$pdo->exec('DROP TABLE IF EXISTS tutor_profiles');
$pdo->exec('DROP TABLE IF EXISTS users');

$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'student'
)");
$pdo->exec("CREATE TABLE tutor_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    headline TEXT NOT NULL,
    bio TEXT,
    hourly_rate REAL NOT NULL DEFAULT 0,
    rating_avg REAL NOT NULL DEFAULT 0,
    reviews_count INTEGER NOT NULL DEFAULT 0,
    modality TEXT NOT NULL DEFAULT 'virtual',
    city TEXT,
    subjects TEXT DEFAULT '[]',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE tutor_availabilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tutor_id INTEGER NOT NULL,
    start_at TEXT NOT NULL,
    end_at TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tutor_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    starts_at TEXT NOT NULL,
    ends_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    hourly_rate REAL NOT NULL,
    total_price REAL NOT NULL,
    title TEXT,
    notes TEXT,
    meeting_type TEXT DEFAULT 'video',
    meeting_id TEXT,
    meeting_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
)");

$pdo->exec("INSERT INTO users (id, name, email, password, role) VALUES (1, 'Student A', 'a@example.com', 'hash', 'student')");
$pdo->exec("INSERT INTO users (id, name, email, password, role) VALUES (2, 'Student B', 'b@example.com', 'hash', 'student')");
$pdo->exec("INSERT INTO users (id, name, email, password, role) VALUES (3, 'Tutor', 'tutor@example.com', 'hash', 'tutor')");
$pdo->exec("INSERT INTO tutor_profiles (id, user_id, headline, bio, hourly_rate, rating_avg, reviews_count, modality, city, subjects) VALUES (1, 3, 'Tutor', 'Bio', 60, 4.8, 10, 'virtual', 'Bogotá', '[\"Math\"]')");
$pdo->exec("INSERT INTO tutor_availabilities (id, tutor_id, start_at, end_at, is_active) VALUES (1, 1, '2030-01-10T09:00:00Z', '2030-01-10T12:00:00Z', 1)");

$repo = new BookingRepository($pdo);
$service = new BookingService($repo);
$controller = new BookingController($service);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$storeResult = $controller->store([
    'tutor_id' => 1,
    'student_id' => 2,
    'starts_at' => '2030-01-10T09:30:00Z',
    'ends_at' => '2030-01-10T10:30:00Z',
    'hourly_rate' => 999,
    'total_price' => 99999,
    'title' => 'Clase de prueba',
], ['user_id' => 1]);
$assert(($storeResult['status'] ?? '') === 'success', 'La reserva del estudiante autenticado no se creó. ' . json_encode($storeResult));
$assert(($storeResult['booking']['student_id'] ?? null) === 1, 'El student_id del request fue aceptado en lugar del autenticado.');
$assert(($storeResult['booking']['hourly_rate'] ?? null) == 60.0, 'El precio por hora fue tomado del cliente en vez de tutor_profiles.');

$bookingId = (int) ($storeResult['booking']['id'] ?? 0);
$assert($bookingId > 0, 'No se obtuvo el ID de la reserva.');

$thirdPartyConfirm = $controller->confirm(['booking_id' => $bookingId], ['user_id' => 2]);
$assert(($thirdPartyConfirm['message'] ?? '') === 'La reserva no existe.', 'Un tercero pudo confirmar una reserva ajena.');

$thirdPartyCancel = $controller->cancel(['booking_id' => $bookingId], ['user_id' => 2]);
$assert(($thirdPartyCancel['message'] ?? '') === 'La reserva no existe.', 'Un tercero pudo cancelar una reserva ajena.');

$tutorConfirm = $controller->confirm(['booking_id' => $bookingId], ['user_id' => 3, 'role' => 'tutor']);
$assert(($tutorConfirm['status'] ?? '') === 'success', 'El tutor dueño no pudo confirmar. ' . json_encode($tutorConfirm));

$invalidReject = $controller->reject(['booking_id' => $bookingId], ['user_id' => 1, 'role' => 'student']);
$assert(($invalidReject['message'] ?? '') === 'La reserva no existe.', 'Un estudiante pudo rechazar la reserva del tutor.');

$past = $controller->store([
    'tutor_id' => 1,
    'student_id' => 2,
    'starts_at' => '2020-01-10T09:00:00Z',
    'ends_at' => '2020-01-10T10:00:00Z',
    'hourly_rate' => 50,
], ['user_id' => 2]);
$assert(($past['status'] ?? '') === 'error', 'Se aceptó una reserva en el pasado.');

$selfBooking = $controller->store([
    'tutor_id' => 1,
    'student_id' => 3,
    'starts_at' => '2030-01-11T09:00:00Z',
    'ends_at' => '2030-01-11T11:00:00Z',
    'hourly_rate' => 50,
], ['user_id' => 3]);
$assert(($selfBooking['status'] ?? '') === 'error', 'Se permitió reservarse a sí mismo.');

$overlap = $controller->store([
    'tutor_id' => 1,
    'student_id' => 2,
    'starts_at' => '2030-01-10T09:45:00Z',
    'ends_at' => '2030-01-10T10:45:00Z',
    'hourly_rate' => 50,
], ['user_id' => 2]);
$assert(($overlap['status'] ?? '') === 'error', 'Se aceptó un solapamiento con una reserva existente.');

@unlink($basePath);
echo json_encode(['status' => 'ok', 'message' => 'Booking authz integration OK'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
