<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\AIEngine\Adapters\MockAIAssistantAdapter;
use App\Modules\AIEngine\Controllers\AIController;
use App\Modules\SearchReputation\Repositories\SearchTutorRepository;

$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        avatar_url TEXT,
        phone TEXT
    );
    CREATE TABLE tutor_profiles (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL,
        headline TEXT NOT NULL,
        bio TEXT NOT NULL,
        hourly_rate REAL NOT NULL,
        rating_avg REAL NOT NULL,
        reviews_count INTEGER NOT NULL,
        modality TEXT NOT NULL,
        city TEXT,
        subjects TEXT NOT NULL
    );
');
$pdo->exec("INSERT INTO users VALUES (1, 'Tutor Python', 'tutor@example.com', 'hash', 'tutor', NULL, NULL)");
$pdo->exec("INSERT INTO tutor_profiles VALUES (1, 1, 'Tutor de Python', 'Backend y algoritmos', 40, 4.9, 10, 'virtual', 'Bogotá', '[\"Python\"]')");

$controller = new AIController(new MockAIAssistantAdapter(new SearchTutorRepository($pdo)));
$result = $controller->chat(['query' => 'Necesito aprender Python']);

if (($result['status'] ?? null) !== 'success') {
    throw new \RuntimeException('La respuesta de IA no fue exitosa: ' . json_encode($result));
}

if (isset($result['data']['status'])) {
    throw new \RuntimeException('El adapter no debe duplicar el envelope status.');
}

if (($result['data']['tutors'][0]['name'] ?? null) !== 'Tutor Python') {
    throw new \RuntimeException('La IA no devolvió el tutor de SQLite esperado.');
}

echo json_encode([
    'status' => 'ok',
    'message' => 'AI integration OK: clean envelope and SQLite tutor',
], JSON_UNESCAPED_UNICODE), PHP_EOL;
