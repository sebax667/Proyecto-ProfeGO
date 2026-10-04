# Changelog de seguridad

## 2026-10-04

### Hallazgo: validación débil de credenciales y duplicados
- Severidad: alta
- Ajuste: validación de email, contraseña, login con latencia uniforme y control de duplicados por email sin distinción de mayúsculas.
- Corregido en: AuthController, AuthService y UserRepository.

### Hallazgo: rutas y middleware con resolución insegura
- Severidad: alta
- Ajuste: coincidencia exacta de rutas, soporte para middlewares como instancias, y control de acceso garantizado en métodos sensibles.
- Corregido en: Router, AuthMiddleware, RoleMiddleware y public/index.php.

### Hallazgo: JWT con secretos no validados y sin claims mínimos
- Severidad: crítica
- Ajuste: validación de secreto, algoritmo HS256, claims iss/exp/jti y base64 URL decode segura.
- Corregido en: JwtService.

### Hallazgo: banco de datos no versionado ni protegible en pruebas
- Severidad: media
- Ajuste: migraciones versionadas, DB_PATH configurable y PRAGMAs de SQLite orientados a producción.
- Corregido en: Database.php y database/migrations.

### Hallazgo: falta de protección CSRF y origen
- Severidad: alta
- Ajuste: middleware de origen y restricciones en POSTs no seguros.
- Corregido en: app/Shared/Middleware/CsrfOriginMiddleware.php.
