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

## Smoke test

```bash
cd /var/www/profego
BASE_URL=https://tu-dominio.com bash deploy/smoke_test.sh
```

Debe confirmar HTTP 200 para `/catalog` y `/api/ai/keywords`.
