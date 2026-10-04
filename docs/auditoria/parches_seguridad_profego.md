# Parches de seguridad ProfeGo

Este documento recoge los cambios mínimos necesarios para cerrar los riesgos detectados en la capa de autenticación, rutas, JWT y base de datos.

## Cambios principales

- Validación estricta de nombre, email y contraseña.
- Login con respuesta temporal constante y sin fuga de token en el cuerpo del JSON.
- Cookies seguras con HttpOnly y SameSite=Strict.
- Router exacto y protección global ante errores con logging.
- Base de datos configurable por DB_PATH y preparada con pragmas de SQLite seguros.

## Recomendación

Aplicar estos ajustes antes de cualquier despliegue a producción, y mantener siempre una copia de seguridad del SQLite.
