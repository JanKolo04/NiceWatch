# Deploy: NiceWatch Agent

Target: Debian 12 / Ubuntu 22.04+ z systemd. Agent musi mieć PHP 8.2+ CLI oraz dostęp do `/proc` i `df`.

## 1. Pakiety

```bash
sudo apt update
sudo apt install -y php8.2-cli php8.2-curl php8.2-mbstring php8.2-xml composer git
```

(Lub `php8.3-cli` jeśli wolisz tę wersję — agent działa na obu.)

## 2. Instalacja

```bash
sudo mkdir -p /opt/nicewatch-agent
sudo chown $USER: /opt/nicewatch-agent
git clone <repo-url> /opt/nicewatch-agent-src
cp -r /opt/nicewatch-agent-src/agent/. /opt/nicewatch-agent/
cd /opt/nicewatch-agent
composer install --no-dev --optimize-autoloader
```

## 3. Konfiguracja

```bash
sudo mkdir -p /etc/nicewatch
sudo cp /opt/nicewatch-agent/config.php.dist /etc/nicewatch/agent.php
sudo chmod 600 /etc/nicewatch/agent.php
sudo chown root:root /etc/nicewatch/agent.php

# Edytuj /etc/nicewatch/agent.php — ustaw:
#   server_url = 'https://nicewatch.example.com'
#   api_token  = '<token z `nicewatch:host:create`>'
#   hostname   = (opcjonalnie własna nazwa)
```

Sprawdź:

```bash
sudo -u nicewatch /opt/nicewatch-agent/bin/nicewatch-agent ping
# powinno wypisać: Server reachable, token accepted.
```

(Możesz utworzyć dedykowanego usera: `sudo useradd -r -s /usr/sbin/nologin nicewatch`.)

## 4. systemd service + timer

Agent uruchamiany jest jednorazowo (`run` to single-shot) przez systemd timer co N sekund.

`/etc/systemd/system/nicewatch-agent.service`:

```ini
[Unit]
Description=NiceWatch agent — single checkin
After=network.target

[Service]
Type=oneshot
User=nicewatch
ExecStart=/usr/bin/php /opt/nicewatch-agent/bin/nicewatch-agent run --config=/etc/nicewatch/agent.php
Nice=10
IOSchedulingClass=best-effort
IOSchedulingPriority=7
```

`/etc/systemd/system/nicewatch-agent.timer`:

```ini
[Unit]
Description=Run NiceWatch agent every 30 seconds

[Timer]
OnBootSec=30s
OnUnitActiveSec=30s
AccuracySec=5s
Unit=nicewatch-agent.service

[Install]
WantedBy=timers.target
```

Aktywacja:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nicewatch-agent.timer

# weryfikacja:
systemctl list-timers nicewatch-agent.timer
journalctl -u nicewatch-agent.service -f
```

## 5. Diagnostyka

```bash
# Test bez wysyłki (sprawdza co kolektor by zebrał):
sudo -u nicewatch /opt/nicewatch-agent/bin/nicewatch-agent run --config=/etc/nicewatch/agent.php --dry-run

# Sprawdzenie tokenu i połączenia:
sudo -u nicewatch /opt/nicewatch-agent/bin/nicewatch-agent ping --config=/etc/nicewatch/agent.php
```

Typowe problemy:

- **401 Invalid token** — host nie istnieje w centrali lub token nie pasuje. Sprawdź `nicewatch:host:list` po stronie serwera.
- **Connection refused / SSL error** — sprawdź `server_url` (https vs http), `verify_tls`, firewall, certyfikat.
- **Disks/memory empty** — agent uruchomiony na non-Linux (np. macOS dev) lub bez dostępu do `/proc`.

## 6. Uprawnienia

Domyślnie agent czyta tylko:

- `/proc/loadavg`, `/proc/meminfo`, `/proc/stat`, `/proc/net/dev`, `/proc/uptime`, `/proc/cpuinfo` (world-readable)
- wykonuje `df -P -B1 -T` (nie wymaga root)

W kolejnych fazach (Docker, systemd, Postfix queue) będą wymagane dodatkowe uprawnienia — będą udokumentowane przy ich wprowadzeniu.
