<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Bookings\Controllers\BookingController;
use App\Modules\Bookings\Repositories\BookingRepository;
use App\Modules\Bookings\Services\BookingService;
use App\Modules\Integrations\Adapters\MockVideoAdapter;

$dbPath = sys_get_temp_dir() . '/sistema_app_booking_test_' . uniqid('', true) . '.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL,
    avatar_url TEXT,
    phone TEXT
)");

$pdo->exec("CREATE TABLE tutor_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    headline TEXT NOT NULL,
    bio TEXT,
    hourly_rate REAL NOT NULL,
    rating_avg REAL DEFAULT 0.0,
    reviews_count INTEGER DEFAULT 0,
    modality TEXT NOT NULL,
    city TEXT,
    subjects TEXT,
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
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed')),
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

$pdo->exec("INSERT INTO users (id, name, email, password, role, avatar_url, phone) VALUES (1, 'Tutor Demo', 'tutor@example.com', 'hash', 'tutor', null, null)");
$pdo->exec("INSERT INTO users (id, name, email, password, role, avatar_url, phone) VALUES (2, 'Student Demo', 'student@example.com', 'hash', 'student', null, null)");
$pdo->exec("INSERT INTO tutor_profiles (id, user_id, headline, bio, hourly_rate, rating_avg, reviews_count, modality, city, subjects) VALUES (1, 1, 'Tutor Demo', 'Bio demo', 50, 4.9, 25, 'virtual', 'Bogotá', '[\"Matemáticas\", \"Física\"]')");
$pdo->exec("INSERT INTO tutor_availabilities (id, tutor_id, start_at, end_at, is_active) VALUES (1, 1, '2026-09-01T10:00:00+00:00', '2026-09-01T12:00:00+00:00', 1)");

$repo = new BookingRepository($pdo);
$service = new BookingService($repo, new MockVideoAdapter());
$controller = new BookingController($service);

$request = [
    'tutor_id' => 1,
    'student_id' => 2,
    'starts_at' => '2026-09-01T10:30:00+00:00',
    'ends_at' => '2026-09-01T11:30:00+00:00',
    'hourly_rate' => 50,
    'title' => 'Clase de álgebra',
    'notes' => 'Repaso de ecuaciones cuadráticas',
    'meeting_type' => 'video',
];

$createResult = $controller->store($request, ['user_id' => 2]);
if (($createResult['status'] ?? '') !== 'success') {
    throw new RuntimeException('Fallo al crear reserva: ' . json_encode($createResult));
}

$bookingId = (int) ($createResult['booking']['id'] ?? 0);
if ($bookingId <= 0) {
    throw new RuntimeException('No se creó el booking correctamente.');
}

$confirmResult = $controller->confirm(['booking_id' => $bookingId], ['user_id' => 2]);
if (($confirmResult['status'] ?? '') !== 'success') {
    throw new RuntimeException('Fallo al confirmar reserva: ' . json_encode($confirmResult));
}

$cancelResult = $controller->cancel(['booking_id' => $bookingId, 'reason' => 'Cambio de horario'], ['user_id' => 2]);
if (($cancelResult['status'] ?? '') !== 'success') {
    throw new RuntimeException('Fallo al cancelar reserva: ' . json_encode($cancelResult));
}

$finalBooking = $repo->findById($bookingId);
if (($finalBooking['status'] ?? '') !== 'cancelled') {
    throw new RuntimeException('La reserva no terminó en estado cancelled.');
}

$pdo = null;
@unlink($dbPath);

echo json_encode([
    'status' => 'ok',
    'message' => 'Booking lifecycle OK: create -> confirm -> cancel',
    'booking_id' => $bookingId,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
