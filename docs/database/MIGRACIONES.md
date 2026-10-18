# Migraciones de base de datos

La base de datos se actualiza automáticamente desde `app/Shared/Database/Database.php`
en la primera petición que abre una conexión después del despliegue. Cada versión se
registra en `schema_migrations` y en `PRAGMA user_version`; las migraciones se ejecutan
en orden y dentro de transacciones. Las versiones que reconstruyen tablas desactivan
las claves foráneas antes de abrir la transacción y verifican `PRAGMA foreign_key_check`
antes de confirmarla.

## Orden y transformaciones

| Versión | Archivo | Cambios |
| --- | --- | --- |
| 001 | `database/migrations/001_initial_schema.php` | Esquema inicial de usuarios, perfiles, reservas, disponibilidad y las tablas auxiliares que ya existían. |
| 002 | `database/migrations/002_security_indexes.php` | Añade importes en centavos e índices de búsqueda de reservas. |
| 003 | `database/migrations/003_catalog.php` | Completa catálogo de materias/niveles y normaliza `tutor_subjects` a `level_id`. Inserta Matemáticas, Física, Química, Inglés, Programación y Estadística; agrega Primaria, Bachillerato, Universidad, Posgrado y `General`. Convierte las materias del JSON de cada perfil en relaciones con nivel `General`; los nombres se comparan sin distinguir mayúsculas y no se duplican. Conserva `tutor_profiles.subjects` por compatibilidad histórica: la columna es obsoleta y no debe usarse para nuevas lecturas/escrituras. Consolida nombres preexistentes equivalentes y remapea sus relaciones. |
| 004 | `database/migrations/004_users_constraints.php` | Normaliza los emails a minúsculas, conserva el usuario más antiguo (por `created_at` y luego `id`) cuando hay emails que solo difieren en mayúsculas, y remapea las referencias conocidas de ese usuario. Conserva sus IDs y contraseñas sin reescribirlas. Reconstruye `users` con roles permitidos, `is_active`, `updated_at` y unicidad `COLLATE NOCASE`. Un role heredado desconocido se convierte a `student`. La migración informa los conteos de duplicados y roles normalizados. |
| 005 | `database/migrations/005_bookings_constraints.php` | Reconstruye `bookings`, conservando IDs, precios en moneda y centavos, contenido y fechas. Añade referencias opcionales al catálogo, motivo de cancelación, actualización, validación de estado/tipo e intervalo. Los estados desconocidos pasan a `pending`; los tipos históricos `presential` y `hybrid` pasan a `presencial` y otros tipos desconocidos a `video`. En duplicados activos del mismo tutor e inicio conserva la fila más antigua y cancela las siguientes con `duplicada (migración)`. Crea índices de búsqueda, unicidad parcial por horario y protección contra autorreserva. Informa los conteos transformados. Si una reserva heredada tiene un intervalo no válido, la migración aborta con su ID en lugar de descartar o inventar sus fechas. |
| 006 | `database/migrations/006_availability_constraints.php` | Añade validación de intervalo e índice por tutor. Las filas inválidas se conservan y desactivan (`is_active = 0`); `end_at` se ajusta al segundo siguiente al inicio para que la fila cumpla el nuevo CHECK. Informa el número transformado. |
| 007 | `database/migrations/007_reviews_notifications.php` | Asegura tablas, índices y triggers de reseñas. Solo permite reseñar reservas completadas y mantiene `rating_avg` y `reviews_count` al insertar, actualizar o borrar una reseña. Añade a `notifications` los campos opcionales `user_id`, `booking_id`, `subject` y `body` que faltaban, sin eliminar el `payload` histórico. |
| 008 | `database/migrations/008_auth_session_indexes.php` | Asegura `auth_sessions` y `login_attempts`, sus índices y los campos opcionales de IP y agente de usuario. Las sesiones persistentes registran/revocan el `jti`; el middleware rechaza tokens sin sesión activa. Cinco fallos por correo e IP dentro de 15 minutos bloquean el acceso durante la ventana, con respuesta genérica. |

## Diferencias respecto a la guía de diseño

El esquema ejecutable de `001`/`002` y las columnas ya consumidas por la aplicación
prevalecen sobre la guía. Por eso se conserva `reviews.score` en vez de renombrarla a
`rating`; también se mantienen `subjects.slug`, `education_levels.slug`, fechas y
campos originales de las tablas, `notifications.payload` y los campos en centavos
de `bookings`. `tutor_subjects.education_level_id`, creado inicialmente, se transforma
a `level_id` para coincidir con el nuevo catálogo. Las columnas añadidas a notificaciones
son opcionales para no invalidar mensajes anteriores.

No se agregan endpoints ni pantallas. La búsqueda catalogada y la validación de
materia/nivel en reservas solo afectan los casos en que se envían filtros/IDs; las
búsquedas sin filtro mantienen el comportamiento previo.

## Reversión y respaldo

No hay migraciones de reversa. Antes de actualizar el servidor se debe ejecutar:

```sh
sudo -n /usr/local/bin/profego-backup
```

Si se requiere volver atrás, restaura la copia completa de la base de datos creada por
`profego-backup` siguiendo el procedimiento operativo de despliegue. No intentes
revertir cambios de esquema borrando tablas o manipulando `user_version`.

## Validación local

`tests/migration_integration.php` crea una base temporal con datos del esquema antiguo,
aplica y repite las migraciones, y comprueba IDs/datos, restricciones, integridad y
revocación de sesiones. El resto de scripts de `tests/` verifica regresiones de la
aplicación; `npm run build:css` comprueba el build de estilos.
