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
DEPLOY_USER ALL=(root) NOPASSWD: /usr/local/bin/profego-backup, /usr/local/bin/profego-fix-perms, /usr/bin/systemctl reload php8.3-fpm
```

Valida la regla con `visudo -cf /etc/sudoers.d/profego-deploy`. El ejecutable `/usr/local/bin/profego-fix-perms` también debe estar instalado en el servidor y ser invocable por el usuario SSH mediante la regla anterior. El despliegue usa `sudo -n`, así que cualquier comando no autorizado falla sin solicitar contraseña.

## Smoke test

```bash
cd /var/www/profego
BASE_URL=https://tu-dominio.com bash deploy/smoke_test.sh
```

Debe confirmar HTTP 200 para `/catalog` y `/api/ai/keywords`.
