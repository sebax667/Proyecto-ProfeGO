# Despliegue de ProfeGo con PHP 8.3

## Instalación

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-curl php8.3-mbstring unzip curl git composer
php -v
sudo systemctl status php8.3-fpm --no-pager
```

La salida de `php -v` debe indicar PHP 8.3.x. Nginx usa el socket:

```text
/run/php/php8.3-fpm.sock
```

## Aprovisionamiento

```bash
cd /var/www/profego
sudo chmod +x deploy/setup_production.sh deploy/smoke_test.sh
sudo APP_DIR=/var/www/profego \
     APP_USER=www-data \
     APP_GROUP=www-data \
     PHP_FPM_SERVICE=php8.3-fpm \
     bash deploy/setup_production.sh
```

## Nginx y servicios

```bash
sudo cp deploy/nginx/profego /etc/nginx/sites-available/profego
sudo ln -sfn /etc/nginx/sites-available/profego /etc/nginx/sites-enabled/profego
sudo nginx -t
sudo systemctl enable php8.3-fpm nginx
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
sudo systemctl status nginx --no-pager
```

El estado esperado de Nginx es `active (running)`.

## Permisos de despliegue remoto

Antes de habilitar el workflow, el servidor debe tener `/usr/local/bin/profego-backup` instalado como archivo propiedad de `root:root` y modo `0755`. Su contenido exacto:

```bash
#!/usr/bin/env bash
set -euo pipefail

backup_dir=/var/backups/profego
database=/var/www/profego/database/database.sqlite

install -d -o www-data -g www-data -m 0750 "$backup_dir"
runuser -u www-data -- sqlite3 "$database" ".backup '$backup_dir/pre-deploy-$(date +%F-%H%M).sqlite'"
```

El directorio `/etc/sudoers.d` debe contener una regla instalada con `visudo -f /etc/sudoers.d/profego-deploy`. Reemplaza `DEPLOY_USER` por el usuario SSH real; el contenido de la línea debe ser exactamente:

```sudoers
DEPLOY_USER ALL=(root) NOPASSWD: /usr/local/bin/profego-backup, /usr/local/bin/profego-set-auto-migrate-off, /usr/local/bin/profego-migration-status, /usr/local/bin/profego-migrate, /usr/local/bin/profego-fix-perms, /usr/bin/systemctl reload php8.3-fpm
```

Valida la regla con `visudo -cf /etc/sudoers.d/profego-deploy`. El ejecutable `/usr/local/bin/profego-fix-perms` también debe estar instalado en el servidor y ser invocable por el usuario SSH mediante la regla anterior. El despliegue usa `sudo -n`, así que cualquier comando no autorizado falla sin solicitar contraseña.

### Preparación única para el despliegue controlado

El servidor debe tener instalados los helpers raíz que fijan `AUTO_MIGRATE=0` y ejecutan el CLI como `www-data`. Tras fusionar el cambio del pipeline, instálalos sin cambiar el checkout que sirve la aplicación:

```bash
cd /var/www/profego
git fetch --prune origin
git show origin/main:deploy/install_deploy_helpers.sh | sudo env APP_DIR=/var/www/profego bash -s
```

Añade entonces la regla sudoers de arriba y valida con `visudo -cf /etc/sudoers.d/profego-deploy`. El pipeline respaldará la base, fijará `AUTO_MIGRATE=0` antes de actualizar el checkout, exigirá `user_version: 2`, aplicará 003–009 y comprobará `user_version: 9`, `integrity_check: ok` y `foreign_key_check: sin errores`. Si falla una precondición, aborta sin aplicar las migraciones.

Si GitHub Actions informa que no conoce la clave del host, valida la huella ED25519 del VPS por un canal confiable y actualiza el secret `VPS_KNOWN_HOSTS`; no desactives `StrictHostKeyChecking`.

## Smoke test

```bash
cd /var/www/profego
BASE_URL=https://tu-dominio.com bash deploy/smoke_test.sh
```

Debe confirmar HTTP 200 para `/catalog` y `/api/ai/keywords`.
