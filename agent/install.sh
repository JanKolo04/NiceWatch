#!/usr/bin/env bash
#
# NiceWatch agent installer
#
# Usage (run on the host you want to monitor, as root):
#   curl -fsSL http://<nicewatch-server>/install.sh | sudo bash -s -- \
#        --server http://<nicewatch-server> --token <BEARER_TOKEN> [--name web01]
#
# What it does (idempotent):
#   1. apt-get installs PHP CLI + composer if missing
#   2. downloads nicewatch-agent.zip from the central server
#   3. unpacks to /opt/nicewatch-agent
#   4. runs composer install --no-dev
#   5. writes /etc/nicewatch/agent.php with the supplied server URL + token
#   6. installs systemd .service + .timer and enables the timer (30 s cadence)
#
set -euo pipefail

SERVER=""
TOKEN=""
HOSTNAME_ARG=""
INSTALL_DIR="/opt/nicewatch-agent"
CONFIG_DIR="/etc/nicewatch"
SERVICE_USER="nicewatch"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --server)   SERVER="$2"; shift 2 ;;
        --token)    TOKEN="$2"; shift 2 ;;
        --name)     HOSTNAME_ARG="$2"; shift 2 ;;
        --dir)      INSTALL_DIR="$2"; shift 2 ;;
        -h|--help)
            sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; exit 1 ;;
    esac
done

if [[ -z "$SERVER" || -z "$TOKEN" ]]; then
    echo "Missing --server or --token. Run with --help for usage." >&2
    exit 1
fi

if [[ $EUID -ne 0 ]]; then
    echo "Run as root (sudo)." >&2
    exit 1
fi

log() { printf '\033[36m[nicewatch]\033[0m %s\n' "$*"; }

# ---------- 1. Packages ----------
log "Installing PHP CLI + composer + unzip (if missing)…"
PHP_PKG=""
if ! command -v php >/dev/null 2>&1; then
    if   apt-cache show php8.3-cli >/dev/null 2>&1; then PHP_PKG="php8.3-cli php8.3-curl php8.3-mbstring php8.3-xml";
    elif apt-cache show php8.2-cli >/dev/null 2>&1; then PHP_PKG="php8.2-cli php8.2-curl php8.2-mbstring php8.2-xml";
    else PHP_PKG="php-cli php-curl php-mbstring php-xml"; fi
fi
DEBIAN_FRONTEND=noninteractive apt-get update -qq
# shellcheck disable=SC2086
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq $PHP_PKG composer unzip curl >/dev/null

# ---------- 2. Service user ----------
if ! id "$SERVICE_USER" >/dev/null 2>&1; then
    log "Creating service user '$SERVICE_USER'"
    useradd -r -s /usr/sbin/nologin "$SERVICE_USER"
fi

# ---------- 3. Download & unpack ----------
ZIP_URL="${SERVER%/}/downloads/nicewatch-agent.zip"
TMP_ZIP=$(mktemp /tmp/nicewatch-agent.XXXX.zip)

log "Downloading $ZIP_URL"
if ! curl -fsSL -o "$TMP_ZIP" "$ZIP_URL"; then
    echo "Failed to download $ZIP_URL — is the NiceWatch server reachable?" >&2
    exit 1
fi

log "Extracting to $INSTALL_DIR"
mkdir -p "$INSTALL_DIR"
# Unpack into a tmp dir then move so we don't half-overwrite a running install
TMP_EXTRACT=$(mktemp -d)
unzip -q -o "$TMP_ZIP" -d "$TMP_EXTRACT"
# Zip contains a single top-level "nicewatch-agent/" directory.
SRC_DIR="$TMP_EXTRACT/nicewatch-agent"
if [[ ! -d "$SRC_DIR" ]]; then SRC_DIR=$(find "$TMP_EXTRACT" -mindepth 1 -maxdepth 1 -type d | head -1); fi
cp -a "$SRC_DIR/." "$INSTALL_DIR/"
rm -rf "$TMP_ZIP" "$TMP_EXTRACT"

chown -R "$SERVICE_USER":"$SERVICE_USER" "$INSTALL_DIR"

# ---------- 4. Composer deps ----------
log "Installing composer dependencies"
sudo -u "$SERVICE_USER" -H bash -c "cd '$INSTALL_DIR' && composer install --no-dev --no-interaction --optimize-autoloader --quiet"

# ---------- 5. Config ----------
mkdir -p "$CONFIG_DIR"
CONFIG_FILE="$CONFIG_DIR/agent.php"
HOSTNAME_LITERAL="${HOSTNAME_ARG:-null}"
[[ "$HOSTNAME_LITERAL" != "null" ]] && HOSTNAME_LITERAL="'$HOSTNAME_ARG'"

log "Writing $CONFIG_FILE"
cat > "$CONFIG_FILE" <<EOF
<?php

declare(strict_types=1);

return [
    'server_url' => '$SERVER',
    'api_token' => '$TOKEN',
    'hostname' => $HOSTNAME_LITERAL,
    'timeout_seconds' => 10,
    'verify_tls' => true,
    'collectors' => [
        'system' => true,
    ],
];
EOF
chmod 600 "$CONFIG_FILE"
chown root:"$SERVICE_USER" "$CONFIG_FILE"
chmod 640 "$CONFIG_FILE"

# ---------- 6. systemd ----------
log "Installing systemd service + timer"
install -m 644 "$INSTALL_DIR/systemd/nicewatch-agent.service" /etc/systemd/system/
install -m 644 "$INSTALL_DIR/systemd/nicewatch-agent.timer"   /etc/systemd/system/

# Patch ExecStart in the service if INSTALL_DIR was overridden
if [[ "$INSTALL_DIR" != "/opt/nicewatch-agent" ]]; then
    sed -i "s|/opt/nicewatch-agent|$INSTALL_DIR|g" /etc/systemd/system/nicewatch-agent.service
fi

systemctl daemon-reload
systemctl enable --now nicewatch-agent.timer

# ---------- 7. Smoke test ----------
log "Verifying connectivity…"
if sudo -u "$SERVICE_USER" "$INSTALL_DIR/bin/nicewatch-agent" ping --config="$CONFIG_FILE"; then
    log "All good — the host should appear as 'online' in the NiceWatch panel within ~30 seconds."
else
    echo "Ping failed — check the token and that '$SERVER' is reachable from this host." >&2
    exit 1
fi
