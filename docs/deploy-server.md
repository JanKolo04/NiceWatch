# Deploy: NiceWatch Server (centrala)

Target: Debian 12 / Ubuntu 22.04+. Stack: nginx + PHP-FPM 8.3+ + SQLite (lub MySQL/MariaDB).

## 1. Pakiety

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-cli php8.3-sqlite3 \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl \
    composer git
```

## 2. Kod

```bash
sudo mkdir -p /var/www/nicewatch
sudo chown $USER: /var/www/nicewatch
git clone <repo-url> /var/www/nicewatch
cd /var/www/nicewatch/server
composer install --no-dev --optimize-autoloader
```

## 3. Konfiguracja

```bash
cp .env.example .env
php artisan key:generate

# Edytuj .env — w szczególności:
#   APP_URL=https://nicewatch.example.com
#   APP_ENV=production
#   APP_DEBUG=false
#   MAIL_MAILER=smtp + reszta MAIL_*
#   NICEWATCH_ALERT_RECIPIENT=admin@example.com

# Baza:
touch database/database.sqlite
php artisan migrate --force

# Cache:
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Uprawnienia:
sudo chown -R www-data: storage bootstrap/cache database/database.sqlite
sudo chmod -R 775 storage bootstrap/cache
```

## 4. nginx + PHP-FPM

`/etc/nginx/sites-available/nicewatch`:

```nginx
server {
    listen 80;
    server_name nicewatch.example.com;
    root /var/www/nicewatch/server/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/nicewatch /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Następnie SSL (Let's Encrypt) — np. skryptem `../Apache2/add-ssl.sh` (jeśli używasz Apache) lub `certbot --nginx -d nicewatch.example.com`.

## 5. Queue worker (systemd)

`/etc/systemd/system/nicewatch-queue.service`:

```ini
[Unit]
Description=NiceWatch queue worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/nicewatch/server
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nicewatch-queue
```

## 6. Scheduler (cron)

```bash
sudo crontab -u www-data -e
# dodaj:
* * * * * cd /var/www/nicewatch/server && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

To uruchamia m.in. `nicewatch:mark-offline-hosts` co minutę.

## 7. Pierwszy admin

```bash
# Załóż konto przez panel (https://nicewatch.example.com/register).
# Jeśli rejestracja ma być wyłączona w prod, usuń route i utwórz usera ręcznie:
cd /var/www/nicewatch/server
php artisan tinker --execute="App\Models\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>bcrypt('CHANGE_ME')]);"
```

## 8. Rejestracja pierwszego hosta

```bash
php artisan nicewatch:host:create web01
# wypisze token — przekaż go do agenta na docelowym hoście
```
