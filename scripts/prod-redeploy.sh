#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/_lib.sh"

check_common_requirements
check_git_requirements
check_prod_env

section "Updating production code"
run git pull --ff-only

section "Building and restarting production containers"
info "Production images are rebuilt from the checked-out source. Containers are replaced only after the build succeeds."
run prod_compose up -d --build --remove-orphans
run prod_compose restart nginx

run_prod_release_tasks

success "Production was redeployed."
info "Check logs with: docker compose -f docker-compose.prod.yml logs -f --tail=200"
