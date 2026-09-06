$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$php = if ($env:PHP_EXE) {
    $env:PHP_EXE
} elseif (Get-Command php.exe -ErrorAction SilentlyContinue) {
    (Get-Command php.exe).Source
} else {
    'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
}

if (-not (Test-Path -LiteralPath $php)) {
    throw "No se encontró PHP. Define PHP_EXE o instala PHP en Laragon."
}

$directories = @('app', 'public', 'config', 'resources', 'tests', 'database') |
    ForEach-Object { Join-Path $projectRoot $_ } |
    Where-Object { Test-Path -LiteralPath $_ }

$phpFiles = foreach ($directory in $directories) {
    Get-ChildItem -LiteralPath $directory -Recurse -File -Filter '*.php'
}

foreach ($file in $phpFiles) {
    & $php -l $file.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "Falló la sintaxis PHP: $($file.FullName)"
    }
}

Push-Location $projectRoot
try {
    if (Get-Command composer.exe -ErrorAction SilentlyContinue) {
        composer dump-autoload -o --no-interaction
        if ($LASTEXITCODE -ne 0) {
            throw 'Composer dump-autoload falló.'
        }
    } else {
        Write-Warning 'Composer no está en PATH; se omitió dump-autoload.'
    }
} finally {
    Pop-Location
}

Write-Host "Validación completada: $($phpFiles.Count) archivos PHP revisados."
