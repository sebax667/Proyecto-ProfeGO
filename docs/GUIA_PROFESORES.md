# ProfeGO · Guía técnica: sesión, interfaz y módulo de profesores

Esta guía responde a tu documento de requerimientos. Parte del código que me mostraste (Router, `index.php`, Auth, JWT). **No he visto** las vistas del dashboard ni los JavaScript de las tarjetas, la campana o el chat: donde lo indico, confirma el diagnóstico con el comando propuesto antes de aplicar el cambio.

## 0. Decisiones previas

1. **Rol `tutor`, no `teacher`.** La base, el código y los `CHECK` usan `student`, `tutor` y `admin`. Renombrarlo rompería datos y pruebas. En la interfaz puedes mostrar "Profesor". Del mismo modo se mantienen `tutor_profiles` (en vez de `teacher_profiles`) y `tutor_availabilities`.
2. **El Router solo admite rutas exactas.** No hay `/tutors/{id}`. Usa `/tutor?id=5` y `/api/tutors/profile?id=5`.
3. **CSP `script-src 'self'`.** No se permiten scripts inline ni librerías desde un CDN. FullCalendar se copia al proyecto (sección 4).
4. **El token vive en una cookie `HttpOnly`.** El JavaScript no puede leerlo y no debe guardarse en `localStorage`. El nombre del usuario se obtiene del servidor.

---

## 1. Sesión: nombre ausente y pérdida de datos

### 1.1 Causas (confirma con estos comandos)

```powershell
git grep -n "renderModule" -- public/index.php
git grep -n "authUser" -- resources/views
```

1. **`$renderModule` no recibe al usuario.** En `index.php`, la ruta `/dashboard` tiene `$authUser`, pero llama a `$renderModule($view, $title)`, y esa función no pasa nada a la vista. Por eso la vista no tiene de dónde sacar el nombre.
2. **El JWT no lleva el nombre.** `AuthService::login` solo firma `user_id`, `email` y `role`. Aunque la vista recibiera el token decodificado, no tendría `name`.
3. **Expiración fija de 1 hora.** La cookie y el JWT duran 3600 s sin renovación, así que a la hora el usuario "se pierde". Además, con `SameSite=Strict`, entrar desde un enlace externo llega sin cookie y redirige al login.
4. **Cambio del hardening:** el login ya no devuelve el token en el JSON. Si alguna vista antigua leía `localStorage` o `response.token`, dejó de funcionar.

### 1.2 Backend: cargar el usuario desde la base y pasarlo a las vistas

`UserRepository`:

```php
public function findById(int $id): ?array
{
    $stmt = $this->db->prepare(
        'SELECT id, name, email, role, avatar_url FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}
```

`public/index.php`:

```php
use App\Modules\Auth\Repositories\UserRepository;

$renderModule = static function (string $view, string $title, array $data = []): string {
    $viewPath = __DIR__ . '/../resources/views/modules/' . $view;
    if (!is_file($viewPath)) {
        http_response_code(404);
        return '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>404</title><h1>404</h1>';
    }
    ob_start();
    require $viewPath;            // la vista ve $data y $title
    return (string) ob_get_clean();
};

$currentUser = static function (?array $authUser): ?array {
    $id = (int) ($authUser['user_id'] ?? 0);
    return $id > 0 ? (new UserRepository())->findById($id) : null;
};

$router->get('/dashboard', static function (array $request, ?array $authUser) use ($renderModule, $currentUser): string {
    $user = $currentUser($authUser);
    if ($user === null) {                      // usuario borrado o token huérfano
        header('Location: /login', true, 302);
        return '';
    }
    $view = in_array($user['role'], ['tutor', 'admin'], true)
        ? 'dashboard/teacher.php'
        : 'dashboard/student.php';
    return $renderModule($view, 'Dashboard | ProfeGo', ['user' => $user]);
}, [AuthMiddleware::class]);
```

Haz lo mismo en `/settings` y `/chat` (pasa `['user' => $user]`).

Vista (`dashboard/student.php` y `teacher.php`):

```php
<?php $user = $data['user']; ?>
<h1>Hola, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h1>
```

Endpoint para que el JavaScript sepa quién es sin leer el token. `AuthController`:

```php
public function me(array $request, ?array $authUser = null): array
{
    $user = (new UserRepository())->findById((int) ($authUser['user_id'] ?? 0));
    if ($user === null) {
        return ['status' => 'unauthorized', 'message' => 'Sesión inválida.'];
    }
    return ['status' => 'success', 'user' => $user];
}
```

Ruta: `$router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);`

### 1.3 Persistencia

- Duración configurable. En `.env`: `JWT_TTL=28800` (8 horas). En `AuthService` usa `(int) (getenv('JWT_TTL') ?: 28800)` como segundo parámetro de `generateToken`, y en el controlador la misma cifra para `expires` de la cookie. Así token y cookie siempre coinciden.
- Opcional (fase posterior): renovación deslizante. Si a un token válido le quedan menos de 15 minutos, `AuthMiddleware` emite uno nuevo y lo reenvía en la cookie. Si usas `auth_sessions`, registra el nuevo `jti` y revoca el anterior.
- No guardes el token ni el perfil en `localStorage`. Si necesitas el nombre en el cliente, llama a `/api/auth/me`.

### 1.4 Cliente: tratar el 401

`public/assets/js/app.js`:

```js
export async function api(path, options = {}) {
  const res = await fetch(path, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
    ...options,
  });
  if (res.status === 401) {
    window.location.assign('/login?redirect=' + encodeURIComponent(window.location.pathname));
    throw new Error('No autenticado');
  }
  return res.json();
}
```

Carga los scripts con `<script type="module" src="/assets/js/app.js"></script>`.

---

## 2. Eventos de la interfaz

Principio: sin `onclick` inline (la CSP los bloquea). Usa enlaces reales cuando se trate de navegar, y delegación de eventos para lo dinámico. Revisa en la consola del navegador (F12) si hay errores de CSP: ahí suele estar la causa de un botón "muerto".

### 2.1 Botón "Explorar"

Debe ser un enlace, no un botón con JavaScript:

```html
<a href="/catalog" class="profego-button">Explorar</a>
```

### 2.2 Tarjetas de tutor

```php
<article class="tutor-card" data-tutor-id="<?= (int) $t['id'] ?>">
  <h3><a href="/tutor?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></a></h3>
  <button type="button" data-action="book" data-tutor-id="<?= (int) $t['id'] ?>">Agendar</button>
</article>
```

El perfil usa la ruta real `/tutor?id=ID`. El diálogo de agendamiento deja que el estudiante elija inicio y fin y envía `POST /api/bookings` con `{tutor_id, starts_at, ends_at}`. **No se consulta `/api/tutors/availability`: esa ruta no existe.** El servidor comprueba la disponibilidad, calcula el precio y obtiene el estudiante de la sesión; nunca se envían `student_id` ni precios desde el navegador. La tarjeta y el diálogo se enlazan con delegación de eventos en `public/assets/js/catalog.js`.

### 2.3 Chat

```html
<a href="/chat" aria-label="Chat">...</a>
```

El enlace `/chat` lleva a la vista protegida del asistente. Desde ahí, SmartMatch enlaza a `#ai-chat-widget` en el catálogo; el widget usa los endpoints existentes `/api/ai/keywords` y `/api/ai/chat` y carga su comportamiento desde un script externo.

### 2.4 Campana de notificaciones

Los endpoints `/api/notifications` y `/api/notifications/read` todavía no están registrados. La campana queda deshabilitada y no realiza solicitudes; no se debe añadir el panel interactivo hasta que exista el backend.

---

## 3. Registro: términos y validación en tiempo real

Cuadro de términos con `<dialog>` y casilla obligatoria:

```html
<label><input type="checkbox" name="terms" required> Acepto los <a href="#" id="open-terms">términos y condiciones</a></label>
<dialog id="terms-dialog"><h2>Términos y condiciones</h2><p>...</p><button type="button" id="close-terms">Cerrar</button></dialog>
<p id="reg-error" role="alert" hidden></p>
```

Validación en el navegador (no sustituye a la del servidor):

```js
const rules = {
  name:     (v) => v.trim().length >= 2 || 'Escribe tu nombre (mínimo 2 caracteres).',
  email:    (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Correo con formato inválido.',
  password: (v) => (v.length >= 10 && /[a-z]/.test(v) && /[A-Z]/.test(v) && /\d/.test(v))
                   || 'Mínimo 10 caracteres, con mayúscula, minúscula y número.',
};
for (const [field, check] of Object.entries(rules)) {
  const input = form.elements[field];
  input.addEventListener('input', () => {
    const r = check(input.value);
    input.setCustomValidity(r === true ? '' : r);
    document.getElementById(field + '-msg').textContent = r === true ? '' : r;
  });
}
```

- **Correo duplicado:** muéstralo tras el envío, con el mensaje que devuelve el servidor. **No crees un endpoint "¿existe este correo?"**: permitiría enumerar usuarios.
- Guarda el aceptado: añade `terms_accepted_at` y `terms_version` en `users` y rechaza el registro si no llegan (`terms === true`).
- El servidor valida de nuevo: nombre 2–100, email válido en minúsculas, contraseña 10–128.

---

## 4. Calendario de clases

FullCalendar se incluye **dentro del repositorio** para cumplir la CSP:

```powershell
npm install fullcalendar --save-dev
Copy-Item node_modules\fullcalendar\index.global.min.js public\assets\vendor\fullcalendar\
Copy-Item node_modules\fullcalendar\locales-all.global.min.js public\assets\vendor\fullcalendar\
```

Haz commit de esos archivos. La vista del dashboard carga los dos scripts y luego `public/assets/js/calendar.js`:

```js
const el = document.getElementById('calendar');
const calendar = new FullCalendar.Calendar(el, {
  locale: 'es',
  initialView: 'dayGridMonth',
  headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
  events: '/api/calendar/events',          // FullCalendar añade ?start=...&end=...
  eventClick: (info) => {
    info.jsEvent.preventDefault();
    const p = info.event.extendedProps;
    document.getElementById('event-detail').textContent =
      info.event.title + ' · ' + p.status + (p.meeting_url ? ' · Enlace disponible' : '');
    if (p.meeting_url) window.open(p.meeting_url, '_blank', 'noopener');
  },
});
calendar.render();
```

Endpoint `GET /api/calendar/events` (rol según el usuario autenticado). Controlador:

```php
public function events(array $request, ?array $authUser = null): array
{
    $uid   = (int) ($authUser['user_id'] ?? 0);
    $start = $this->isoOrNull($request['start'] ?? null);
    $end   = $this->isoOrNull($request['end'] ?? null);
    if ($start === null || $end === null) { return []; }
    $isTutor = ($authUser['role'] ?? '') === 'tutor';
    return $this->repo->eventsFor($uid, $isTutor, $start, $end);   // consultas preparadas
}
```

Consulta del estudiante (la del tutor cambia el `WHERE` a `tp.user_id = :uid` y muestra el nombre del estudiante):

```sql
SELECT b.id, b.starts_at AS start, b.ends_at AS end, b.status, b.title, b.meeting_url,
       u.name AS other_name
FROM bookings b
JOIN tutor_profiles tp ON tp.id = b.tutor_id
JOIN users u ON u.id = tp.user_id
WHERE b.student_id = :uid AND b.starts_at < :end AND b.ends_at > :start
  AND b.status <> 'rejected';
```

Colores por estado en la respuesta (`pending` ámbar, `confirmed` verde, `cancelled` gris, `completed` azul). El endpoint solo devuelve reservas **del usuario autenticado**.

---

## 5. Microsoft Teams, notificaciones y recordatorios

### 5.1 Enlaces de Teams

- **Fase 1 (inmediata):** el tutor pega el enlace de su reunión de Teams al confirmar la clase (`POST /api/tutor/bookings/meeting-link`, solo el tutor dueño, URL `https` válida con dominio `teams.microsoft.com` o `teams.live.com`). Se guarda en `bookings.meeting_url`, con `meeting_provider = 'teams'`.
- **Fase 2:** un `TeamsVideoAdapter` detrás de `VIDEO_PROVIDER=teams` que cree la reunión con Microsoft Graph. Requiere registrar una aplicación en Microsoft Entra ID, permisos concedidos por un administrador del tenant y variables `MS_TENANT_ID`, `MS_CLIENT_ID`, `MS_CLIENT_SECRET` en `.env`. **Revisa la documentación vigente de Microsoft Graph** (reuniones en línea y políticas de acceso de aplicación) antes de implementarlo. Sin credenciales, el sistema debe seguir usando el adaptador de prueba.

### 5.2 Notificaciones

Tipos: `reminder`, `grade`, `promotion`, `booking`, `system`. La campana consume `GET /api/notifications` y marca con `POST /api/notifications/read`. Las promociones las crea un administrador (`POST /api/admin/notifications/broadcast`).

### 5.3 Recordatorios de clases próximas (tarea programada)

`bin/send_reminders.php` busca reservas `confirmed` que empiecen en menos de 24 horas o 1 hora y cuyo recordatorio no se haya enviado, inserta una notificación para el estudiante y otra para el tutor, y marca `reminder_24h_sent_at` o `reminder_1h_sent_at` (así no se repite). En el servidor:

```bash
echo '*/5 * * * * www-data /usr/bin/php /var/www/profego/bin/send_reminders.php >> /var/log/profego-reminders.log 2>&1' | sudo tee /etc/cron.d/profego-reminders
```

### 5.4 Notas académicas

`booking_notes`: el tutor escribe una nota (y opcionalmente una calificación) sobre una clase **completada**, visible para el estudiante. Al guardarla se crea una notificación de tipo `grade`.

---

## 6. Módulo de profesores

### 6.1 Registro del tutor

`POST /api/auth/register-tutor` (con `CsrfOriginMiddleware`):

| Campo | Regla |
|---|---|
| name, email, password, terms | Las mismas del estudiante |
| headline | 5–120 caracteres |
| bio | hasta 2000 caracteres |
| hourly_rate | número entre 0 y un máximo definido |
| modality | `virtual`, `presencial` o `both` |
| city | obligatorio si la modalidad incluye presencial |
| subjects | lista de `{subject_id, level_id}` existentes |
| intro_url | opcional, `https` |

Todo en **una transacción**: crea el usuario con rol `tutor`, el perfil con `is_approved = 0` y las filas de `tutor_subjects`. **Un tutor no aprobado no aparece en el catálogo** hasta que un admin lo apruebe (`POST /api/admin/tutors/approve`). El rol nunca se toma del cuerpo de la petición.

Foto: `POST /api/tutor/photo` (multipart). Máximo 2 MB; tipo detectado con `finfo` (no por la extensión) y solo jpeg, png o webp; el archivo se guarda con nombre aleatorio y la extensión que le corresponde al tipo detectado, en `public/uploads/avatars/`. En Nginx, evita que se ejecute algo ahí:

```nginx
location ^~ /uploads/ { try_files $uri =404; }
```

El prefijo `^~` impide que la regla `\.php$` actúe sobre esa carpeta.

### 6.2 Disponibilidad semanal

Bloques recurrentes en `tutor_weekly_slots` (día 0–6, hora de inicio y fin `HH:MM`) en la zona horaria del tutor (`tutor_profiles.timezone`). `POST /api/tutor/availability/weekly` recibe la lista completa y la reemplaza en una transacción: valida formato, `fin > inicio`, ausencia de solapamientos y un máximo de bloques. `tutor_availabilities` sigue sirviendo para excepciones puntuales. Al reservar, `BookingService` convierte la hora a la zona del tutor y comprueba que cae dentro de un bloque.

### 6.3 Panel del tutor (todo filtrado por el tutor autenticado)

| Necesidad | Endpoint |
|---|---|
| Lista de alumnos | `GET /api/tutor/students` |
| Clases agendadas (lista) | `GET /api/tutor/bookings` |
| Calendario | `GET /api/calendar/events` |
| Ver y editar disponibilidad | `GET` y `POST /api/tutor/availability/weekly` |
| Confirmar, rechazar, completar | `POST /api/bookings/confirm`, `/reject`, `/complete` |
| Enlace de Teams | `POST /api/tutor/bookings/meeting-link` |
| Notas de clase | `POST /api/tutor/bookings/notes` |

Alumnos (solo nombre y número de clases, sin datos de contacto innecesarios):

```sql
SELECT u.id, u.name, COUNT(b.id) AS classes, MAX(b.starts_at) AS last_class
FROM bookings b
JOIN users u ON u.id = b.student_id
WHERE b.tutor_id = :tutor_profile_id AND b.status IN ('confirmed','completed')
GROUP BY u.id
ORDER BY last_class DESC;
```

`:tutor_profile_id` sale de `tutor_profiles.user_id = $authUser['user_id']`, nunca del cliente.

---

## 7. Estructura de archivos recomendada

```
app/Modules/
  Auth/            (AuthController: register, register-tutor, login, logout, me)
  Tutors/
    Controllers/   TutorRegistrationController, TutorDashboardController, TutorAvailabilityController
    Services/      TutorRegistrationService, WeeklyAvailabilityService
    Repositories/  TutorRepository, WeeklySlotRepository
  Calendar/        Controllers/CalendarController, Repositories/CalendarRepository
  Notifications/   Controllers/NotificationController, Services/NotificationService, Repositories/NotificationRepository
  Bookings/        (existente; añade complete, meeting-link y notas)
  Integrations/    Adapters/TeamsVideoAdapter (fase 2)
bin/send_reminders.php
database/migrations/00N_*.php
resources/views/modules/
  tutor/           profile.php, register.php
  dashboard/       student.php, teacher.php
public/assets/js/  app.js, calendar.js, notifications.js, tutor-cards.js, register.js, tutor-dashboard.js
public/assets/vendor/fullcalendar/
```

## 8. Rutas y roles

| Ruta | Método | Acceso |
|---|---|---|
| `/dashboard` | GET | Autenticado |
| `/tutor?id=` | GET | Público (solo tutores aprobados) |
| `/api/auth/register`, `/register-tutor`, `/login` | POST | Público (con límite de peticiones) |
| `/api/auth/me`, `/logout` | GET, POST | Autenticado |
| `/api/tutors/availability?tutor_id=` | GET | Autenticado |
| `/api/calendar/events` | GET | Estudiante o tutor |
| `/api/notifications`, `/read` | GET, POST | Autenticado |
| `/api/tutor/*` | GET, POST | Rol `tutor` |
| `/api/admin/*` | POST | Rol `admin` |

Toda ruta POST lleva `CsrfOriginMiddleware`, y las de rol llevan `new RoleMiddleware([...])`.

## 9. Orden de implementación (una PR por paso, con pruebas)

1. **Sesión y UI:** secciones 1, 2 y 3. Pruebas: el dashboard muestra el nombre, `/api/auth/me` responde 200 con sesión y 401 sin ella, la cookie persiste tras recargar.
2. **Migración y registro de tutor:** sección 6.1 y el SQL de `profego_migracion_profesores.sql`. Pruebas: un tutor no aprobado no sale en el catálogo; el rol no se puede elegir desde el cuerpo.
3. **Disponibilidad y panel del tutor:** secciones 6.2 y 6.3. Pruebas: un tutor no ve alumnos ni reservas de otro; los bloques solapados se rechazan.
4. **Calendario y notificaciones:** secciones 4 y 5.2 a 5.4. Pruebas: el calendario solo devuelve reservas propias; el recordatorio no se duplica.
5. **Teams:** sección 5.1, fase 2, solo cuando tengas las credenciales.

Antes de cada despliegue con migraciones: `sudo -n /usr/local/bin/profego-backup`.