#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ -t 1 ] && command -v tput >/dev/null 2>&1; then
  BOLD="$(tput bold)"
  DIM="$(tput dim)"
  RESET="$(tput sgr0)"
  BLUE="$(tput setaf 4)"
  GREEN="$(tput setaf 2)"
  YELLOW="$(tput setaf 3)"
  RED="$(tput setaf 1)"
else
  BOLD=""
  DIM=""
  RESET=""
  BLUE=""
  GREEN=""
  YELLOW=""
  RED=""
fi

cd "${REPO_ROOT}"

section() {
  printf "\n%s==> %s%s\n" "${BOLD}${BLUE}" "$1" "${RESET}"
}

info() {
  printf "%s%s%s\n" "${DIM}" "$1" "${RESET}"
}

success() {
  printf "%s✓ %s%s\n" "${GREEN}" "$1" "${RESET}"
}

warn() {
  printf "%s! %s%s\n" "${YELLOW}" "$1" "${RESET}"
}

fail() {
  printf "%s✗ %s%s\n" "${RED}" "$1" "${RESET}" >&2
  exit 1
}

run() {
  printf "%s$ %s%s\n" "${DIM}" "$*" "${RESET}"
  "$@"
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

check_common_requirements() {
  require_command docker
  docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is required. Install Docker Desktop or the docker compose plugin."
}

check_git_requirements() {
  require_command git
}

check_prod_env() {
  if [ ! -f apps/backend/.env ]; then
    fail "apps/backend/.env is missing. Create it on the server before starting production."
  fi
}

check_testing_env() {
  if [ ! -f .env.testing-server ]; then
    fail ".env.testing-server is missing. Copy .env.testing-server.example and edit it before starting testing."
  fi

  if [ ! -f apps/backend/.env.testing-server ]; then
    fail "apps/backend/.env.testing-server is missing. Copy apps/backend/.env.testing-server.example and edit it before starting testing."
  fi
}

prod_compose() {
  docker compose -f docker-compose.prod.yml "$@"
}

testing_compose() {
  docker compose --env-file .env.testing-server -f docker-compose.testing.yml "$@"
}

run_prod_release_tasks() {
  section "Running Laravel release tasks"
  run prod_compose exec -T backend php artisan migrate --force
  run prod_compose exec -T backend php artisan storage:link --force
  run prod_compose exec -T backend php artisan optimize:clear
  run prod_compose exec -T backend php artisan config:cache
  run prod_compose exec -T backend php artisan route:cache
  run prod_compose exec -T backend php artisan view:cache
  run prod_compose exec -T backend php artisan event:cache
  run prod_compose exec -T backend php artisan queue:restart
}

run_testing_release_tasks() {
  section "Running Laravel testing release tasks"
  run testing_compose exec -T backend php artisan migrate --force
  run testing_compose exec -T backend php artisan storage:link --force
  run testing_compose exec -T backend php artisan optimize:clear
  run testing_compose exec -T backend php artisan config:cache
  run testing_compose exec -T backend php artisan route:cache
  run testing_compose exec -T backend php artisan view:cache
  run testing_compose exec -T backend php artisan event:cache
  run testing_compose exec -T backend php artisan queue:restart
}
