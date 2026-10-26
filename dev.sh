#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f "$ROOT/docker-compose.yml")
BACKEND_ENV="$ROOT/backend/.env"
DB_CONTAINER="postgres-closer"
PHP_CONTAINER="closer-php-1"
NGINX_CONTAINER="closer-nginx-1"
FRONTEND_CONTAINER="closer-frontend-1"
AGENT_CONTAINER="closer-docker-control-1"

log()  { printf '\033[1;32m[closer]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[closer]\033[0m %s\n' "$*" >&2; }
fail() { printf '\033[1;31m[closer]\033[0m %s\n' "$*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || fail "Docker não encontrado no PATH."
command -v curl >/dev/null 2>&1 || fail "curl não encontrado no PATH."

if ! docker info >/dev/null 2>&1; then
  fail "Daemon Docker indisponível. Inicie o Docker e tente novamente."
fi

env_has() {
  [ -f "$1" ] && grep -qE "^${2}=" "$1"
}

env_value() {
  [ -f "$1" ] || return 0
  sed -n "s/^${2}=//p" "$1" | tail -n 1 | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

resolve_env_defaults() {
  local value

  if [ -z "${POSTGRES_USER:-}" ]; then
    value="$(env_value "$ROOT/.env" POSTGRES_USER)"
    [ -n "$value" ] && POSTGRES_USER="$value"
  fi
  if [ -z "${POSTGRES_PASSWORD:-}" ]; then
    value="$(env_value "$ROOT/.env" POSTGRES_PASSWORD)"
    [ -n "$value" ] && POSTGRES_PASSWORD="$value"
  fi
  if [ -z "${POSTGRES_DB:-}" ]; then
    value="$(env_value "$ROOT/.env" POSTGRES_DB)"
    [ -n "$value" ] && POSTGRES_DB="$value"
  fi
  if [ -z "${DOCKER_CONTROL_TOKEN:-}" ]; then
    value="$(env_value "$ROOT/.env" DOCKER_CONTROL_TOKEN)"
    [ -n "$value" ] && DOCKER_CONTROL_TOKEN="$value"
  fi
  if [ -z "${DOCKER_CONTROL_TOKEN:-}" ] && [ -f "$BACKEND_ENV" ]; then
    value="$(env_value "$BACKEND_ENV" DOCKER_CONTROL_TOKEN)"
    [ -n "$value" ] && DOCKER_CONTROL_TOKEN="$value"
  fi

  POSTGRES_USER="${POSTGRES_USER:-closer}"
  POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-closer_dev_pw}"
  POSTGRES_DB="${POSTGRES_DB:-closer}"
  DOCKER_CONTROL_TOKEN="${DOCKER_CONTROL_TOKEN:-closer-local-docker-token}"
  DB_DATABASE="$POSTGRES_DB"
  DB_USERNAME="$POSTGRES_USER"

  export POSTGRES_USER POSTGRES_PASSWORD POSTGRES_DB DOCKER_CONTROL_TOKEN
}

warn_backend_token_mismatch() {
  local backend_token
  backend_token="$(env_value "$BACKEND_ENV" DOCKER_CONTROL_TOKEN)"
  if [ -n "$backend_token" ] && [ "$backend_token" != "$DOCKER_CONTROL_TOKEN" ]; then
    warn "DOCKER_CONTROL_TOKEN de backend/.env difere do token efetivo do Compose."
    warn "Alinhe backend/.env e a raiz antes de usar o controle Docker; nenhum .env foi alterado."
  fi
}

ensure_backend_env() {
  if [ ! -f "$BACKEND_ENV" ]; then
    log "Criando backend/.env a partir de valores locais seguros."
    local app_key jwt_secret
    app_key="base64:$(openssl rand -base64 32 2>/dev/null || head -c 32 /dev/urandom | base64 | tr -d '\n')"
    jwt_secret="$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    cat > "$BACKEND_ENV" <<ENV
APP_NAME="Closer OS"
APP_ENV=local
APP_KEY=${app_key}
APP_DEBUG=true
APP_URL=https://localhost:8453
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=pt_BR
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${POSTGRES_PASSWORD:-closer_dev_pw}
SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@closer.local"
MAIL_FROM_NAME="\${APP_NAME}"
JWT_SECRET=${jwt_secret}
JWT_TTL=1440
DEMO_ADMIN_PASSWORD=\${DEMO_ADMIN_PASSWORD:-Closer@2026!}
DOCKER_CONTROL_URL=http://docker-control:8099
DOCKER_CONTROL_TOKEN=${DOCKER_CONTROL_TOKEN}
ENV
    chmod 600 "$BACKEND_ENV"
  fi

  env_has "$BACKEND_ENV" DOCKER_CONTROL_URL || printf '\nDOCKER_CONTROL_URL=http://docker-control:8099\n' >> "$BACKEND_ENV"
  if ! env_has "$BACKEND_ENV" DOCKER_CONTROL_TOKEN; then
    printf 'DOCKER_CONTROL_TOKEN=%s\n' "$DOCKER_CONTROL_TOKEN" >> "$BACKEND_ENV"
  fi
  env_has "$BACKEND_ENV" JWT_SECRET || warn "JWT_SECRET ausente em backend/.env; o login falhará até configurá-lo."
}

container_exists() {
  docker inspect "$1" >/dev/null 2>&1
}

container_running() {
  [ "$(docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null || true)" = "true" ]
}

start_existing() {
  local name="$1"
  if container_running "$name"; then
    log "Reutilizando container ativo: $name"
  else
    log "Iniciando container existente: $name"
    docker start "$name" >/dev/null
  fi
}

ensure_service() {
  local service="$1" container="$2"
  if container_exists "$container"; then
    start_existing "$container"
  else
    log "Criando serviço ausente: $service"
    "${COMPOSE[@]}" up -d --no-deps "$service"
  fi
}

wait_container() {
  local name="$1" timeout="${2:-45}" i
  for ((i = 1; i <= timeout; i++)); do
    container_running "$name" && return 0
    sleep 1
  done
  return 1
}

wait_exec() {
  local label="$1" timeout="$2" i
  shift 2
  for ((i = 1; i <= timeout; i++)); do
    if "$@" >/dev/null 2>&1; then
      log "$label"
      return 0
    fi
    sleep 1
  done
  return 1
}

wait_http() {
  local label="$1" url="$2" timeout="${3:-60}" i
  for ((i = 1; i <= timeout; i++)); do
    if curl -kfsS --max-time 4 "$url" >/dev/null 2>&1; then
      log "$label"
      return 0
    fi
    sleep 1
  done
  return 1
}

print_status() {
  printf '\n%-26s %-10s %s\n' "CONTAINER" "ESTADO" "SAÚDE"
  local name state health
  for name in "$DB_CONTAINER" "$PHP_CONTAINER" "$NGINX_CONTAINER" "$FRONTEND_CONTAINER" "$AGENT_CONTAINER"; do
    if container_exists "$name"; then
      state="$(docker inspect -f '{{.State.Status}}' "$name" 2>/dev/null || echo unknown)"
      health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}-{{end}}' "$name" 2>/dev/null || echo -)"
    else
      state="missing"
      health="-"
    fi
    printf '%-26s %-10s %s\n' "$name" "$state" "$health"
  done
  printf '\nFrontend:  http://localhost:5173\n'
  printf 'API:       https://localhost:8453/api/v1/health\n'
  printf 'HTTP:      http://localhost:8090 (redireciona para HTTPS)\n'
}

up() {
  ensure_backend_env
  warn_backend_token_mismatch
  [ -f "$ROOT/frontend/package-lock.json" ] || fail "frontend/package-lock.json ausente. Rode npm install em frontend."

  ensure_service postgres "$DB_CONTAINER"
  wait_container "$DB_CONTAINER" 45 || fail "PostgreSQL não ficou ativo."

  ensure_service php "$PHP_CONTAINER"
  wait_container "$PHP_CONTAINER" 45 || fail "PHP-FPM não ficou ativo."

  ensure_service nginx "$NGINX_CONTAINER"
  wait_container "$NGINX_CONTAINER" 45 || fail "Nginx não ficou ativo."

  ensure_service frontend "$FRONTEND_CONTAINER"
  wait_container "$FRONTEND_CONTAINER" 45 || fail "Frontend não ficou ativo."

  ensure_service docker-control "$AGENT_CONTAINER"
  wait_container "$AGENT_CONTAINER" 45 || fail "Agente Docker não ficou ativo."

  wait_exec "PostgreSQL aceitando conexões." 60 docker exec "$DB_CONTAINER" pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" \
    || fail "PostgreSQL não aceitou conexões a tempo."

  if [ "${SKIP_MIGRATIONS:-0}" != "1" ]; then
    log "Aplicando migrations não destrutivas."
    docker exec "$PHP_CONTAINER" php artisan migrate --force --no-interaction >/dev/null \
      || fail "Migrations falharam. Verifique ./dev.sh logs php."

    local users
    users="$(docker exec "$DB_CONTAINER" psql -U "$DB_USERNAME" -d "$DB_DATABASE" -tAc 'select count(*) from users' 2>/dev/null | tr -d '[:space:]' || true)"
    if [ "$users" = "0" ]; then
      log "Banco sem usuários; executando seed inicial (inclui admin local)."
      docker exec "$PHP_CONTAINER" php artisan db:seed --force --no-interaction >/dev/null \
        || fail "Seed inicial falhou. Verifique ./dev.sh logs php."
    fi
  fi

  wait_http "Frontend respondendo." "http://localhost:5173/" 60 || fail "Frontend não respondeu a tempo."
  wait_http "API respondendo via HTTPS." "https://localhost:8453/api/v1/health" 60 || fail "API não respondeu a tempo."

  log "Ambiente pronto. Containers existentes foram preservados."
  print_status
}

status() {
  print_status
  if container_running "$PHP_CONTAINER"; then
    docker exec "$PHP_CONTAINER" php artisan migrate:status --no-ansi 2>/dev/null | tail -n 5 || true
  fi
}

logs() {
  local service="${1:-}"
  if [ -z "$service" ]; then
    "${COMPOSE[@]}" logs --tail=120
  else
    "${COMPOSE[@]}" logs --tail=120 "$service"
  fi
}

resolve_env_defaults

case "${1:-up}" in
  up) up ;;
  status) status ;;
  logs) shift; logs "${1:-}" ;;
  help|-h|--help)
    cat <<'HELP'
Uso: ./dev.sh [up|status|logs [serviço]]

  up              Verifica e inicia apenas o necessário, preservando containers e volumes.
  status          Mostra o estado real dos containers e das migrations.
  logs [serviço]  Exibe logs recentes (php, nginx, frontend, postgres, docker-control).

Variáveis opcionais:
  SKIP_MIGRATIONS=1        Não executa migrations.
  DOCKER_CONTROL_TOKEN=... Token compartilhado entre Laravel e o agente local.

Precedência de configuração:
  ambiente > .env da raiz > backend/.env > padrão local.
HELP
    ;;
  *) fail "Comando desconhecido: $1. Use ./dev.sh help." ;;
esac
