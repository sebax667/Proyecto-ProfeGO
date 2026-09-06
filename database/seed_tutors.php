<?php

declare(strict_types=1);

$dbPath = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectando a la base de datos...\n";

    // 1. Asegurar que la estructura de las tablas exista
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            avatar_url TEXT,
            phone TEXT
        );

        CREATE TABLE IF NOT EXISTS tutor_profiles (
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
        );
    ");

    echo "Tablas validadas correctamente.\n";

    // 2. Limpiar datos de prueba anteriores (opcional, para evitar duplicados)
    $pdo->exec("DELETE FROM tutor_profiles;");
    $pdo->exec("DELETE FROM users WHERE role = 'tutor';");

    // 3. Preparar datos de prueba
    $passwordHash = password_hash('Password123*', PASSWORD_ARGON2ID);

    $tutores = [
        [
            'user' => ['Carlos Dev', 'carlos@universidad.edu.co', $passwordHash, 'tutor', 'https://ui-avatars.com/api/?name=Carlos+Dev&background=random', '3001234567'],
            'profile' => ['Ingeniero de Software Senior', 'Especialista en bases de datos relacionales y desarrollo backend. Te enseño a estructurar aplicaciones desde cero.', 45000, 4.9, 120, 'virtual', 'Barranquilla', json_encode(['Python', 'C++', 'SQL Server', 'C#'])]
        ],
        [
            'user' => ['Laura Teacher', 'laura@universidad.edu.co', $passwordHash, 'tutor', 'https://ui-avatars.com/api/?name=Laura+Teacher&background=random', '3109876543'],
            'profile' => ['Docente Bilingüe Certificada', 'Aprende gramática, phrasal verbs y conversación fluida para tu entorno profesional y académico.', 35000, 4.5, 85, 'hybrid', 'Medellín', json_encode(['Inglés', 'Conversación', 'Gramática'])]
        ],
        [
            'user' => ['Andrés Tech', 'andres@universidad.edu.co', $passwordHash, 'tutor', 'https://ui-avatars.com/api/?name=Andres+Tech&background=random', '3205554444'],
            'profile' => ['Experto en Hardware y Mecatrónica', 'Clases prácticas sobre microcontroladores, sensores y circuitos electrónicos para tus proyectos universitarios.', 50000, 5.0, 42, 'presential', 'Bogotá', json_encode(['Arduino', 'Circuitos', 'Física'])]
        ]
    ];

    // 4. Insertar en la base de datos
    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password, role, avatar_url, phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtProfile = $pdo->prepare("INSERT INTO tutor_profiles (user_id, headline, bio, hourly_rate, rating_avg, reviews_count, modality, city, subjects) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($tutores as $t) {
        // Insertar usuario
        $stmtUser->execute($t['user']);
        $userId = $pdo->lastInsertId();

        // Insertar perfil asociado al ID del usuario
        $profileData = array_merge([$userId], $t['profile']);
        $stmtProfile->execute($profileData);
    }

    echo "¡Seed ejecutado con éxito! Se han insertado 3 tutores de prueba.\n";

} catch (PDOException $e) {
    fwrite(STDERR, "Error en la base de datos: " . $e->getMessage() . PHP_EOL);
    exit(1);
}