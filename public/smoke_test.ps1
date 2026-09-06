# Smoke Test Local para Windows (Laragon)
$domain = "http://127.0.0.1:8087"

Write-Host "Iniciando Smoke Test en $domain..." -ForegroundColor Cyan

# 1. Buscar automáticamente el ejecutable de PHP en Laragon para evitar errores de PATH
$phpExe = (Get-ChildItem -Path "C:\laragon\bin\php" -Filter "php.exe" -Recurse | Select-Object -First 1 -ExpandProperty FullName)
if (-not $phpExe) {
    $phpExe = "php" # Fallback global
}

Write-Host "Verificando PHP CLI..."
& $phpExe -v
$phpModules = & $phpExe -m
if ($phpModules -match "pdo_sqlite" -and $phpModules -match "curl") {
    Write-Host " [OK] Extensiones pdo_sqlite y curl detectadas." -ForegroundColor Green
} else {
    Write-Host " [X] Faltan extensiones críticas en el php.ini de Laragon." -ForegroundColor Red
}

# 2. Verificar que el Front Controller y rutas funcionen (Código 200)
Write-Host "Verificando ruta de catálogo..."
try {
    $response = Invoke-WebRequest -Uri "$domain/catalog" -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host " [OK] El endpoint /catalog responde con HTTP 200 OK." -ForegroundColor Green
    }
} catch {
    Write-Host " [X] Error de Enrutamiento: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "Smoke Test Finalizado." -ForegroundColor Cyan