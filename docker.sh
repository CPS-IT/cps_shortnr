#!/usr/bin/env bash
set -euo pipefail

# 1. Always inject host UID/GID
export USER_ID=$(id -u)
export GROUP_ID=$(id -g)

# 2. Build only when necessary (for 'up' commands or when image doesn't exist)
should_build() {
  # Check if first argument is 'up' (with any flags)
  if [[ "${1:-}" == "up" ]]; then
    return 0
  fi
  # Check if image doesn't exist
  if ! docker compose images -q php | grep -q .; then
    return 0
  fi
  return 1
}

if should_build "${1:-}"; then
  docker compose build --build-arg USER_ID --build-arg GROUP_ID >/dev/null
fi

# 3. Helper: is the php service running?
is_running() {
  docker compose ps --status=running --format json | jq -e '.State == "running"' >/dev/null 2>&1
}

# 4. Smart 'exec': start container if necessary and inject service name
if [[ "${1:-}" == "exec" ]]; then
  if ! is_running; then
    echo "php container not running – starting it in the background …"
    docker compose up -d php
    # short wait so the container is really ready
    sleep 1
  fi
  # Inject 'php' service name after 'exec'
  shift  # remove 'exec'
  exec docker compose exec php "$@"
fi

# 5. Forward everything else transparently
exec docker compose "$@"
