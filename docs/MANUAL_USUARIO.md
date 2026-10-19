# Manual del usuario

## Registro

1. Entra a /register.
2. Completa nombre, email y contraseña con al menos 10 caracteres.
3. Lee y acepta los términos para habilitar el registro.
4. El sistema normaliza el email a minúsculas y rechaza duplicados.

## Inicio de sesión

1. Entra a /login e inicia sesión con el email y contraseña registrados.
2. El servidor crea una sesión segura y redirige al dashboard según el rol.
3. `/dashboard` requiere una sesión activa; un tutor solo ve sus propias reservas
   y un estudiante ve las reservas asociadas a su cuenta.

## Búsqueda y catálogo

- Filtra por materia, nivel educativo, ciudad o modalidad.
- La búsqueda ordena por reputación del tutor cuando está disponible.
- Los resultados se muestran en la vista pública del catálogo.
- Abre el nombre o perfil de un tutor para visitar `/tutor?id=ID`.

## Reserva

1. El estudiante inicia sesión, busca un tutor y elige inicio y fin de la sesión.
2. La solicitud se envía a `POST /api/bookings`; el servidor obtiene el estudiante
   y la tarifa desde la sesión y el perfil del tutor.
3. La reserva se crea en estado `pending`.
4. El tutor puede confirmar o rechazar la solicitud mediante las rutas protegidas.
5. El estudiante o el tutor pueden cancelar solo cuando el estado de la reserva
   lo permite.

## Disponibilidad del tutor

La base de datos y el backend validan las franjas de disponibilidad y evitan
solapamientos. La pantalla `/settings` es actualmente informativa y todavía no
permite que el tutor gestione sus franjas desde la interfaz.

## Reseñas

El backend protege las reseñas para reservas completadas; la interfaz para crear y
consultar reseñas no forma parte del flujo actual.

## Cierre de sesión

El backend invalida la sesión mediante `POST /api/auth/logout` con la cookie de
sesión y un origen permitido. La interfaz todavía no ofrece un control visible de
cierre de sesión.
