#!/usr/bin/env bash
# shellcheck disable=SC2016
# Single-quoted sh commands expand environment variables inside the database container.
# Restore into a NEW disposable database; never overwrite the live database.
set -euo pipefail
cd "$(dirname "$0")/.."
file=$(realpath "${1:?Usage: bash scripts/verify-restore.sh backups/FILE.dump}")
[[ -f "$file" && -f "$file.sha256" ]] || { echo "Dump and checksum required" >&2; exit 1; }
expected_hash=$(awk 'NR==1 { print $1 }' "$file.sha256")
actual_hash=$(sha256sum "$file" | awk '{ print $1 }')
[[ "$expected_hash" =~ ^[a-f0-9]{64}$ && "$expected_hash" == "$actual_hash" ]] || { echo "Checksum mismatch" >&2; exit 1; }
target="restore_verify_$(date -u +%s)_$RANDOM"
scratch=$(mktemp -d)
cleanup() {
  rm -f -- "$scratch/expected" "$scratch/actual"
  rmdir -- "$scratch"
  docker compose exec -T db sh -c 'dropdb -U "$POSTGRES_USER" --if-exists "$1"' sh "$target" >/dev/null
}
trap cleanup EXIT
docker compose exec -T db sh -c 'createdb -U "$POSTGRES_USER" "$1"' sh "$target"
docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$1" --no-owner --no-acl --exit-on-error' sh "$target" < "$file"
# Canonical COPY rows retain their table identity; order within a table is irrelevant.
canonicalize() {
  awk '
    /^\\\.$/ { table=""; next }
    table != "" { print table "|" $0; next }
    /^COPY / { table=$0; print; next }
    /^--/ || /^\\restrict / || /^\\unrestrict / || /^[[:space:]]*$/ { next }
    /^SET / || /^SELECT pg_catalog.set_config/ { next }
    { print }
  ' | LC_ALL=C sort
}
for section in schema data; do
  docker compose exec -T db pg_restore "--$section-only" --no-owner --no-acl -f - < "$file" | canonicalize > "$scratch/expected"
  docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$1" "--$2-only" --no-owner --no-acl' sh "$target" "$section" | canonicalize > "$scratch/actual"
  # Never print a diff of database contents (could contain password hashes or sessions).
  cmp -s "$scratch/expected" "$scratch/actual" || { echo "Restored $section differs from archive" >&2; exit 1; }
done
docker compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$1" -v ON_ERROR_STOP=1 -c "SELECT count(*) AS services FROM services; SELECT count(*) AS checks FROM checks; SELECT count(*) AS incidents FROM incidents;"' sh "$target"
echo "Restore verified: schema and table data match the archive."
