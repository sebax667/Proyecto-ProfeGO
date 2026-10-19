# ProfeGo

ProfeGo es una plataforma SSR para conectar estudiantes con tutores, gestionar reservas, buscar perfiles y disponer de soporte de IA dentro de un monolito PHP modular.

## Demo en producción

La URL pública queda definida por la variable `APP_URL` del entorno de despliegue; mientras no se asigne, usa un placeholder seguro como `https://<HOST>`.

## Cuentas de prueba

Las credenciales reales de tutor y estudiante se generan desde el seeder y deben mostrarse solo en consola durante la inicialización local o de staging. Nunca deben quedar en el repositorio ni en documentación pública.

## Flujo de despliegue

1. El repositorio se valida en GitHub Actions.
2. El pipeline de CI exige Composer, lint PHP, shellcheck equivalente con `bash -n`, compilación de CSS y ejecución de los scripts de prueba.
3. El despliegue automático solo continúa tras CI exitoso por push a `main`; el despacho manual también está limitado a `main` y requiere el environment protegido y sus secrets.
4. El job verifica la host key ED25519 fijada en `VPS_KNOWN_HOSTS`, respalda SQLite, desactiva la migración automática, despliega el SHA exacto probado, aplica y valida las migraciones, revisa salud/logs y revierte al backup/commit anterior ante fallos.
5. El smoke test público exige HTTP 403/404 para rutas sensibles y valida los encabezados de seguridad.

Las acciones de terceros del workflow están fijadas a commits SHA. La preparación de VPS, known_hosts, sudoers, secuencia y rollback se documentan en [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md).

## Variables de entorno relevantes

- `APP_ENV` — `local`, `production` u otro valor operativo.
- `APP_DEBUG` — controla la salida de depuración.
- `JWT_SECRET` — secreto mínimo de 32 caracteres.
- `JWT_TTL` — duración del token y la sesión en segundos (opcional; predeterminado: 28.800; acepta 300–2.592.000 y usa 28.800 si el valor no es válido).
- `AI_PROVIDER` — `mock` o `openai`.
- `OPENAI_*` — credenciales del proveedor OpenAI.
- `VIDEO_PROVIDER` — `mock`, `googlemeet` o `teams` según el adaptador activo.
- `MAIL_*` — configuración SMTP para notificaciones.
- `DB_PATH` — ruta absoluta a la base SQLite para pruebas y entornos aislados.
- `AUTO_MIGRATE` — `1` (predeterminado) permite a la primera petición aplicar migraciones pendientes; `0` impide que las peticiones web migren y responde HTTP 503 hasta que se ejecute el comando CLI.

## Base de datos SQLite y migraciones

El esquema se administra con migraciones versionadas en `database/migrations/` y
`PRAGMA user_version`. Por compatibilidad, `AUTO_MIGRATE=1` mantiene la actualización
automática en la primera petición que encuentre cambios pendientes. Para desplegar de
forma controlada, configura `AUTO_MIGRATE=0`, ejecuta el comando de migración desde la
línea de comandos y vuelve a habilitarlo solo después de verificar la base:

```bash
php scripts/migrate.php --status
php scripts/migrate.php
```

`--status` es de solo lectura. El comando de ejecución aplica las migraciones aunque
`AUTO_MIGRATE=0` y comprueba `integrity_check` y `foreign_key_check`. Consulta
[`docs/database/MIGRACIONES.md`](docs/database/MIGRACIONES.md) para el procedimiento
recomendado de respaldo, actualización y recuperación.

## Stack tecnológico

- PHP 8.3 + Composer
- SQLite con PDO
- Nginx + PHP-FPM
- Tailwind CSS 4.3
- SSR con front controller en public/index.php
- GitHub Actions para CI/CD

## Instalación local

Requisitos: PHP 8.3 con PDO SQLite, Composer, Node.js 20 y npm.

```bash
composer validate --no-check-publish
composer install --no-interaction --prefer-dist
cp .env.example .env
npm ci
npm run build:css
php scripts/migrate.php --status
php scripts/migrate.php
for file in tests/*.php; do php "$file"; done
```

`composer.json` no instala paquetes de terceros actualmente; `composer.lock` conserva
el estado vacío y por eso Composer Audit no tiene paquetes que evaluar. Las
dependencias del build CSS se fijan en `package-lock.json`; usa `npm audit` para
revisar también las herramientas de desarrollo.

`security_http_smoke.php` inicia un servidor PHP local en `127.0.0.1:8087`;
asegúrate de que el puerto esté disponible. Las pruebas HTTP de interfaz inician
sus propios servidores y usan bases temporales.

## Ejecución

La aplicación se sirve desde la carpeta `public`:

```bash
php -S 127.0.0.1:8087 -t public
```

## Validación recomendada

```bash
find app public resources tests database scripts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
for script in deploy/*.sh; do bash -n "$script"; done
composer validate --no-check-publish
npm audit
npm run build:css
for file in tests/*.php; do php "$file"; done
```

## Seguridad y cumplimiento

La capa de seguridad incluye validación de entradas, JWT con `iss`/`exp`/`jti`,
sesiones persistentes, cookies `HttpOnly` y `SameSite`, bloqueo de redirecciones
abiertas, middleware de origen para CSRF y encabezados CSP, anti-framing y
`nosniff`. HSTS solo se envía en producción sobre HTTPS.

## Alcance funcional pendiente

- El dashboard muestra reservas reales, pero las tarjetas de métricas del tutor aún
  son datos de ejemplo. La interfaz de `/settings` no permite todavía gestionar la
  disponibilidad.
- La recuperación de contraseña y un botón de cierre de sesión en la interfaz no
  están implementados. El backend sí expone `POST /api/auth/logout`.
- Las notificaciones de interfaz permanecen deshabilitadas hasta que existan rutas
  de backend.
