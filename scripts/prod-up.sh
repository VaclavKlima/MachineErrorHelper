#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/_lib.sh"

check_common_requirements
check_prod_env

section "Starting production environment"
info "Building production images and starting containers."
run prod_compose up -d --build --remove-orphans
run prod_compose restart nginx

run_prod_release_tasks

success "Production environment is running."
info "Web server: http://localhost"
