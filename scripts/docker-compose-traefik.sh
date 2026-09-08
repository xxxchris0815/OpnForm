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

if [[ "${1:-}" == "up" ]]; then
  network_name="$(grep -E '^[[:space:]]*TRAEFIK_DOCKER_NETWORK=' "$ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '[:space:]')"
  network_name="${network_name:-traefik}"
  if ! docker network inspect "$network_name" >/dev/null 2>&1; then
    echo "Docker network '${network_name}' does not exist (external Traefik network)."
    echo "List networks and the one Traefik already uses:"
    echo "  docker network ls"
    echo "  docker ps --format '{{.Names}}' | grep -i traefik"
    echo "Set TRAEFIK_DOCKER_NETWORK in docker/traefik.env to that name, then retry."
    exit 1
  fi
fi

exec docker compose "${ARGS[@]}" "$@"
