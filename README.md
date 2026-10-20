# ProfeGo

<div align="center">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php" alt="PHP 8.3" />
  <img src="https://img.shields.io/badge/SQLite-Database-003B57?style=for-the-badge&logo=sqlite" alt="SQLite" />
  <img src="https://img.shields.io/badge/Tailwind-4.3-38B2AC?style=for-the-badge&logo=tailwindcss" alt="Tailwind CSS" />
  <img src="https://img.shields.io/badge/Deploy-CI%2FCD-0A0A0A?style=for-the-badge&logo=githubactions" alt="GitHub Actions" />
</div>

Plataforma moderna para conectar estudiantes con tutores expertos, facilitar reservas y ofrecer una experiencia educativa más inteligente, segura y escalable.

## ✨ ¿Qué hace ProfeGo?

ProfeGo es una solución web orientada a la educación que permite:

- Buscar y filtrar tutores por especialidad, perfil y reputación
- Reservar clases de manera sencilla y ordenada
- Gestionar sesiones, confirmaciones y seguimiento académico
- Aprender o obtener apoyo con recomendaciones impulsadas por IA
- Operar en un entorno seguro y listo para producción

Diseñado como un monolito modular, combina velocidad de desarrollo, claridad de estructura y facilidad de mantenimiento sin perder robustez operativa.

## 🚀 Funcionalidades clave

### Para estudiantes
- Catálogo de tutores con filtros por disciplina y perfil
- Reserva de sesiones con flujo claro
- Reputación visible y sistema de valoraciones
- Experiencia centrada en encontrar ayuda rápida y confiable

### Para tutores
- Perfil profesional con especialidades y métricas
- Gestión de disponibilidad y agenda
- Seguimiento de reservas y actividad
- Base preparada para extender funcionalidades del negocio

### Para operación y administración
- Arquitectura modular por dominio
- Seguridad con autenticación y sesiones
- Migraciones versionadas de base de datos
- CI/CD con despliegue automatizado
- Preparado para entornos de staging y producción

## 🧠 Cómo funciona

```text
Estudiante -> Busca tutor -> Revisa perfil -> Reserva clase -> Sigue sesión -> Recibe apoyo
```

La plataforma está pensada para que la experiencia fluya de forma intuitiva, reduciendo fricción en el proceso de contratación y gestión educativa.

## 🏗️ Arquitectura del proyecto

ProfeGo está separado por módulos funcionales:

- `app/Modules/Auth` — autenticación, sesiones y seguridad
- `app/Modules/Bookings` — reservas y gestión de citas
- `app/Modules/SearchReputation` — catálogo, filtros y reputación
- `app/Modules/AIEngine` — soporte inteligente y adaptadores
- `app/Modules/Integrations` — videollamadas y servicios externos
- `app/Shared` — utilidades, middleware y servicios transversales

Estructura general:

- `public/` — punto de entrada de la aplicación
- `resources/` — vistas, componentes y estilos
- `database/` — SQLite y migraciones
- `deploy/` — scripts y configuración de despliegue
- `docs/` — documentación técnica y operativa
- `tests/` — validaciones funcionales y smoke tests

## 🛠️ Stack tecnológico

- PHP 8.3
- Composer
- SQLite + PDO
- Tailwind CSS 4.3
- Nginx + PHP-FPM
- GitHub Actions
- SSR con front controller en `public/index.php`

## ⚡ Inicio rápido

### Requisitos

- PHP 8.3+
- Composer
- Node.js 20+
- npm
- PDO SQLite habilitado

### Instalación

```bash
composer validate --no-check-publish
composer install --no-interaction --prefer-dist
npm ci
```

### Variables de entorno

```bash
cp .env.example .env
```

Principales variables:

- `APP_ENV` — entorno de ejecución
- `APP_DEBUG` — depuración
- `APP_URL` — URL pública de la aplicación
- `JWT_SECRET` — secreto mínimo de 32 caracteres
- `JWT_TTL` — duración de sesión/token
- `AI_PROVIDER` — proveedor de IA (`mock` o `openai`)
- `DB_PATH` — ruta absoluta a la base SQLite
- `AUTO_MIGRATE` — control de migración automática

### Base de datos y migraciones

```bash
php scripts/migrate.php --status
php scripts/migrate.php
```

### Compilar estilos

```bash
npm run build:css
```

### Ejecutar localmente

```bash
php -S 127.0.0.1:8087 -t public
```

Abrir en el navegador:

```text
http://127.0.0.1:8087
```

## 🔒 Seguridad

ProfeGo incorpora medidas orientadas a producción:

- Validación y control de entradas
- Autenticación con JWT y sesiones seguras
- Cookies `HttpOnly`, `SameSite` y `Secure` en producción
- Middleware de origen y protección CSRF
- Encabezados de seguridad: CSP, anti-framing y `nosniff`
- Validación de redirecciones y rutas sensibles
- Smoke tests y validación HTTP de seguridad

## 🌍 Despliegue

El flujo de despliegue está pensado para ser seguro, reproducible y automatizable:

1. Validación automática con GitHub Actions
2. Verificación de dependencias y sintaxis
3. Control del entorno de producción
4. Backup de SQLite antes de cambios relevantes
5. Aplicación controlada de migraciones
6. Validación de salud y rollback automático ante fallos

Documentación operativa: [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md)

## ✅ Estado actual

El proyecto ya tiene una base sólida en:

- autenticación y gestión de sesión,
- reservas y flujo académico,
- seguridad HTTP,
- migraciones y estructura modular.

Todavía hay mejoras pendientes en algunos aspectos del producto, por ejemplo:

- disponibilidad desde `/settings`
- recuperación de contraseña en la interfaz
- cierre de sesión visual
- notificaciones de UI a nivel backend
- métricas del dashboard con datos de ejemplo en ciertos escenarios

## 📚 Documentación relevante

- [`docs/MANUAL_SISTEMA.md`](docs/MANUAL_SISTEMA.md)
- [`docs/MANUAL_USUARIO.md`](docs/MANUAL_USUARIO.md)
- [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md)
- [`docs/database/MIGRACIONES.md`](docs/database/MIGRACIONES.md)
- [`SECURITY.md`](SECURITY.md)

## 🧪 Validación recomendada

```bash
find app public resources tests database scripts -type f -name '*.php' -print0 | xargs -0 -n1 php -l
for script in deploy/*.sh; do bash -n "$script"; done
composer validate --no-check-publish
npm audit
npm run build:css
for file in tests/*.php; do php "$file"; done
```

## 📁 Estructura del repositorio

```text
.
├── app/                     # Lógica de negocio y módulos
├── database/                # SQLite y migraciones
├── deploy/                  # Scripts de despliegue
├── docs/                    # Documentación técnica
├── public/                  # Front controller y assets públicos
├── resources/               # Vistas y estilos
├── scripts/                 # Utilidades operativas
├── tests/                   # Pruebas de integración
├── composer.json            # Dependencias PHP
├── package.json             # Dependencias frontend
├── README.md                # Documentación principal
├── SECURITY.md              # Política de seguridad
├── tailwind.config.js       # Configuración de Tailwind
├── .env.example             # Plantilla de variables de entorno
└── .gitignore              # Exclusiones del proyecto
```

## 🤝 Contribución

Las contribuciones son bienvenidas. Para colaborar:

1. Crear una rama de trabajo
2. Mantener cambios pequeños y enfocados
3. Ejecutar validaciones antes de enviar cambios
4. Documentar nuevos cambios funcionales y operativos

## 📜 Licencia

Proyecto bajo licencia propietaria definida en `composer.json`, conforme a la política interna del repositorio.
