# Parches de seguridad ProfeGo 2

## Continuidad de la auditoría

- Añadir middleware de origen para proteger llamadas cross-site.
- Asegurar rutas críticas con sesión y cookie JWT revocadas.
- Validar la presencia de JWT_SECRET en entorno de producción.
- Usar migraciones idempotentes y evitar cambios destructivos.

## Nota de despliegue

No se debe exponer el contenido de .env ni la base de datos a rutas públicas. Los smoke tests deben comprobar las respuestas 403/404 esperadas.
