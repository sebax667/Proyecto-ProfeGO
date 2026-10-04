<?php

declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8087';
$root = dirname(__DIR__);
$tempDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'profego_http_' . uniqid('', true);
if (!mkdir($tempDirectory, 0700, true) && !is_dir($tempDirectory)) {
    throw new RuntimeException('No se pudo crear el directorio temporal para el smoke test.');
}
$descriptorSpec = [
    0 => ['file', 'php://stdin', 'r'],
    1 => ['file', $tempDirectory . '/profego_http.log', 'a'],
    2 => ['file', $tempDirectory . '/profego_http.err', 'a'],
];
$process = proc_open('php -S 127.0.0.1:8087 -t ' . escapeshellarg($root . '/public'), $descriptorSpec, $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('No se pudo iniciar el servidor de prueba HTTP.');
}

usleep(1500000);

function curlStatus(string $url, array $headers = [], bool $followLocation = true): array
{
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => $headers,
            'ignore_errors' => true,
            'timeout' => 10,
            'follow_location' => $followLocation ? 1 : 0,
            'max_redirects' => $followLocation ? 20 : 0,
        ],
    ];

    $stream = stream_context_create($options);
    $content = @file_get_contents($url, false, $stream);
    $response = $http_response_header ?? [];

    return [
        'content' => $content === false ? '' : (string) $content,
        'headers' => $response,
        'status' => isset($response[0]) && preg_match('/\s(\d{3})(?:\s|$)/', $response[0], $matches) === 1
            ? (int) $matches[1]
            : 0,
    ];
}

$checks = [
    ['/login?redirect=https://evil.test', 'safe-redirect'],
    ['/.env', 'blocked-file'],
    ['/database/database.sqlite', 'blocked-db'],
    ['/vendor/autoload.php', 'blocked-vendor'],
];

$failures = [];

$home = curlStatus($baseUrl . '/', [], false);
if ($home['status'] !== 302 || !str_contains(implode("\n", $home['headers']), 'Location: /catalog')) {
    $failures[] = 'La ruta raíz no redirige a /catalog con HTTP 302.';
}

$webNotFound = curlStatus($baseUrl . '/missing-page');
if ($webNotFound['status'] !== 404 || !str_contains($webNotFound['content'], 'Error 404')) {
    $failures[] = 'La ruta web desconocida no mostró la página HTML 404.';
}

$apiNotFound = curlStatus($baseUrl . '/api/missing-page');
if ($apiNotFound['status'] !== 404 || json_decode($apiNotFound['content'], true) === null) {
    $failures[] = 'La ruta API desconocida no conservó la respuesta JSON 404.';
}

foreach ($checks as [$path, $label]) {
    $url = $baseUrl . $path;
    $result = curlStatus($url);
    $headers = implode("\n", $result['headers']);

    if ($label === 'safe-redirect') {
        if (str_contains($headers, 'Location: https://evil.test') || str_contains($headers, 'Location: //evil.test')) {
            $failures[] = 'Open redirect no bloqueado: ' . $path;
        }
    }

    if (in_array($label, ['blocked-file', 'blocked-db', 'blocked-vendor'], true)
        && !in_array($result['status'], [403, 404], true)) {
        $failures[] = 'Ruta sensible no devolvió 403/404: ' . $path . ' (HTTP ' . $result['status'] . ')';
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
proc_close($process);
@unlink($tempDirectory . '/profego_http.log');
@unlink($tempDirectory . '/profego_http.err');
@rmdir($tempDirectory);

if ($failures !== []) {
    throw new RuntimeException(implode('; ', $failures));
}

echo json_encode([
    'status' => 'ok',
    'message' => 'HTTP smoke OK',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
