#!/usr/bin/env bash
# WP-CLI wrapper for the local docker stack:  tools/wp.sh <wp args>
cd "$(dirname "$0")/.." && exec docker compose -f docker/docker-compose.yml run --rm -T --quiet-pull wpcli "$@" 2> >(grep -v -E '^\s*Container ' >&2)
