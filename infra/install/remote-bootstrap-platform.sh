#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/opt/school-platform"
RELEASE_ARCHIVE=""
ENV_FILE=""
SERVICE_FILE=""
TIMER_FILE=""
OBSERVE_SERVICE_FILE=""
OBSERVE_TIMER_FILE=""
SERVER_NAME=""
WEB_USER="www-data"

usage() {
    cat <<'EOF'
Usage: remote-bootstrap-platform.sh --release <tar.gz> --env-file <.env> --service-file <service> --timer-file <timer> --observe-service-file <service> --observe-timer-file <timer> [options]

Required:
  --release <path>       Uploaded platform release tarball
  --env-file <path>      Uploaded Laravel .env file
  --service-file <path>  Uploaded school-platform-complete-schedules.service
  --timer-file <path>    Uploaded school-platform-complete-schedules.timer
  --observe-service-file <path>
                         Uploaded school-platform-observe-time.service
  --observe-timer-file <path>
                         Uploaded school-platform-observe-time.timer

Options:
  --app-root <path>      Install root on the target machine (default: /opt/school-platform)
  --server-name <name>   Hostname or IP Caddy should serve (default: machine primary IP)
  --web-user <name>      Web user/group owner (default: www-data)
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --release)
            RELEASE_ARCHIVE="$2"
            shift 2
            ;;
        --env-file)
            ENV_FILE="$2"
            shift 2
            ;;
        --service-file)
            SERVICE_FILE="$2"
            shift 2
            ;;
        --timer-file)
            TIMER_FILE="$2"
            shift 2
            ;;
        --observe-service-file)
            OBSERVE_SERVICE_FILE="$2"
            shift 2
            ;;
        --observe-timer-file)
            OBSERVE_TIMER_FILE="$2"
            shift 2
            ;;
        --app-root)
            APP_ROOT="$2"
            shift 2
            ;;
        --server-name)
            SERVER_NAME="$2"
            shift 2
            ;;
        --web-user)
            WEB_USER="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

for required_file in "$RELEASE_ARCHIVE" "$ENV_FILE" "$SERVICE_FILE" "$TIMER_FILE" "$OBSERVE_SERVICE_FILE" "$OBSERVE_TIMER_FILE"; do
    if [[ -z "$required_file" || ! -f "$required_file" ]]; then
        echo "Missing required file argument or file not found: $required_file" >&2
        exit 1
    fi
done

if [[ -z "$SERVER_NAME" ]]; then
    SERVER_NAME="$(hostname -I | awk '{print $1}')"
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y \
    ca-certificates \
    caddy \
    composer \
    curl \
    git \
    nodejs \
    npm \
    php \
    php-bcmath \
    php-cli \
    php-curl \
    php-fpm \
    php-gd \
    php-intl \
    php-mbstring \
    php-pgsql \
    php-sqlite3 \
    php-xml \
    php-zip \
    rsync \
    unzip

staging_dir="$(mktemp -d)"
cleanup() {
    rm -rf "$staging_dir"
}
trap cleanup EXIT

mkdir -p "$staging_dir/release" "$APP_ROOT"
tar -xzf "$RELEASE_ARCHIVE" -C "$staging_dir/release"
rsync -a --delete "$staging_dir/release/" "$APP_ROOT/"
install -D -m 600 "$ENV_FILE" "$APP_ROOT/.env"

mkdir -p \
    "$APP_ROOT/storage/app/private" \
    "$APP_ROOT/storage/framework/cache/data" \
    "$APP_ROOT/storage/framework/sessions" \
    "$APP_ROOT/storage/framework/testing" \
    "$APP_ROOT/storage/framework/views" \
    "$APP_ROOT/storage/logs" \
    "$APP_ROOT/bootstrap/cache"

cd "$APP_ROOT"
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci

if ! grep -Eq '^APP_KEY=base64:' "$APP_ROOT/.env"; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan storage:link || true
npm run build
php artisan optimize:clear

chown -R "$WEB_USER:$WEB_USER" "$APP_ROOT"

php_fpm_socket="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' | sort | tail -n 1)"
if [[ -z "$php_fpm_socket" ]]; then
    echo "Unable to locate a php-fpm socket under /run/php." >&2
    exit 1
fi

php_fpm_service="$(systemctl list-unit-files | awk '/^php[0-9]+\.[0-9]+-fpm\.service/ {print $1; exit}')"
if [[ -z "$php_fpm_service" ]]; then
    echo "Unable to locate a php-fpm systemd service." >&2
    exit 1
fi

sed "s#/var/www/school-system-redo/platform#${APP_ROOT//\\/\\\\}#g" "$SERVICE_FILE" > /etc/systemd/system/school-platform-complete-schedules.service
install -m 644 "$TIMER_FILE" /etc/systemd/system/school-platform-complete-schedules.timer
sed "s#/var/www/school-system-redo/platform#${APP_ROOT//\\/\\\\}#g" "$OBSERVE_SERVICE_FILE" > /etc/systemd/system/school-platform-observe-time.service
install -m 644 "$OBSERVE_TIMER_FILE" /etc/systemd/system/school-platform-observe-time.timer

cat > /etc/caddy/Caddyfile <<EOF
{
    auto_https disable_redirects
}

http://${SERVER_NAME}, https://${SERVER_NAME} {
    tls internal
    root * ${APP_ROOT}/public
    encode zstd gzip
    php_fastcgi unix/${php_fpm_socket}
    file_server
}
EOF

caddy validate --config /etc/caddy/Caddyfile
systemctl daemon-reload
systemctl enable --now caddy
systemctl enable --now "$php_fpm_service"
systemctl enable --now school-platform-complete-schedules.timer
systemctl enable --now school-platform-observe-time.timer
systemctl restart "$php_fpm_service"
systemctl restart caddy

echo "Deployment finished."
echo "App root: $APP_ROOT"
echo "Server name: $SERVER_NAME"
echo "PHP-FPM service: $php_fpm_service"
