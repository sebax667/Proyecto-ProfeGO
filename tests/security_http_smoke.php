<?php

declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8087';
$root = dirname(__DIR__);
$descriptorSpec = [
    0 => ['file', 'php://stdin', 'r'],
    1 => ['file', $root . '/tmp/profego_http.log', 'a'],
    2 => ['file', $root . '/tmp/profego_http.err', 'a'],
];
$process = proc_open('php -S 127.0.0.1:8087 -t ' . escapeshellarg($root . '/public'), $descriptorSpec, $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('No se pudo iniciar el servidor de prueba HTTP.');
}

usleep(1500000);

function curlStatus(string $url, array $headers = []): array
{
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => $headers,
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ];

    $stream = stream_context_create($options);
    $content = @file_get_contents($url, false, $stream);
    $meta = stream_get_meta_data($stream);
    $response = $http_response_header ?? [];

    return [
        'content' => $content === false ? '' : (string) $content,
        'headers' => $response,
    ];
}

$checks = [
    ['/login?redirect=https://evil.test', 'safe-redirect'],
    ['/.env', 'blocked-file'],
    ['/database/database.sqlite', 'blocked-db'],
    ['/vendor/autoload.php', 'blocked-vendor'],
];

$failures = [];

foreach ($checks as [$path, $label]) {
    $url = $baseUrl . $path;
    $result = curlStatus($url);
    $headers = implode("\n", $result['headers']);

    if ($label === 'safe-redirect') {
        if (str_contains($headers, 'Location: https://evil.test') || str_contains($headers, 'Location: //evil.test')) {
            $failures[] = 'Open redirect no bloqueado: ' . $path;
        }
    }

    if ($label === 'blocked-file' && $result['content'] !== '') {
        $failures[] = 'Archivo sensible accesible: ' . $path;
    }

    if ($label === 'blocked-db' && $result['content'] !== '') {
        $failures[] = 'Base de datos accesible: ' . $path;
    }

    if ($label === 'blocked-vendor' && $result['content'] !== '') {
        $failures[] = 'Vendor accesible: ' . $path;
    }
}

$csrfHeaders = [
    'Origin: https://evil.example',
    'Content-Type: application/json',
    'Accept: application/json',
];
$csrfContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $csrfHeaders),
        'ignore_errors' => true,
        'content' => json_encode(['email' => 'ana@example.com', 'password' => 'password123']),
        'timeout' => 10,
    ],
]);
$csrfResult = @file_get_contents($baseUrl . '/api/auth/login', false, $csrfContext);
$csrfHeadersText = implode("\n", $http_response_header ?? []);
if (!str_contains($csrfHeadersText, '403')) {
    $failures[] = 'CSRF con origen ajeno no fue rechazado.';
}

proc_terminate($process);
if ($failures !== []) {
    throw new RuntimeException(implode('; ', $failures));
}

echo json_encode([
    'status' => 'ok',
    'message' => 'HTTP smoke OK',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
