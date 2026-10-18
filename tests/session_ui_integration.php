<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$databasePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'profego_session_ui_' . bin2hex(random_bytes(8)) . '.sqlite';
$logPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'profego_session_ui_' . bin2hex(random_bytes(8)) . '.log';
$errorPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'profego_session_ui_' . bin2hex(random_bytes(8)) . '.err';
$ttl = 2473;
$pdo = null;
putenv('APP_ENV=testing');
putenv('AUTO_MIGRATE=1');
putenv('DB_PATH=' . $databasePath);
putenv('JWT_SECRET=' . bin2hex(random_bytes(32)));
putenv('JWT_TTL=' . $ttl);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$request = static function (string $url, string $method = 'GET', ?string $body = null, array $headers = []): array {
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 10,
        'follow_location' => 0,
        'header' => implode("\r\n", $headers),
    ];
    if ($body !== null) {
        $options['content'] = $body;
    }

    $context = stream_context_create(['http' => $options]);
    $content = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    return [
        'body' => $content === false ? '' : (string) $content,
        'headers' => $responseHeaders,
        'status' => isset($responseHeaders[0]) && preg_match('/\s(\d{3})(?:\s|$)/', $responseHeaders[0], $matches) === 1
            ? (int) $matches[1]
            : 0,
    ];
};

$socket = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
if (!is_resource($socket)) {
    throw new RuntimeException('No se pudo reservar un puerto HTTP temporal: ' . $socketMessage);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$port = (int) substr((string) strrchr((string) $address, ':'), 1);
$baseUrl = 'http://127.0.0.1:' . $port;
$descriptorSpec = [
    0 => ['file', 'NUL', 'r'],
    1 => ['file', $logPath, 'a'],
    2 => ['file', $errorPath, 'a'],
];
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public'], $descriptorSpec, $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('No se pudo iniciar el servidor HTTP de integración.');
}

try {
    $registerPage = null;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $registerPage = $request($baseUrl . '/register');
        if ($registerPage['status'] === 200) {
            break;
        }
        usleep(100000);
    }
    $assert(is_array($registerPage) && $registerPage['status'] === 200, 'El formulario de registro no cargó.');
    $assert(str_contains($registerPage['body'], 'name="terms" required'), 'El formulario no tiene aceptación obligatoria de términos.');
    $assert(str_contains($registerPage['body'], '<dialog id="terms-dialog"'), 'El formulario no incluye el diálogo de términos.');
    $assert(str_contains($registerPage['body'], '<script src="/assets/js/register.js" defer></script>'), 'El formulario no carga el JavaScript externo bajo CSP.');
    $assert(!str_contains($registerPage['body'], '<script>'), 'El formulario conserva un script inline bloqueable por CSP.');

    $registerScript = $request($baseUrl . '/assets/js/register.js');
    $assert($registerScript['status'] === 200, 'El script externo de registro no se sirve.');
    $assert(str_contains($registerScript['body'], "terms: terms.checked"), 'El formulario no envía la aceptación al servidor.');

    $headers = [
        'Origin: ' . $baseUrl,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $email = 'session-ui-' . bin2hex(random_bytes(5)) . '@example.test';
    $password = 'TestPass123';
    $name = '<script>alert(1)</script>';
    $missingTerms = $request(
        $baseUrl . '/api/auth/register',
        'POST',
        json_encode(['name' => $name, 'email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        $headers
    );
    $assert($missingTerms['status'] === 400, 'El servidor aceptó un registro sin términos.');
    $pdo = new PDO('sqlite:' . $databasePath);
    $assert((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0, 'El registro sin términos creó una cuenta.');

    $registration = $request(
        $baseUrl . '/api/auth/register',
        'POST',
        json_encode(['name' => $name, 'email' => $email, 'password' => $password, 'terms' => true], JSON_THROW_ON_ERROR),
        $headers
    );
    $assert($registration['status'] === 201, 'El registro con términos no creó la cuenta: ' . $registration['body']);
    $user = $pdo->prepare('SELECT id, name, password, terms_accepted_at, terms_version FROM users WHERE email = :email');
    $user->execute([':email' => $email]);
    $storedUser = $user->fetch(PDO::FETCH_ASSOC);
    $user->closeCursor();
    $assert(is_array($storedUser), 'No se guardó el usuario registrado.');
    $assert($storedUser['name'] === $name, 'El nombre del usuario no se conservó.');
    $assert(password_verify($password, $storedUser['password']), 'La contraseña no quedó almacenada con hash válido.');
    $assert($storedUser['terms_accepted_at'] !== null && $storedUser['terms_accepted_at'] !== '', 'No se guardó la fecha de aceptación.');
    $assert($storedUser['terms_version'] === 'v1', 'No se guardó la versión de términos.');

    $unauthenticatedMe = $request($baseUrl . '/api/auth/me');
    $assert($unauthenticatedMe['status'] === 401, '/api/auth/me no devolvió 401 sin cookie.');

    $login = $request(
        $baseUrl . '/api/auth/login',
        'POST',
        json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        $headers
    );
    $assert($login['status'] === 200, 'El login del flujo no funcionó: ' . $login['body']);
    $cookieHeader = '';
    foreach ($login['headers'] as $header) {
        if (preg_match('/^Set-Cookie:\s*profego_token=([^;]+);(.*)$/i', $header, $matches) === 1) {
            $cookieHeader = 'profego_token=' . $matches[1];
            $cookieAttributes = $matches[2];
            $token = $matches[1];
            break;
        }
    }
    $assert($cookieHeader !== '', 'El login no emitió la cookie HttpOnly de sesión.');
    $assert(str_contains(strtolower($cookieAttributes), 'httponly'), 'La cookie no es HttpOnly.');
    $assert(preg_match('/expires=([^;]+)/i', $cookieAttributes, $expiresMatch) === 1, 'La cookie no especificó expiración.');
    $cookieExpiresAt = strtotime(trim($expiresMatch[1]));
    $tokenParts = explode('.', $token);
    $encodedPayload = strtr($tokenParts[1], '-_', '+/');
    $encodedPayload .= str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4);
    $claims = json_decode((string) base64_decode($encodedPayload, true), true);
    $assert(is_array($claims) && isset($claims['exp'], $claims['jti']), 'No se pudo leer expiración y jti del JWT.');
    $assert((int) $claims['exp'] - time() <= $ttl && (int) $claims['exp'] - time() >= $ttl - 2, 'El JWT no usó JWT_TTL personalizado.');

    $session = $pdo->prepare('SELECT user_id, expires_at FROM auth_sessions WHERE jti = :jti');
    $session->execute([':jti' => $claims['jti']]);
    $sessionRow = $session->fetch(PDO::FETCH_ASSOC);
    $assert(
        is_array($sessionRow),
        'No se encontró la sesión correspondiente al jti del JWT. JWT=' . $claims['jti']
            . '; sesiones=' . implode(',', $pdo->query('SELECT jti FROM auth_sessions')->fetchAll(PDO::FETCH_COLUMN))
    );
    $assert((int) $sessionRow['user_id'] === (int) $storedUser['id'], 'La sesión quedó asociada a otro usuario.');
    $sessionExpiresAt = strtotime((string) $sessionRow['expires_at']);
    $assert($cookieExpiresAt === (int) $claims['exp'], 'La expiración de la cookie no coincide con la del JWT.');
    $assert(
        $sessionExpiresAt === (int) $claims['exp'],
        sprintf('La expiración de auth_sessions (%s) no coincide con la del JWT (%s).', (string) $sessionExpiresAt, (string) $claims['exp'])
    );

    $authenticatedMe = $request($baseUrl . '/api/auth/me', 'GET', null, ['Cookie: ' . $cookieHeader]);
    $profile = json_decode($authenticatedMe['body'], true);
    $assert($authenticatedMe['status'] === 200 && ($profile['status'] ?? null) === 'success', '/api/auth/me no devolvió 200 con sesión.');
    $assert(($profile['user']['name'] ?? null) === $name, '/api/auth/me no devolvió el perfil del usuario.');
    $assert(!array_key_exists('password', $profile['user'] ?? []), '/api/auth/me expuso el hash de contraseña.');

    $dashboard = $request($baseUrl . '/dashboard', 'GET', null, ['Cookie: ' . $cookieHeader]);
    $assert($dashboard['status'] === 200, 'El dashboard protegido no cargó con la cookie.');
    $assert(str_contains($dashboard['body'], '&lt;script&gt;alert(1)&lt;/script&gt;'), 'El dashboard no escapó el nombre como HTML.');
    $assert(!str_contains($dashboard['body'], '<script>alert(1)</script>'), 'El nombre creó un elemento script ejecutable en el dashboard.');

    $externalRedirect = $request($baseUrl . '/login?redirect=https%3A%2F%2Fevil.example');
    $assert(
        $externalRedirect['status'] === 200
            && preg_match('/<input type="hidden" name="redirect" value="([^"]*)"/', $externalRedirect['body'], $redirectMatch) === 1
            && html_entity_decode($redirectMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === '/dashboard',
        'El redirect externo no fue sustituido por /dashboard.'
    );

    echo json_encode([
        'status' => 'ok',
        'message' => 'Registro, sesión, perfil protegido, expiración, XSS y redirect verificados.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    proc_terminate($process);
    proc_close($process);
    $user = null;
    $session = null;
    $pdo = null;
    foreach ([$databasePath, $databasePath . '-wal', $databasePath . '-shm', $logPath, $errorPath] as $temporaryFile) {
        if (is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
    }
}
