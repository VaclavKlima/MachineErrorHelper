#!/usr/bin/env bash

set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/_lib.sh"

check_common_requirements
check_testing_env

section "Starting testing environment"
info "Building testing images and starting isolated testing containers."
run testing_compose up -d --build --remove-orphans

run_testing_release_tasks

success "Testing environment is running."
info "Local tunnel target is configured by TESTING_HTTP_BIND and TESTING_HTTP_PORT in .env.testing-server."
