#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
APP_SOURCE="$REPO_ROOT/apps/platform"
REMOTE_BOOTSTRAP_SCRIPT="$SCRIPT_DIR/remote-bootstrap-platform.sh"
SERVICE_FILE="$REPO_ROOT/infra/systemd/school-platform-complete-schedules.service"
TIMER_FILE="$REPO_ROOT/infra/systemd/school-platform-complete-schedules.timer"
OBSERVE_SERVICE_FILE="$REPO_ROOT/infra/systemd/school-platform-observe-time.service"
OBSERVE_TIMER_FILE="$REPO_ROOT/infra/systemd/school-platform-observe-time.timer"

SSH_PORT="22"
SSH_USER="root"
SSH_HOST=""
APP_ROOT="/opt/school-platform"
SERVER_NAME=""
ENV_FILE=""
WEB_USER="www-data"

usage() {
    cat <<'EOF'
Usage: deploy-platform-over-ssh.sh --host <host> --env-file <path> [options]

Required:
  --host <host>          Target server hostname or IP
  --env-file <path>      Local Laravel .env file to upload

Options:
  --user <user>          SSH user (default: root)
  --port <port>          SSH port (default: 22)
  --app-root <path>      Install root on target (default: /opt/school-platform)
  --server-name <name>   Hostname or IP for the generated Caddyfile (default: host)
  --web-user <name>      Web owner on target (default: www-data)
  -h, --help             Show help

Example:
  ./infra/install/deploy-platform-over-ssh.sh \\
    --host 192.168.11.50 \\
    --env-file ./apps/platform/.env \\
    --server-name 192.168.11.50
EOF
}

need_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing required command: $1" >&2
        exit 1
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --host)
            SSH_HOST="$2"
            shift 2
            ;;
        --user)
            SSH_USER="$2"
            shift 2
            ;;
        --port)
            SSH_PORT="$2"
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
        --env-file)
            ENV_FILE="$2"
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

for cmd in ssh scp tar mktemp; do
    need_cmd "$cmd"
done

if [[ -z "$SSH_HOST" ]]; then
    echo "--host is required." >&2
    usage >&2
    exit 1
fi

if [[ -z "$ENV_FILE" ]]; then
    echo "--env-file is required." >&2
    usage >&2
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Env file not found: $ENV_FILE" >&2
    exit 1
fi

if [[ ! -d "$APP_SOURCE" ]]; then
    echo "Platform source directory not found: $APP_SOURCE" >&2
    exit 1
fi

if [[ ! -x "$REMOTE_BOOTSTRAP_SCRIPT" ]]; then
    echo "Remote bootstrap script missing or not executable: $REMOTE_BOOTSTRAP_SCRIPT" >&2
    exit 1
fi

if [[ -z "$SERVER_NAME" ]]; then
    SERVER_NAME="$SSH_HOST"
fi

tmp_dir="$(mktemp -d)"
cleanup() {
    rm -rf "$tmp_dir"
}
trap cleanup EXIT

release_archive="$tmp_dir/platform-release.tar.gz"

tar \
    --exclude='.env' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='bootstrap/cache/*.php' \
    -czf "$release_archive" \
    -C "$APP_SOURCE" \
    .

ssh_target="${SSH_USER}@${SSH_HOST}"
ssh_opts=(
    -p "$SSH_PORT"
    -o StrictHostKeyChecking=no
)

remote_tmp="/tmp/school-platform-deploy"
ssh "${ssh_opts[@]}" "$ssh_target" "mkdir -p '$remote_tmp'"
scp "${ssh_opts[@]}" "$release_archive" "$ssh_target:$remote_tmp/platform-release.tar.gz"
scp "${ssh_opts[@]}" "$ENV_FILE" "$ssh_target:$remote_tmp/platform.env"
scp "${ssh_opts[@]}" "$SERVICE_FILE" "$ssh_target:$remote_tmp/complete-schedules.service"
scp "${ssh_opts[@]}" "$TIMER_FILE" "$ssh_target:$remote_tmp/complete-schedules.timer"
scp "${ssh_opts[@]}" "$OBSERVE_SERVICE_FILE" "$ssh_target:$remote_tmp/observe-time.service"
scp "${ssh_opts[@]}" "$OBSERVE_TIMER_FILE" "$ssh_target:$remote_tmp/observe-time.timer"
scp "${ssh_opts[@]}" "$REMOTE_BOOTSTRAP_SCRIPT" "$ssh_target:$remote_tmp/remote-bootstrap-platform.sh"

ssh "${ssh_opts[@]}" "$ssh_target" \
    "chmod +x '$remote_tmp/remote-bootstrap-platform.sh' && \
     '$remote_tmp/remote-bootstrap-platform.sh' \
        --release '$remote_tmp/platform-release.tar.gz' \
        --env-file '$remote_tmp/platform.env' \
        --service-file '$remote_tmp/complete-schedules.service' \
        --timer-file '$remote_tmp/complete-schedules.timer' \
        --observe-service-file '$remote_tmp/observe-time.service' \
        --observe-timer-file '$remote_tmp/observe-time.timer' \
        --app-root '$APP_ROOT' \
        --server-name '$SERVER_NAME' \
        --web-user '$WEB_USER'"

echo "Remote deploy completed for $ssh_target"
