#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/_lib.sh"

check_common_requirements
check_git_requirements

section "Updating development code"
run git pull --ff-only

section "Rebuilding development environment"
info "Compose will rebuild changed images and recreate containers as needed."
run docker compose up -d --build --remove-orphans

success "Development environment was redeployed."
info "Backend API: http://localhost:8090/api"
info "Mobile web app: http://localhost:8081"
