# Despliegue seguro de ProfeGO

Procedimiento para producción en Ubuntu 22.04, Nginx, PHP 8.3 y SQLite. El
despliegue de GitHub Actions instala un SHA exacto que ya pasó CI; no despliega
pull requests.

## Secretos y variables de GitHub

En el environment protegido `production`, configura estos **repository/environment
secrets** (no guardes sus valores en el repositorio):

| Nombre | Contenido |
| --- | --- |
| `VPS_HOST` | Hostname o IPv4 pública del VPS, sin esquema ni puerto. |
| `VPS_USER` | Usuario SSH de despliegue, por ejemplo `deploy`. |
| `VPS_SSH_KEY` | Clave privada SSH dedicada al workflow. |
| `VPS_KNOWN_HOSTS` | Línea completa de host key ED25519 verificada; nunca una clave obtenida automáticamente por Actions. |

Configura `APP_URL` como **environment variable** `https://...` del environment
`production`, exactamente como origen sin ruta, puerto ni barra final (por ejemplo,
`https://profego.example`). El pipeline requiere HTTPS, HTTP 200 en `/catalog`,
respuestas 403/404 para rutas protegidas y encabezados de seguridad.

## Preparación única en el VPS

Ejecuta estos pasos como administrador directamente en el VPS o en una sesión
administrativa confiable. No los ejecutes desde el job de Actions.

### Paquetes y permisos del checkout

Instala PHP-FPM/CLI 8.3 con PDO SQLite, Composer, Node.js 20/npm, Git, `sqlite3`,
Nginx, `curl` y `sudo`. El checkout debe existir en `/var/www/profego`, usar el
remote del repositorio correcto y ser propiedad del usuario SSH `deploy`, que
actualiza el código. Añade `deploy` al grupo de lectura de PHP y vuelve a iniciar su
sesión con `sudo usermod -aG www-data deploy`.

El directorio `database/` permite escritura a `deploy` para que Git actualice las
migraciones y a `www-data` para que SQLite cree WAL/journal; el aprovisionamiento
lo fija como `deploy:www-data` modo `0770`. `.env` y `database/database.sqlite`
permanecen propiedad de `www-data`. La base actual que inicia la actualización debe
tener `PRAGMA user_version = 2`.

Si necesitas el aprovisionamiento del repositorio, ejecútalo una vez como root
después de crear el `.env` y el checkout:

```bash
cd /var/www/profego
sudo APP_DIR=/var/www/profego APP_USER=www-data APP_GROUP=www-data \
     DEPLOY_USER=deploy PHP_FPM_SERVICE=php8.3-fpm \
     bash deploy/setup_production.sh
```

El script conserva el dueño del checkout, fija el grupo de lectura `www-data`,
deja el directorio de SQLite escribible por el deploy y PHP-FPM, e instala los
helpers root-owned. Composer se ejecuta con el usuario de despliegue.

Comprueba PHP-FPM y la configuración de Nginx:

```bash
php -v
php -m | grep -E 'PDO|pdo_sqlite|sqlite3'
sudo nginx -t
sudo systemctl status php8.3-fpm nginx --no-pager
```

### Instalar helpers root-owned

Revisa los scripts de `scripts/deploy/` en el commit que se aprobará. Desde el
checkout validado:

```bash
cd /var/www/profego
sudo bash scripts/deploy/install_helpers.sh
```

El instalador no acepta argumentos y copia los helpers fijos a
`/usr/local/sbin/profego-*` con dueño `root:root` y modo `0755`. Los helpers
privilegiados rechazan cualquier argumento. Si cambian en una actualización futura,
un administrador debe reinstalarlos desde el código revisado antes de desplegar esa
versión.

### Instalar y validar sudoers

Reemplaza el único token `DEPLOY_USER` del ejemplo por el usuario SSH real; no uses
comodines ni amplíes la lista:

```bash
sudo visudo -f /etc/sudoers.d/profego-deploy
```

Copia en ese archivo el contenido revisado de
[`scripts/deploy/profego-deploy.sudoers`](../scripts/deploy/profego-deploy.sudoers),
con el usuario sustituido. Cada comando está fijado a una ruta absoluta y `""`
restringe la invocación a **cero argumentos**. Luego valida y confirma los permisos:

```bash
sudo visudo -c
sudo -l -U deploy
```

La lista debe mostrar únicamente los nueve helpers `/usr/local/sbin/profego-*` de
la regla. No autorices `bash`, `git`, `systemctl`, `cp`, `sqlite3`, ni comandos con
argumentos libres mediante sudoers.

### Verificar `known_hosts` antes de guardar el secreto

Obtén y verifica la huella ED25519 usando un canal administrativo confiable hacia el
VPS. En el VPS:

```bash
sudo ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

En una estación administrativa confiable (no en GitHub Actions), obtén la línea
completa que se usará en el secreto:

```bash
ssh-keyscan -t ed25519 VPS_HOST
```

La salida incluye `hostname ssh-ed25519 base64...`. Calcula también su huella para
compararla carácter por carácter con la huella obtenida directamente en el VPS:

```bash
ssh-keyscan -t ed25519 VPS_HOST 2>/dev/null | ssh-keygen -lf -
```

No aceptes la salida si la huella no coincide. Guarda **la línea completa** de
`ssh-keyscan` (sin editarla) en `VPS_KNOWN_HOSTS`. El workflow no ejecuta
`ssh-keyscan`; escribe ese secreto a `known_hosts`, comprueba que contiene
`VPS_HOST` y conecta con `StrictHostKeyChecking=yes`.

## Secuencia automatizada

1. `ProfeGo CI` termina en verde para un push a `main`; el workflow comprueba que el
   evento fue `push` en `main` y hace checkout de ese `head_sha` exacto. El despacho
   manual también requiere la rama `main` y despliega el SHA seleccionado.
2. GitHub Actions configura SSH en modo batch con host key estricta y transmite el
   SHA validado; no usa `ssh-keyscan` durante el despliegue.
3. En el VPS, `profego-backup` crea
   `/var/backups/profego/pre-deploy-<UTC>-<pid>.sqlite` con SQLite online backup y
   confirma `PRAGMA integrity_check = ok`.
4. Se avisa que habrá una respuesta 503 temporal. El helper cambia
   `AUTO_MIGRATE=0` en `.env` de forma atómica, y el estado anterior de la DB se
   comprueba con `scripts/migrate.php --status`.
5. El job hace fetch y checkout del SHA exacto, verifica que sea ancestro de
   `origin/main`, instala Composer sin dependencias de desarrollo y compila CSS
   mediante `npm ci` y `npm run build:css`.
6. Comprueba versión inicial y versión máxima, ejecuta como `www-data`
   `php scripts/migrate.php` y exige versión final igual a la máxima (actualmente 9),
   `integrity_check: ok`, `foreign_key_check: sin errores` y ninguna migración
   pendiente.
7. Registra el inicio de las comprobaciones de salud y la posición de logs, corrige
   permisos, valida Nginx, recarga PHP-FPM/Nginx, exige HTTP 200 en `/catalog` por
   HTTPS contra loopback fijando el host configurado y aborta ante errores nuevos
   de journal o de logs de Nginx/PHP-FPM.
8. El job comprueba también la URL pública y sus protecciones. Si el despliegue
   remoto falla después del backup, o falla el smoke test público, intenta rollback
   automático a la base respaldada y al SHA anterior.

Con `AUTO_MIGRATE=0`, la aplicación ya existente responde 503 mientras detecta una
migración pendiente. No hay una página de mantenimiento dedicada; ese 503 es el
aviso temporal. Mantén `AUTO_MIGRATE=0` después del despliegue como defensa
operativa.

## Rollback

El rollback automático usa la última copia verificada, detiene PHP-FPM antes de
restaurar SQLite, vuelve al commit anterior, reinstala dependencias, reconstruye el
CSS y recarga servicios. Se ejecuta ante errores de migración, health check local,
logs o smoke público; después exige HTTP 200 en HTTPS por loopback con el host
configurado.

Si el rollback automático informa error, detén el tráfico y actúa como administrador.
El job imprime `PREVIOUS_COMMIT=<sha>`; usa ese SHA exacto y confirma qué archivo
`pre-deploy-*.sqlite` corresponde al intento fallido. Desde el checkout:

```bash
cd /var/www/profego
ROLLBACK_COMMIT=<SHA_ANTERIOR_VALIDADO> \
HEALTHCHECK_HOST=<HOST_DE_APP_URL> bash scripts/deploy/rollback.sh
```

Sustituye ambos valores por el SHA revisado y el hostname de `APP_URL` (sin esquema,
puerto ni ruta). El script valida el SHA y el host, restaura la última copia
mediante el helper restringido, regresa el checkout y recompone assets; comprueba
HTTP 200 en HTTPS por loopback. El job conserva fallido el resultado que originó el
rollback.
No uses `git reset` o copies manuales sobre la base de datos si el helper está
disponible. Conserva las copias de seguridad hasta completar la revisión posterior.

## Lista posterior al despliegue

- [ ] Workflow de CI y workflow de despliegue completados en verde.
- [ ] SHA desplegado coincide con el `head_sha` del CI exitoso.
- [ ] `scripts/migrate.php --status`: `user_version` actual (9 para este despliegue)
      y ninguna migración pendiente.
- [ ] Salida de migración: `integrity_check: ok` y `foreign_key_check: sin errores`.
- [ ] `/catalog` devuelve HTTP 200 por `APP_URL` y en la sonda HTTPS local.
- [ ] `.env`, SQLite, `.git`, `vendor` y rutas sensibles devuelven 403/404.
- [ ] HSTS, CSP, `X-Content-Type-Options` y `X-Frame-Options` presentes en HTTPS.
- [ ] Sin errores nuevos en logs de PHP-FPM/Nginx; backup íntegro conservado.
- [ ] Prueba manual en navegador: registro con términos, login, dashboard, catálogo,
      reserva y logout según la interfaz implementada.
- [ ] Verifica que las cuentas existentes vuelvan a autenticarse tras la migración
      de sesiones.
