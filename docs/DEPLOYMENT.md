# One Ubuntu VM: deployment and rollback

This procedure is provided for a VM you choose to provision. No VM has been purchased, provisioned or deployed by this project task. Container startup, public HTTPS and rollback have not been executed in the current environment.

## Prerequisites

Use a supported Ubuntu LTS such as Ubuntu 24.04, with SSH access, a domain pointing to the VM, and Docker Engine plus the Compose plugin. Follow Docker's official [Ubuntu installation instructions](https://docs.docker.com/engine/install/ubuntu/) for the repository and package setup rather than running an unreviewed installation script.

Allow SSH and TCP ports 80/443 in the VM/cloud firewall. Keep your existing SSH session open while changing firewall rules. Do not publish ports 5432 or 9000. Docker-published ports can interact differently with UFW rules than ordinary host services; verify exposure from another machine. This Compose file publishes only Caddy ports.

Check tools:

```bash
docker version
docker compose version
git --version
openssl version
```

Docker group membership grants substantial control over the host. Use a restricted deployment account and follow your own access policy. There is no secret-management service in this MVP; .env is protected locally and excluded from builds.

## Initial release

Copy or clone the project to /opt/infrawatch, owned by your deployment account. If using the supplied archive, unzip it into that directory. Keep release source and image tags so they can be reproduced or restored.

```bash
cd /opt/infrawatch
bash scripts/init.sh
chmod 600 .env
```

Edit .env with an editor. Keep the generated APP_KEY and DB_PASSWORD; set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://status.your-domain.example
SITE_ADDRESS=status.your-domain.example
HTTP_PORT=80
HTTPS_PORT=443
SESSION_SECURE_COOKIE=true
MONITOR_ALLOWED_HOSTS=your-approved-host.example
DEMO_PASSWORD=
RELEASE=release-001
```

Replace the example domain/allowlist with your actual domains. APP_URL describes InfraWatch; the monitoring allowlist describes the separate destinations the worker may check. Ensure DNS A/AAAA records point to reachable VM addresses; do not publish a broken AAAA record.

```bash
docker compose config --quiet
docker compose build
docker compose up -d --wait --wait-timeout 600
docker compose exec api php artisan infrawatch:user admin@your-domain.example --role=admin
docker compose exec api php artisan infrawatch:user viewer@your-domain.example --role=viewer
curl --fail https://status.your-domain.example/api/health
docker compose ps
```

Do not run the demo seed in production. The seed refuses APP_ENV=production even with --force. Enter unique passwords at the CLI prompts, log in through HTTPS and add an approved monitor.

The migrate service runs migrations before the API and worker start. Its successful exit is expected. Caddy waits for API health before starting. The worker may need a full minute to produce its first heartbeat. A slow pass can require several minutes, so the deployment wait budget is longer than the local-demo budget.

Caddy obtains certificates only if the real domain resolves correctly and ACME validation can reach the VM. This document is not evidence that certificate issuance has succeeded.

## Health and inspection

```bash
docker compose ps
curl --fail --silent https://status.your-domain.example/api/health
docker compose exec -T worker php artisan monitor:health
docker compose exec -T api php artisan migrate:status
docker compose logs --tail 100 api worker proxy
```

The API health endpoint checks database connectivity; it is not proof that monitoring or schema correctness is healthy. The worker health command requires a completed pass within ten minutes. Caddy's container health checks its local admin listener, not the public certificate or DNS. Use the external HTTPS check as well.

If API readiness fails on initial startup, inspect the migration service and permissions. If worker health fails, check database connectivity and whether a pass is running or blocked. A fresh dashboard result proves only that a check was recently recorded. Never print full environment/configuration data during diagnosis.

## Updating

1. Confirm the latest CI run passed, including scans and restore verification.
2. Preserve the current source tag, RELEASE value and application images.
3. Build the candidate images without changing the running stack.
4. Back up the current database and verify it before migrations.
5. Start the new release and verify the external health endpoint, both account roles and a monitoring pass.

For example, first change RELEASE in .env to a new unique tag, then:

```bash
docker compose build
backup=$(bash scripts/backup.sh)
bash scripts/verify-restore.sh "$backup"
docker compose up -d --wait --wait-timeout 600
curl --fail https://status.your-domain.example/api/health
docker compose exec -T worker php artisan monitor:check
docker compose ps
```

Keep the previous images; do not prune them during the rollback window. Capture their exact image IDs/digests before replacement:

```bash
docker image inspect infrawatch-api:release-001 infrawatch-proxy:release-001 --format '{{.Id}}'
mkdir -p releases
docker image save infrawatch-api:release-001 infrawatch-proxy:release-001 | gzip > releases/release-001-images.tar.gz
```

Those example tags must match the release you actually deployed. Base image tags are mutable; retaining built application images is more reliable than assuming a future rebuild is identical. Pin approved base digests and action SHAs in a reviewed production release.

## Rollback

A code rollback is safe only when the current database schema is compatible with the previous application. Check the migrations before choosing it.

For a compatible schema, restore the old source checkout and RELEASE value in .env, retain the same APP_KEY/database credentials, and start the saved image pair:

```bash
docker compose up -d --no-build --wait --wait-timeout 600
curl --fail https://status.your-domain.example/api/health
docker compose exec -T worker php artisan monitor:health
```

If images were archived, load them first with docker image load. Do not rebuild an old tag and assume it equals the previous image.

For an incompatible schema, stop writes and use the [recovery procedure](BACKUPS.md) to restore the matching backup into a new database. Point the matching old release at that database. Expect loss of writes made after the backup. Do not blindly run migrate:rollback or overwrite the live database. Keep the failed release database available for investigation.

This is a single-VM procedure with possible maintenance downtime. Blue/green deployment, high availability and automated rollback are not implemented.
