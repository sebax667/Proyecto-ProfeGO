# ProfeGo

ProfeGo es una plataforma SSR para conectar estudiantes con tutores, gestionar reservas, buscar perfiles y disponer de soporte de IA dentro de un monolito PHP modular.

## Demo en producción

La URL pública queda definida por la variable `APP_URL` del entorno de despliegue; mientras no se asigne, usa un placeholder seguro como `https://<HOST>`.

## Cuentas de prueba

Las credenciales reales de tutor y estudiante se generan desde el seeder y deben mostrarse solo en consola durante la inicialización local o de staging. Nunca deben quedar en el repositorio ni en documentación pública.

## Flujo de despliegue

1. El repositorio se valida en GitHub Actions.
2. El pipeline de CI exige Composer, lint PHP, shellcheck equivalente con `bash -n`, compilación de CSS y ejecución de los scripts de prueba.
3. El despliegue automático solo continúa tras CI exitoso en `main`; el despacho manual también está limitado a `main` y requiere el entorno protegido y secrets de servidor.
4. El job de despliegue ejecuta la actualización del repositorio en el VPS, reinstala dependencias y recarga PHP-FPM.
5. El smoke test exige HTTP 403/404 para `.env` y la base SQLite y valida los encabezados de seguridad de `/catalog`.

Las acciones de terceros del workflow están fijadas a commits SHA. Los workflows no se ejecutan desde esta guía; el despliegue remoto pertenece exclusivamente al pipeline protegido.

## Variables de entorno relevantes

- `APP_ENV` — `local`, `production` u otro valor operativo.
- `APP_DEBUG` — controla la salida de depuración.
- `JWT_SECRET` — secreto mínimo de 32 caracteres.
- `AI_PROVIDER` — `mock` o `openai`.
- `OPENAI_*` — credenciales del proveedor OpenAI.
- `VIDEO_PROVIDER` — `mock`, `googlemeet` o `teams` según el adaptador activo.
- `MAIL_*` — configuración SMTP para notificaciones.
- `DB_PATH` — ruta absoluta a la base SQLite para pruebas y entornos aislados.

## Stack tecnológico

- PHP 8.3 + Composer
- SQLite con PDO
- Nginx + PHP-FPM
- Tailwind CSS 3.4
- SSR con front controller en public/index.php
- GitHub Actions para CI/CD

## Instalación local

```bash
composer install
cp .env.example .env
npm ci
npm run build:css
php tests/auth_integration.php
php tests/ai_integration.php
php tests/booking_integration.php
php tests/booking_authz_integration.php
php tests/migration_integration.php
php tests/security_http_smoke.php
```

`security_http_smoke.php` inicia un servidor PHP local en `127.0.0.1:8087`; asegúrate de que el puerto esté disponible antes de ejecutarlo.

## Ejecución

La aplicación se sirve desde la carpeta `public`:

```bash
php -S 127.0.0.1:8087 -t public
```

## Validación recomendada

```bash
find app public resources tests database -type f -name '*.php' -print0 | xargs -0 -n1 php -l
bash -n deploy/*.sh
npm run build:css
for file in tests/*.php; do php "$file"; done
```

## Seguridad y cumplimiento

La capa de seguridad actual incluye validación de entradas, JWT con `iss`/`exp`/`jti`, cookies con `HttpOnly` y `SameSite`, bloqueo de redirecciones abiertas y middleware de origen para CSRF.

## Alcance funcional pendiente

- El panel del tutor todavía muestra métricas y contenido de ejemplo estáticos; deben reemplazarse por agregados derivados de reservas reales y cubrirse con pruebas de integración.
- La recuperación de contraseña no está implementada. El inicio de sesión no ofrece un enlace a un flujo que aún no cuenta con tokens de un solo uso, expiración y pruebas de abuso.
