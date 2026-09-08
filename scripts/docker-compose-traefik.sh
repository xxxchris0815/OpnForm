#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${TRAEFIK_ENV_FILE:-$ROOT/docker/traefik.env}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing ${ENV_FILE}"
  echo "Copy the example and set OPNFORM_DOMAIN:"
  echo "  cp docker/traefik.env.example docker/traefik.env"
  exit 1
fi

if grep -q '^[[:space:]]*ingress:' docker-compose.override.yml 2>/dev/null; then
  echo "docker-compose.override.yml defines ingress. That overwrites nginx."
  echo "Replace it with docker-compose.override.example.yml (API/UI images only)."
  exit 1
fi

ARGS=(
  --env-file "$ENV_FILE"
  -f docker-compose.yml
  -f docker-compose.traefik.yml
)

if [[ -f docker-compose.override.yml ]]; then
  ARGS+=(-f docker-compose.override.yml)
fi

exec docker compose "${ARGS[@]}" "$@"
