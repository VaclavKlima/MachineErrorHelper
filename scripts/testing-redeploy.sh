#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/_lib.sh"

check_common_requirements
check_git_requirements
check_testing_env

section "Updating testing code"
run git pull --ff-only

section "Building and restarting testing containers"
info "Testing images are rebuilt from the checked-out source. Containers are replaced only after the build succeeds."
run testing_compose up -d --build --remove-orphans

run_testing_release_tasks

success "Testing environment was redeployed."
info "Check logs with: docker compose --env-file .env.testing-server -f docker-compose.testing.yml logs -f --tail=200"
