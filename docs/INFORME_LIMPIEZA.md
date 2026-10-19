# Informe de auditoría de limpieza y QA

Fecha de auditoría: 2026-10-19
Rama: `chore/limpieza-qa`, creada desde `origin/main` (`f9ff319`).
Alcance: inventario versionado y búsquedas de referencias en código, pruebas,
documentación, scripts y workflows. Los artefactos locales no rastreados se
preservaron; no se leyeron los contenidos de claves, `.env`, bases SQLite ni la guía
local.

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
están cubiertos por `.gitignore`. Se preservaron asimismo archivos locales no
rastreados: una pareja de clave local, una ruta de trabajo bajo `app/`, un esquema
SQL de SQL Server y contenido bajo `docs/`; no se incluyeron en la limpieza.

## Candidatos y hallazgos

| Archivo | Motivo | Evidencia de que no se usa / evidencia relacionada | Riesgo |
| --- | --- | --- | --- |
| `docs/auditoria/despliegue_profego_vps.md` | Documento vacío candidato a retirar o completar. | Mide 2 bytes y contiene solo whitespace; en los 118 archivos rastreados de la base auditada no se encontró referencia por nombre. El historial (`git log --follow`) solo contiene blobs de 1 byte (LF), incluso al crearse el 2026-10-04: nunca tuvo contenido sustantivo. | Medio |
| `docs/auditoria/parches_seguridad_profego.md` | Documento actualmente vacío, candidato a retirar o restaurar su contenido histórico. | Mide 2 bytes y contiene solo whitespace; no se encontró referencia por nombre en los 118 archivos rastreados de la base auditada. El commit `459601c` (2026-10-04 11:23:56 -05:00) eliminó 14 líneas; el commit padre contenía recomendaciones de validación de entrada/login, cookies seguras, router, configuración SQLite y respaldo. | Medio |
| `docs/auditoria/parches_seguridad_profego_2.md` | Documento actualmente vacío, candidato a retirar o restaurar su contenido histórico. | Mide 2 bytes y contiene solo whitespace; no se encontró referencia por nombre en los 118 archivos rastreados de la base auditada. El commit `459601c` (2026-10-04 11:23:56 -05:00) eliminó 11 líneas sobre CSRF/origen, sesiones JWT, `JWT_SECRET`, migraciones idempotentes y smoke tests de rutas protegidas. | Medio |
| `docs/auditoria/schema_profego.sql` | Archivo SQL vacío; posible copia/snapshot de esquema. **Revisar manualmente; no eliminar:** podría ser respaldo o fuente histórica protegida. | Mide 2 bytes; mismo SHA-256 que los otros documentos vacíos. Su nombre y extensión SQL impiden asumir que sea descartable. | Alto |
| `.gitignore` | Ignorar guía local, credenciales locales, respaldos y artefactos generados. | `docs/GUIA_PROFESORES.md` estaba presente localmente sin regla; se agregaron reglas para esa guía, claves comunes, `.bak`/`.backup`, archivos de journal SQLite y logs/build caches. Se verificó con `git check-ignore`. | Bajo |
| `.github/workflows/deploy.yml`, `scripts/deploy/` y `docs/DESPLIEGUE.md` | Preparación y revisión de despliegue, no candidatos a borrar. | La rama despliega el SHA exacto probado por CI, fija `VPS_KNOWN_HOSTS`, restringe sudoers a helpers sin argumentos y añade rollback/health/log checks. El fallo previo en `main` fue `Host key verification failed`; el VPS aún requiere los pasos manuales documentados. | Alto |

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
  También se ignoran claves privadas comunes y nombres locales observados, sin leer
  sus contenidos.
- Se eliminaron los tres Markdown aprobados en el commit separado `53b3fda`
  (`chore: eliminar markdown vacíos de docs/auditoria`). Los contenidos históricos
  de los dos documentos de parches siguen recuperables desde Git; no se alteró
  `schema_profego.sql`.
- Los elementos no rastreados locales (incluidos una carpeta de SQL, rutas bajo
  `docs/docs/` y un archivo de nombre inusual bajo `app/`) se preservaron y no se
  incluyeron en el inventario versionado ni en una propuesta de eliminación.
## QA ejecutado

| Check | Resultado | Evidencia / nota |
| --- | --- | --- |
| Sintaxis PHP | ✅ | `php -l` en los 83 archivos PHP del proyecto, incluidos scripts CLI. |
| Composer validate/install | ✅ | `composer validate --no-check-publish` e `install --no-dev` pasan; se declara `license: proprietary`. |
| Composer audit | N/A | `composer.lock` no contiene paquetes; `composer audit --locked` no tiene dependencias de terceros que analizar. |
| Análisis estático / estilo | N/A | No hay configuración de PHPStan, Psalm, PHP-CS-Fixer, PHPCS ni PHPUnit. |
| Pruebas PHP | ✅ | Los 11 scripts `tests/*.php` pasan, incluido registro/login, catálogo, perfil de tutor, reservas, escapes XSS, autorización y páginas de error. |
| Migración desde base vacía | ✅ | `scripts/migrate.php` aplicó 001–009: `user_version: 9`, `integrity_check: ok`, `foreign_key_check: sin errores`; `--status` no deja pendientes. |
| Migración desde versión anterior | ✅ | `migration_integration.php` migra desde `user_version: 2` hasta 9; `terms_migration_integration.php` prueba 8→9. |
| Tailwind / assets | ✅ | `npm ci`, `npm run build:css` con Tailwind y CLI 4.3.0; las rutas locales de assets existen y los componentes CSS se generan. |
| npm audit | ✅ | `npm audit --audit-level=moderate`: 0 vulnerabilidades tras la actualización compatible de lock y la migración autorizada a Tailwind 4. |
| Secret scanning | ✅ | Sin coincidencias con firmas comunes de claves/tokens en archivos rastreados. Los archivos locales ignorados no se inspeccionaron ni añadieron. |
| Seguridad HTTP | ✅ | El smoke test valida `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y CSP; también prueba CSRF y rutas protegidas. HSTS se emite solo para producción sobre HTTPS y lo valida el smoke test del despliegue cuando el servidor responda. |
| Scripts de despliegue | ✅ | `bash -n` pasa en todos los scripts y helpers, incluido el backup temporal seguro; ShellCheck no está instalado localmente. |
| YAML de Actions | ✅ | Ambos workflows parsean y conservan la clave superior `on`; el workflow de CI pasa para los cambios de despliegue. |
| GitHub Actions | ✅ | `quality` pasó para `a6afaf7` ([run 39304282367](https://github.com/sebax667/Proyecto-ProfeGO/actions/runs/39304282367)); la actualización posterior de este informe es solo documental y también se validará en CI. El deploy previo de `main` falló por `Host key verification failed`; Actions ahora exige una línea ED25519 verificada y SSH estricto. |
| Despliegue real | ⚠️ | El flujo está preparado en repositorio; no se ejecutó contra producción. Falta validar `VPS_KNOWN_HOSTS`, instalar los helpers y actualizar sudoers en el VPS. |

El escaneo de firma no sustituye Gitleaks ni auditoría de secretos en el historial;
Gitleaks corre en CI. No se detectó una configuración de análisis estático PHP que
pudiera ejecutarse.

### Preparación de despliegue añadida a la PR #12

El inventario de rutas rastreadas que sigue corresponde a la base de auditoría de
118 archivos; la preparación posterior de despliegue añade `docs/DESPLIEGUE.md` y
los 13 archivos de `scripts/deploy/`, actualiza los workflows y reemplaza los
helpers antiguos de `deploy/`. No se cambian las migraciones ni `scripts/migrate.php`.
El smoke local usa HTTPS por loopback con el host de `APP_URL`; tanto el runner como
los scripts validan el host antes de pasarlo por SSH/curl. No se probó contra el VPS.

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
