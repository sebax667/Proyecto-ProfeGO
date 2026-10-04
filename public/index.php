<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Support/helpers.php';

if (is_file(__DIR__ . '/../.env')) {
    $environmentLines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($environmentLines === false) {
        throw new RuntimeException('No se pudo leer el archivo de entorno.');
    }

    foreach ($environmentLines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
    }
}

$appEnv = strtolower((string) getenv('APP_ENV'));
ini_set('display_errors', $appEnv === 'production' ? '0' : '1');
error_reporting($appEnv === 'production' ? E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED : E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$jwtSecret = (string) getenv('JWT_SECRET');
if ($appEnv === 'production' && ($jwtSecret === '' || strlen($jwtSecret) < 32 || str_starts_with($jwtSecret, 'replace-with'))) {
    http_response_code(500);
    echo 'JWT_SECRET no está configurado correctamente para producción.';
    exit(1);
}

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Bookings\Controllers\BookingController;
use App\Modules\Bookings\Listeners\SendEmailNotificationListener;
use App\Modules\Bookings\Repositories\BookingRepository;
use App\Modules\Bookings\Services\BookingService;
use App\Modules\AIEngine\Controllers\AIController;
use App\Modules\AIEngine\Adapters\OpenAIAssistantAdapter;
use App\Modules\AIEngine\Adapters\MockAIAssistantAdapter;
use App\Modules\Integrations\Adapters\MockVideoAdapter;
use App\Modules\SearchReputation\Controllers\TutorCatalogController;
use App\Modules\SearchReputation\Repositories\SearchTutorRepository;
use App\Shared\Events\EventDispatcher;
use App\Shared\Http\Router;
use App\Shared\Middleware\AuthMiddleware;
use App\Shared\Middleware\CsrfOriginMiddleware;
use App\Shared\Middleware\RoleMiddleware;
use App\Shared\Database\Database;

// 1. Instancia de BD SQLite
$pdo = Database::getConnection();

// 2. Instancia del EventDispatcher (Observer Pattern)
$eventDispatcher = new EventDispatcher();

// 3. Registro de listeners para eventos específicos
$eventDispatcher->subscribe(
    \App\Modules\Bookings\Events\BookingCreatedEvent::class,
    new SendEmailNotificationListener()
);

// 4. Inyección de dependencias (Instanciamos el repositorio y los controladores)
$tutorRepository = new SearchTutorRepository($pdo);
$catalogController = new TutorCatalogController($tutorRepository);
$bookingController = new BookingController(
    new BookingService(
        new BookingRepository($pdo),
        new MockVideoAdapter(),
        $eventDispatcher  // Inyectamos el EventDispatcher en BookingService
    )
);
$aiProvider = strtolower((string) (getenv('AI_PROVIDER') ?: 'mock'));
$aiAdapter = $aiProvider === 'openai'
    ? new OpenAIAssistantAdapter(
        (string) (getenv('OPENAI_API_KEY') ?: ''),
        (string) (getenv('OPENAI_MODEL') ?: 'gpt-4o-mini'),
        30,
        $tutorRepository
    )
    : new MockAIAssistantAdapter($tutorRepository);
$aiController = new AIController($aiAdapter);

// 5. Inicializar Router
$router = new Router();

// --- Rutas Públicas ---
$router->post('/api/auth/register', [AuthController::class, 'handleRegister'], [new CsrfOriginMiddleware()]);
$router->post('/api/auth/login', [AuthController::class, 'handleLogin'], [new CsrfOriginMiddleware()]);
$router->post('/api/auth/logout', [AuthController::class, 'handleLogout'], [new CsrfOriginMiddleware(), AuthMiddleware::class]);

// Pasamos el objeto $catalogController YA INSTANCIADO dentro del array:
$router->get('/catalog', [$catalogController, 'index']);

$router->get('/login', static function (): string {
    $viewPath = __DIR__ . '/../resources/views/modules/auth/login.php';

    if (!is_file($viewPath)) {
        http_response_code(404);
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>404 | ProfeGo</title></head><body><h1>404</h1><p>La vista de inicio de sesión no está disponible.</p></body></html>';
    }

    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
});

$router->get('/register', static function (): string {
    $viewPath = __DIR__ . '/../resources/views/modules/auth/register.php';

    if (!is_file($viewPath)) {
        http_response_code(404);
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>404 | ProfeGo</title></head><body><h1>404</h1><p>La vista de registro no está disponible.</p></body></html>';
    }

    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
});

$renderModule = static function (string $view, string $title, array $data = []): string {
    $viewPath = __DIR__ . '/../resources/views/modules/' . $view;

    if (!is_file($viewPath)) {
        http_response_code(404);
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>404 | ProfeGo</title></head><body><h1>404</h1><p>La vista solicitada no está disponible.</p></body></html>';
    }

    // Convertimos el array de datos en variables reales para la vista
    extract($data);
    
    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
};

$router->get('/dashboard', static function (array $request, ?array $authUser) use ($renderModule, $pdo): string {
    $role = (string) ($authUser['role'] ?? 'student');
    $userId = (int) ($authUser['id'] ?? $authUser['sub'] ?? 0);
    $data = [];

    if ($role === 'tutor' || $role === 'admin') {
        $view = 'dashboard/teacher.php';
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as student_name 
            FROM bookings b 
            JOIN users u ON b.student_id = u.id 
            WHERE b.tutor_id = :user_id 
            ORDER BY b.starts_at ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        $data['bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $view = 'dashboard/student.php';
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as tutor_name 
            FROM bookings b 
            JOIN users u ON b.tutor_id = u.id
            WHERE b.student_id = :user_id 
            ORDER BY b.starts_at ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        $data['bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $renderModule($view, 'Dashboard | ProfeGo', $data);
}, [AuthMiddleware::class]);

$router->get('/chat', static function () use ($renderModule): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>401 | ProfeGo</title></head><body><h1>401</h1><p>Debe iniciar sesión para acceder a esta vista.</p></body></html>';
    }

    return $renderModule('chat/index.php', 'Chat | ProfeGo');
}, [AuthMiddleware::class]);

$router->get('/settings', static function () use ($renderModule): string {
    return $renderModule('settings/index.php', 'Configuración | ProfeGo');
}, [AuthMiddleware::class]);

// --- Rutas Protegidas ---
$router->post('/api/bookings', [$bookingController, 'store'], [
    new CsrfOriginMiddleware(),
    AuthMiddleware::class,
]);
$router->post('/api/bookings/confirm', [$bookingController, 'confirm'], [
    new CsrfOriginMiddleware(),
    AuthMiddleware::class,
]);
$router->post('/api/bookings/cancel', [$bookingController, 'cancel'], [
    new CsrfOriginMiddleware(),
    AuthMiddleware::class,
]);

$router->get('/api/user/profile', [AuthController::class, 'profile'], [
    AuthMiddleware::class,
    new RoleMiddleware([\App\Shared\Enums\UserRole::STUDENT->value, \App\Shared\Enums\UserRole::TUTOR->value, \App\Shared\Enums\UserRole::ADMIN->value]),
]);

// --- Rutas de IA (Asistente) ---
$router->post('/api/ai/chat', [$aiController, 'chat'], [
    new CsrfOriginMiddleware(),
    AuthMiddleware::class,
]);
$router->get('/api/ai/keywords', [$aiController, 'getKeywords']);

// 6. Despachar la petición
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);