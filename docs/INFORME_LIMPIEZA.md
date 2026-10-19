# Informe de auditoría de limpieza y QA

Fecha de auditoría: 2026-10-19
Rama: `chore/limpieza-qa`, creada desde `origin/main` (`f9ff319`).
Alcance: archivos rastreados por Git y referencias en código, pruebas, documentación,
scripts y workflows. No se inspeccionó el contenido de archivos locales no rastreados.

## Inventario

El repositorio contiene 118 archivos rastreados: 83 PHP, 6 JavaScript, 3 JSON,
2 CSS, 2 PowerShell, 3 scripts shell, 3 workflows/configuraciones YAML, 11 Markdown,
1 SQL, 1 `.env.example`, 1 `.gitignore`, 1 `composer.lock` y 1 `tailwind.config.js`.
El inventario completo por ruta está al final del informe.

Directorios principales incluidos:

| Ruta | Archivos rastreados |
| --- | ---: |
| `app/` | 39 |
| `database/` | 10 |
| `deploy/` | 5 |
| `docs/` | 8 |
| `public/` | 8 |
| `resources/` | 21 |
| `scripts/` | 2 |
| `tests/` | 12 |
| Raíz y `.github/` | 13 |

Los directorios locales `vendor/` y `node_modules/`, `.env` y `database.sqlite` no
son archivos rastreados. `.env`, `database.sqlite`, `vendor/` y `node_modules/`
están cubiertos por `.gitignore`. Se preservaron los archivos y rutas locales
no rastreados sin inspeccionar sus contenidos.

## Candidatos y hallazgos

| Archivo | Motivo | Evidencia de que no se usa / evidencia relacionada | Riesgo |
| --- | --- | --- | --- |
| `docs/auditoria/despliegue_profego_vps.md` | Documento vacío candidato a retirar o completar. | Mide 2 bytes y contiene solo whitespace; en los 118 archivos rastreados de la base auditada no se encontró referencia por nombre. El historial (`git log --follow`) solo contiene blobs de 1 byte (LF), incluso al crearse el 2026-10-04: nunca tuvo contenido sustantivo. | Medio |
| `docs/auditoria/parches_seguridad_profego.md` | Documento actualmente vacío, candidato a retirar o restaurar su contenido histórico. | Mide 2 bytes y contiene solo whitespace; no se encontró referencia por nombre en los 118 archivos rastreados de la base auditada. El commit `459601c` (2026-10-04 11:23:56 -05:00) eliminó 14 líneas; el commit padre contenía recomendaciones de validación de entrada/login, cookies seguras, router, configuración SQLite y respaldo. | Medio |
| `docs/auditoria/parches_seguridad_profego_2.md` | Documento actualmente vacío, candidato a retirar o restaurar su contenido histórico. | Mide 2 bytes y contiene solo whitespace; no se encontró referencia por nombre en los 118 archivos rastreados de la base auditada. El commit `459601c` (2026-10-04 11:23:56 -05:00) eliminó 11 líneas sobre CSRF/origen, sesiones JWT, `JWT_SECRET`, migraciones idempotentes y smoke tests de rutas protegidas. | Medio |
| `docs/auditoria/schema_profego.sql` | Archivo SQL vacío; posible copia/snapshot de esquema. **Revisar manualmente; no eliminar:** podría ser respaldo o fuente histórica protegida. | Mide 2 bytes; mismo SHA-256 que los otros documentos vacíos. Su nombre y extensión SQL impiden asumir que sea descartable. | Alto |
| `.gitignore` | Añadir una regla explícita para la guía local que no debe publicarse. | `docs/GUIA_PROFESORES.md` está presente localmente y aparece como no rastreada; la búsqueda de la ruta en `.gitignore` no encuentra regla. | Bajo |
| `deploy/remote_deploy.sh`, `.github/workflows/deploy.yml` y documentación de despliegue | Revisar/actualizar antes de declarar listo el despliegue. No son candidatos a borrar. | En `main`, el script actual hace backup, `git reset --hard origin/main`, instala dependencias, corrige permisos y recarga FPM; no fija `AUTO_MIGRATE=0` ni ejecuta `scripts/migrate.php`. El workflow de `main` falló porque no reconocía la clave ED25519 del host. La PR #11 añade validación y migración controlada y su check `quality` pasó, pero sigue abierta y requiere preparación del VPS descrita en ella. | Alto |

### Referencias, dependencias y código conservable

- No se detectaron dependencias de Composer de terceros sin uso: `composer.json`
  solo declara PHP y el autoload PSR-4 de `App\\`.
- `tailwindcss` está usado por `build:css` y `watch:css`; `app.css` se enlaza desde
  las vistas activas, por lo que no es un asset huérfano.
- Los JavaScript están enlazados desde las vistas o importados por otro asset:
  `catalog.js` importa `app.js`; `login.js`, `register.js` y `ai-widget.js` se
  cargan desde sus vistas/componentes.
- Las vistas de dashboards, chat y ajustes se seleccionan desde `public/index.php`;
  las vistas de errores se resuelven dinámicamente desde `ErrorPage`. Los parciales
  están incluidos por layouts y vistas de catálogo/perfil.
- No se encontraron imágenes rastreadas ni nombres de backup `*_old`, `*_backup`,
  `*-copy` o `.bak`. La búsqueda de `TODO`, `FIXME`, `XXX` y `deprecated` en
  `app/`, `public/` y `resources/` no dio resultados.
- No se marcaron funciones como eliminables: el router invoca handlers mediante
  reflexión y hay callbacks/rutas dinámicas; una búsqueda de llamadas por nombre
  no sería evidencia suficiente.
- No se encontraron referencias a los cuatro documentos vacíos por nombre.
  La búsqueda se realizó en los 118 archivos rastreados de la base auditada,
  incluyendo `README.md`, `docs/`, `scripts/`, `deploy/`, `tests/` y `.github/`.
  El propio informe nombra los candidatos para registrar los hallazgos.
- Historial: los dos documentos `parches_seguridad` sí tuvieron contenido y se
  vaciaron en `459601c` el 2026-10-04 a las 11:23:56 -05:00. Los temas y líneas
  eliminados se resumen en la tabla; se pueden recuperar íntegramente del historial.
  `despliegue_profego_vps.md` siempre fue una línea vacía.

## Elementos protegidos y límites de esta fase

- No se modificó ni se propone borrar migraciones, `scripts/migrate.php`,
  `.env.example`, configuración de Nginx, workflows, bases SQLite ni backups.
- `docs/GUIA_PROFESORES.md` permanece local y sin rastrear. No se inspeccionó ni
  versionó. La regla explícita `docs/GUIA_PROFESORES.md` se añadió a `.gitignore`.
- Se eliminaron los tres Markdown aprobados en el commit separado `53b3fda`
  (`chore: eliminar markdown vacíos de docs/auditoria`). Los contenidos históricos
  de los dos documentos de parches siguen recuperables desde Git; no se alteró
  `schema_profego.sql`.
- Los elementos no rastreados locales (incluidos una carpeta de SQL, rutas bajo
  `docs/docs/` y un archivo de nombre inusual bajo `app/`) se preservaron y no se
  incluyeron en el inventario versionado ni en una propuesta de eliminación.
- Esta fase no ejecutó pruebas, builds, auditorías de dependencias ni escaneo de
  secretos: se reserva para QA después de resolver los elementos de riesgo medio/alto.

## Inventario completo de rutas rastreadas

```text
.env.example
.github/dependabot.yml
.github/workflows/ci.yml
.github/workflows/deploy.yml
.gitignore
README.md
SECURITY.md
app/Modules/AIEngine/Adapters/MockAIAssistantAdapter.php
app/Modules/AIEngine/Adapters/OpenAIAssistantAdapter.php
app/Modules/AIEngine/Contracts/AIAssistantInterface.php
app/Modules/AIEngine/Controllers/AIController.php
app/Modules/Auth/Controllers/AuthController.php
app/Modules/Auth/Repositories/UserRepository.php
app/Modules/Auth/Services/AuthService.php
app/Modules/Bookings/Controllers/BookingController.php
app/Modules/Bookings/DTOs/CreateBookingDTO.php
app/Modules/Bookings/Enums/BookingStatus.php
app/Modules/Bookings/Events/BookingCreatedEvent.php
app/Modules/Bookings/Exceptions/BookingException.php
app/Modules/Bookings/Listeners/SendEmailNotificationListener.php
app/Modules/Bookings/Repositories/BookingRepository.php
app/Modules/Bookings/Services/BookingService.php
app/Modules/Integrations/Adapters/MockVideoAdapter.php
app/Modules/Integrations/Contracts/VideoConferenceAdapterInterface.php
app/Modules/SearchReputation/Controllers/TutorCatalogController.php
app/Modules/SearchReputation/DTOs/SearchFiltersDTO.php
app/Modules/SearchReputation/Repositories/SearchTutorRepository.php
app/Shared/Config/Environment.php
app/Shared/DTOs/RegisterUserDTO.php
app/Shared/DTOs/TutorProfileDTO.php
app/Shared/DTOs/UserDTO.php
app/Shared/Database/Database.php
app/Shared/Database/DatabaseBusyException.php
app/Shared/Database/MigrationPendingException.php
app/Shared/Enums/Modality.php
app/Shared/Enums/UserRole.php
app/Shared/Events/Event.php
app/Shared/Events/EventDispatcher.php
app/Shared/Events/Listener.php
app/Shared/Http/ErrorPage.php
app/Shared/Http/Router.php
app/Shared/Middleware/AuthMiddleware.php
app/Shared/Middleware/CsrfOriginMiddleware.php
app/Shared/Middleware/RoleMiddleware.php
app/Shared/Security/JwtService.php
app/Support/helpers.php
composer.json
composer.lock
database/migrations/001_initial_schema.php
database/migrations/002_security_indexes.php
database/migrations/003_catalog.php
database/migrations/004_users_constraints.php
database/migrations/005_bookings_constraints.php
database/migrations/006_availability_constraints.php
database/migrations/007_reviews_notifications.php
database/migrations/008_auth_session_indexes.php
database/migrations/009_terms_acceptance.php
database/seed_tutors.php
deploy/PRODUCTION_PHP83.md
deploy/nginx/profego
deploy/remote_deploy.sh
deploy/setup_production.sh
deploy/smoke_test.sh
design-specs.md
docs/MANUAL_SISTEMA.md
docs/MANUAL_USUARIO.md
docs/auditoria/CHANGELOG_SEGURIDAD.md
docs/auditoria/despliegue_profego_vps.md
docs/auditoria/parches_seguridad_profego.md
docs/auditoria/parches_seguridad_profego_2.md
docs/auditoria/schema_profego.sql
docs/database/MIGRACIONES.md
package-lock.json
package.json
public/assets/css/app.css
public/assets/js/ai-widget.js
public/assets/js/app.js
public/assets/js/catalog.js
public/assets/js/login.js
public/assets/js/register.js
public/index.php
public/smoke_test.ps1
resources/css/input.css
resources/views/components/ai-widget.php
resources/views/components/profego-brand.php
resources/views/errors/401.php
resources/views/errors/403.php
resources/views/errors/404.php
resources/views/errors/500.php
resources/views/errors/503.php
resources/views/layouts/app.php
resources/views/modules/auth/login.php
resources/views/modules/auth/register.php
resources/views/modules/chat/index.php
resources/views/modules/dashboard/student.php
resources/views/modules/dashboard/teacher.php
resources/views/modules/search/catalog.php
resources/views/modules/search/tutor.php
resources/views/modules/settings/index.php
resources/views/partials/footer.php
resources/views/partials/navbar.php
resources/views/partials/navigation.php
resources/views/partials/sidebar.php
scripts/migrate.php
scripts/validate_project.ps1
tailwind.config.js
tests/ai_integration.php
tests/auth_integration.php
tests/booking_authz_integration.php
tests/booking_integration.php
tests/fixtures/migrations/001_count_application.php
tests/migration_integration.php
tests/migration_runtime.php
tests/router_error_pages.php
tests/security_http_smoke.php
tests/seed_tutors_integration.php
tests/session_ui_integration.php
tests/terms_migration_integration.php
```
