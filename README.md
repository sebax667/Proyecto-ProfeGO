# ProfeGo

ProfeGo es una plataforma SSR para conectar estudiantes con tutores, consultar
perfiles, usar SmartMatch y gestionar reservas de tutorías. El proyecto está
organizado como un monolito modular en PHP, con separación por dominios y un
front controller único.

## Stack tecnológico

- **PHP 8.3** con tipado estricto y Composer.
- **SQLite** mediante PDO; la estructura se crea automáticamente al iniciar la
  aplicación.
- **Nginx + PHP-FPM** para producción.
- **Tailwind CSS 3.4** compilado localmente con Node.js/npm.
- **JavaScript nativo** para los formularios asíncronos y el widget de IA.
- **GitHub Actions** para lint, pruebas de integración y validación de scripts.

## Estructura del repositorio

```text
.
├── app/
│   ├── Modules/             # Dominios: Auth, Bookings, AIEngine y catálogo
│   ├── Shared/              # Router, middleware, eventos, DTOs y seguridad
│   └── Support/             # Helpers compartidos
├── database/
│   ├── database.sqlite      # Base local ignorada por Git
│   └── seed_tutors.php      # Datos de tutores para desarrollo
├── deploy/
│   ├── nginx/profego        # Plantilla de virtual host para Nginx
│   ├── setup_production.sh  # Preparación de un VPS Ubuntu
│   └── smoke_test.sh        # Smoke test para Linux/CI
├── public/
│   ├── index.php            # Front controller y registro de rutas
│   └── assets/css/app.css   # CSS compilado que consume el navegador
├── resources/
│   ├── css/input.css        # Entrada Tailwind y estilos propios
│   └── views/               # Layouts, partials y vistas SSR por módulo
├── scripts/                 # Validaciones auxiliares para Windows
├── tests/                   # Pruebas de integración Booking y AI
├── composer.json            # Autoload PSR-4 y requisitos PHP
├── package.json             # Scripts y dependencia de Tailwind
├── tailwind.config.js       # Rutas escaneadas por Tailwind
└── .env.example             # Variables de entorno de referencia
```

`vendor/` y `node_modules/` son dependencias instaladas localmente y no deben
versionarse. Los archivos `.env` y las bases SQLite locales también están
ignorados para evitar publicar secretos o datos de desarrollo.

## Instalación local

### Requisitos

- PHP 8.3 con `pdo_sqlite`, `sqlite3` y `curl`.
- Composer 2.
- Node.js 18+ y npm.
- Git.
- Laragon (Windows) o Nginx/PHP-FPM (Linux).

### 1. Clonar el proyecto

```bash
git clone https://github.com/sebax667/Proyecto-ProfeGO.git
cd Proyecto-ProfeGO
```

### 2. Instalar dependencias PHP

```bash
composer install
```

Si Composer no está en el `PATH` en Windows con Laragon, puede ejecutarse con
el PHAR incluido en la instalación:

```powershell
php C:\laragon\bin\composer\composer.phar install
```

### 3. Crear la configuración de entorno

```bash
cp .env.example .env
```

En PowerShell:

```powershell
Copy-Item .env.example .env
```

Edita `.env` y establece un secreto aleatorio para `JWT_SECRET`. Para usar el
adaptador local de IA, conserva `AI_PROVIDER=mock`. `OPENAI_API_KEY` solo es
necesaria si se selecciona explícitamente `AI_PROVIDER=openai`.

### 4. Instalar y compilar Tailwind

```bash
npm install
npm run build:css
```

Durante el desarrollo puede dejarse un watcher activo:

```bash
npm run watch:css
```

El resultado se genera en `public/assets/css/app.css`.

### 5. Preparar SQLite y datos de ejemplo

La aplicación crea o actualiza las tablas al abrir la conexión por primera
vez. Para cargar tutores de demostración:

```bash
php database/seed_tutors.php
```

El seeder reemplaza los perfiles de tutores existentes. No lo ejecutes contra
una base de datos de producción sin una copia de seguridad.

## Ejecución local

### Laragon en Windows

Configura el proyecto en Laragon para que el **Document Root** del sitio
apunte a `C:\laragon\www\sistema-app\public`. Inicia Apache/Nginx y PHP desde
el panel de Laragon y abre:

```text
http://127.0.0.1:8087/catalog
```

El puerto puede cambiar según la configuración local de Laragon.

### Nginx en Linux

Usa la plantilla [`deploy/nginx/profego`](deploy/nginx/profego), ajusta la ruta
de `root`, el dominio y el socket de PHP-FPM, y valida la configuración:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Para preparar permisos, `.env`, SQLite y dependencias de producción:

```bash
sudo APP_DIR=/var/www/profego bash deploy/setup_production.sh
```

## Rutas principales

| Ruta | Método | Descripción |
| --- | --- | --- |
| `/catalog` | GET | Catálogo público de tutores y SmartMatch |
| `/login` | GET | Formulario SSR de inicio de sesión |
| `/register` | GET | Formulario SSR de registro |
| `/dashboard` | GET | Dashboard protegido por autenticación |
| `/chat` | GET | Vista del módulo de chat |
| `/settings` | GET | Configuración protegida por autenticación |
| `/api/auth/login` | POST | Autenticación y cookie JWT |
| `/api/auth/register` | POST | Registro público como estudiante |
| `/api/ai/keywords` | GET | Palabras clave disponibles |
| `/api/ai/chat` | POST | Recomendación de tutores |
| `/api/bookings` | POST | Creación de reservas autenticadas |

Las rutas web protegidas redirigen a `/login`; las rutas API protegidas
responden JSON con HTTP 401.

## Pruebas y validación

### Lint de PHP

En Linux/macOS:

```bash
find app public resources tests database -type f -name '*.php' -print0 |
  xargs -0 -n1 php -l
```

En Windows puede usarse:

```powershell
.\scripts\validate_project.ps1
```

### Pruebas de integración

```bash
php tests/booking_integration.php
php tests/ai_integration.php
```

### Validación de scripts de despliegue

```bash
bash -n deploy/setup_production.sh deploy/smoke_test.sh
```

### Smoke test HTTP

Con la aplicación ejecutándose:

```bash
BASE_URL=http://127.0.0.1:8087 bash deploy/smoke_test.sh
```

El smoke test verifica `/catalog`, `/api/ai/keywords` y una consulta real a
`/api/ai/chat`. En Windows también está disponible:

```powershell
.\public\smoke_test.ps1
```

### Ejecutar toda la validación de CI localmente

El workflow de GitHub Actions (`.github/workflows/ci.yml`) prueba PHP 8.2 y
8.3, valida la sintaxis PHP y Bash, instala Composer y ejecuta las dos pruebas
de integración. Para reproducir sus pasos, asegúrate de tener disponibles
las extensiones `sqlite3`, `pdo_sqlite`, `curl` y `mbstring`.

## Flujo de trabajo recomendado

1. Crear una rama desde `main`.
2. Instalar dependencias y copiar `.env.example` a `.env`.
3. Ejecutar `npm run build:css` después de modificar estilos.
4. Ejecutar lint, pruebas de integración y smoke test.
5. Revisar que no se hayan añadido `.env`, SQLite, `vendor/` o `node_modules/`.
6. Abrir un pull request hacia `main`.
