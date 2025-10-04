#!/usr/bin/env bash
set -Eeuo pipefail

# -----------------------------
# Config
# -----------------------------
DOCKER_COMPOSE_BIN="${DOCKER_COMPOSE_BIN:-docker compose}"
SERVICE="${SERVICE:-php}"           # service name in docker-compose
WORKDIR="${WORKDIR:-/app}"          # working dir in container
AUTO_FIX="no"                        # --fix (yes/no)
PHP_CS_FIXER_FLAGS="--dry-run --diff --ansi"  # need to be replaced with '' if --fix
PARALLELISM="${PARALLELISM:-4}"

# -----------------------------
# Helpers
# -----------------------------
log()   { printf "\033[1;34m[info]\033[0m %s\n" "$*"; }
ok()    { printf "\033[1;32m[ ok ]\033[0m %s\n" "$*"; }
warn()  { printf "\033[1;33m[warn]\033[0m %s\n" "$*"; }
err()   { printf "\033[1;31m[fail]\033[0m %s\n" "$*" >&2; }
die()   { err "$*"; exit 1; }

have()  { command -v "$1" >/dev/null 2>&1; }

in_container() {
  # heuristic: container has /app php available
  [[ -d "/app" ]] && php -v >/dev/null 2>&1
}

docker_exec() {
  ${DOCKER_COMPOSE_BIN} exec -T "${SERVICE}" bash -lc "$*"
}

try_docker() {
  ${DOCKER_COMPOSE_BIN} ps >/dev/null 2>&1
}

# -----------------------------
# Args
# -----------------------------
while [[ $# -gt 0 ]]; do
  case "$1" in
    --fix) AUTO_FIX="yes"; PHP_CS_FIXER_FLAGS="--ansi";;
    --no-audit) SKIP_AUDIT="yes";;
    --no-static) SKIP_STATIC="yes";;
    --no-tests) SKIP_TESTS="yes";;
    --help|-h)
      cat <<EOF
Usage: ./check.sh [--fix] [--no-audit] [--no-static] [--no-tests]

--fix        : auto-correct style (php-cs-fixer fix)
--no-audit   : skip composer audit
--no-static  : skip phpstan
--no-tests   : skip tests (PHPUnit)
EOF
      exit 0
      ;;
    *) die "Unknown option: $1";;
  esac
  shift
done

# -----------------------------
# Runner: prefer docker, fallback to local
# -----------------------------
RUNNER="local"
if ! in_container && try_docker; then
  RUNNER="docker"
fi

run() {
  if [[ "${RUNNER}" == "docker" ]]; then
    docker_exec "cd ${WORKDIR} && $*"
  else
    bash -lc "$*"
  fi
}

# -----------------------------
# Show env
# -----------------------------
log "Runner: ${RUNNER}"
if [[ "${RUNNER}" == "docker" ]]; then
  run "php -v | head -n1"
else
  php -v | head -n1 || true
fi

# -----------------------------
# Composer validate
# -----------------------------
log "Composer validate"
run "composer validate --no-interaction --strict" && ok "composer.json is valid"

# -----------------------------
# Syntax check
# -----------------------------
log "PHP syntax check"
if run "test -f vendor/bin/parallel-lint"; then
  run "./vendor/bin/parallel-lint --exclude vendor --exclude storage --exclude .docker -j ${PARALLELISM} ."
else
  warn "php-parallel-lint not found, using fallback php -l"
  run "find . -type f -name '*.php' -not -path './vendor/*' -not -path './storage/*' -not -path './.docker/*' -print0 | xargs -0 -n1 -P ${PARALLELISM} php -l" \
    | (! grep -E "Errors parsing" >/dev/null || (cat && false))
fi
ok "Syntax OK"

# -----------------------------
# Code style (PHP-CS-Fixer)
# -----------------------------
if run "test -f vendor/bin/php-cs-fixer"; then
  if [[ "${AUTO_FIX}" == "yes" ]]; then
    log "PHP-CS-Fixer (auto-fix)"
    run "vendor/bin/php-cs-fixer fix ${PHP_CS_FIXER_FLAGS}"
  else
    log "PHP-CS-Fixer (dry-run, PSR-12)"
    run "vendor/bin/php-cs-fixer fix ${PHP_CS_FIXER_FLAGS}"
  fi
  ok "Style check passed"
else
  warn "PHP-CS-Fixer is not installed. Skipping style check."
fi

# -----------------------------
# Static analysis (phpstan / larastan)
# -----------------------------
if [[ "${SKIP_STATIC:-no}" != "yes" ]]; then
  if run "test -f vendor/bin/phpstan"; then
    log "PHPStan analyse"
    # use config if available, otherwise analyze app/ and tests/
    if run "test -f phpstan.neon || test -f phpstan.neon.dist"; then
      run "vendor/bin/phpstan analyse"
    else
      run "vendor/bin/phpstan analyse app tests --level=max"
    fi
    ok "Static analysis passed"
  else
    warn "PHPStan not installed. Skipping static analysis."
  fi
fi

# -----------------------------
# Tests (Pest / PHPUnit)
# -----------------------------
if [[ "${SKIP_TESTS:-no}" != "yes" ]]; then
  if run "test -f vendor/bin/pest"; then
    log "Running tests (Pest)"
    # no coverage by default (less requirements for Xdebug)
    run "vendor/bin/pest -q"
  elif run "test -f vendor/bin/phpunit"; then
    log "Running tests (PHPUnit)"
    run "vendor/bin/phpunit --colors=always"
  else
    warn "No test runner found (neither Pest nor PHPUnit). Skipping tests."
  fi
  ok "Tests finished"
fi

# -----------------------------
# Composer audit (optional)
# -----------------------------
if [[ "${SKIP_AUDIT:-no}" != "yes" ]]; then
  if run "composer help audit >/dev/null 2>&1"; then
    log "Composer audit"
    # --no-dev to watch for dependencies; uncheck if necessary
    run "composer audit --no-interaction --no-dev || true"
    ok "Composer audit completed (non-blocking)"
  else
    warn "Composer audit is not available in this environment. Skipping."
  fi
fi

ok "All checks completed successfully."
