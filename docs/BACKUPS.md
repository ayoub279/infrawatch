# Backups and restore verification

The backup and restore scripts are implemented. **No PostgreSQL backup or restore has been verified in this environment**, because Docker and PostgreSQL are unavailable. The CI container job runs this exact drill; a successful future CI run or operator drill is required before calling recovery verified.

## Create a backup

From the project directory with the database running:

```bash
backup=$(bash scripts/backup.sh)
bash scripts/verify-restore.sh "$backup"
```

backup.sh uses pg_dump custom format, writes to a temporary file, checks success, renames it and produces a SHA-256 checksum. It does not put database passwords in command arguments. PostgreSQL provides the dump's consistent database snapshot.

The archive includes user password hashes, session records, check history and audit data. Files are created with restrictive permissions, but they are not encrypted by the script. Store encrypted off-host copies under a policy appropriate for your data. A backup on the same VM is insufficient for loss of that VM. Decide retention and backup frequency yourself; neither an off-host destination nor a backup schedule has been configured.

Back up the protected .env and deployment configuration separately through your secure process. In particular, preserve APP_KEY and database credentials. The database dump does not contain Caddy's ACME account/certificate volume or the application images. Keep the matching release image pair; Caddy can normally reissue certificates if DNS/validation is available.

## What the verification script does

1. Validates the archive checksum.
2. Creates a uniquely named disposable database inside the existing PostgreSQL container.
3. Restores with pg_restore --exit-on-error, so SQL and constraint failures fail the drill.
4. Reads schema and data from the archive and re-dumps the restored database.
5. Canonicalizes comments, PostgreSQL restrict tokens and row order; COPY rows retain their table identity. It compares the schema and all table data with the archive.
6. Prints only service/check/incident counts and a success message; it never prints a database diff or credentials.
7. Drops only the disposable database and removes temporary comparison files.

A nonzero exit is a failed verification. Save the command's exit status and sanitized output in your release evidence. The original live database is not the comparison source, because it may change after the backup snapshot. The dump itself is the expected state.

A successful drill verifies archive readability, database restoration and comparison for that archive. It does not prove your off-host copies, decryption keys, full VM recovery or a particular recovery time. No recovery-time or recovery-point numbers have been measured.

## Recovery without overwriting the current database

Use a known backup and the application release that matches it. These are manual recovery instructions for a failure, not commands the project has executed.

First verify the archive, then stop browser access and application writes:

```bash
cd /opt/infrawatch
bash scripts/verify-restore.sh backups/YOUR_BACKUP.dump
docker compose stop proxy worker api
```

Create a new database name that does not already exist. The following example deliberately fails if infrawatch_recovered already exists:

```bash
docker compose exec -T db sh -c 'createdb -U "$POSTGRES_USER" infrawatch_recovered'
docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d infrawatch_recovered --no-owner --no-acl --exit-on-error' < backups/YOUR_BACKUP.dump
```

Select the matching saved application release and edit .env:

```dotenv
DB_DATABASE=infrawatch_recovered
RELEASE=YOUR_MATCHING_OLD_RELEASE
```

Keep the correct APP_KEY and DB_USERNAME/DB_PASSWORD. Do not insert these values into shared command logs. Restart the stack using the matching images:

```bash
docker compose up -d --no-build --force-recreate --wait --wait-timeout 600
docker compose exec -T api php artisan migrate:status
docker compose exec -T worker php artisan monitor:check
docker compose exec -T worker php artisan monitor:health
docker compose ps
curl --fail https://status.your-domain.example/api/health
```

Confirm account login and known record counts/history. The migrate service executes outstanding migrations from the selected release, so ensure the release and backup really match before starting. Keep the former database untouched until recovery is accepted. Restoring a pre-failure snapshot necessarily omits later writes.

Document the actual archive, release image IDs, command outcomes and any downtime from your drill. No example numbers in this guide should be presented as measured results.
