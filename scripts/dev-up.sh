#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/_lib.sh"

check_common_requirements

section "Starting development environment"
info "Building local development images and starting containers."
run docker compose up -d --build --remove-orphans

success "Development environment is running."
info "Backend API: http://localhost:8090/api"
info "Mobile web app: http://localhost:8081"
