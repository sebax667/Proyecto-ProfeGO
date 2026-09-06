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
$router->post('/api/auth/register', [AuthController::class, 'handleRegister']);
$router->post('/api/auth/login', [AuthController::class, 'handleLogin']);

// Pasamos el objeto $catalogController YA INSTANCIADO dentro del array:
$router->get('/catalog', [$catalogController, 'index']);

$renderModule = static function (string $view, string $title): string {
    $viewPath = __DIR__ . '/../resources/views/modules/' . $view;

    if (!is_file($viewPath)) {
        http_response_code(404);
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>404 | ProfeGo</title></head><body><h1>404</h1><p>La vista solicitada no está disponible.</p></body></html>';
    }

    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
};

$router->get('/dashboard', static function (array $request, ?array $authUser) use ($renderModule): string {
    $role = (string) ($authUser['role'] ?? 'student');
    $view = $role === 'tutor' || $role === 'admin'
        ? 'dashboard/teacher.php'
        : 'dashboard/student.php';

    return $renderModule($view, 'Dashboard | ProfeGo');
}, [AuthMiddleware::class]);

$router->get('/chat', static function () use ($renderModule): string {
    return $renderModule('chat/index.php', 'Chat | ProfeGo');
});

$router->get('/settings', static function () use ($renderModule): string {
    return $renderModule('settings/index.php', 'Configuración | ProfeGo');
}, [AuthMiddleware::class]);

// --- Rutas Protegidas ---
$router->post('/api/bookings', [$bookingController, 'store'], [
    AuthMiddleware::class,
]);
$router->post('/api/bookings/confirm', [$bookingController, 'confirm'], [
    AuthMiddleware::class,
]);
$router->post('/api/bookings/cancel', [$bookingController, 'cancel'], [
    AuthMiddleware::class,
]);

$router->get('/api/user/profile', [AuthController::class, 'profile'], [
    AuthMiddleware::class,
    RoleMiddleware::class
]);

// --- Rutas de IA (Asistente) ---
$router->post('/api/ai/chat', [$aiController, 'chat']);
$router->get('/api/ai/keywords', [$aiController, 'getKeywords']);

// 6. Despachar la petición
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);