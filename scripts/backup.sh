#!/usr/bin/env bash
# shellcheck disable=SC2016
# Single-quoted sh commands expand environment variables inside the database container.
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
mkdir -p backups
stamp=$(date -u +%Y%m%dT%H%M%SZ)
file="backups/infrawatch-$stamp.dump"
tmp="$file.partial"
trap 'rm -f -- "$tmp"' EXIT
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner --no-acl' > "$tmp"
test -s "$tmp"
mv -- "$tmp" "$file"
sha256sum "$file" > "$file.sha256"
echo "$file"
