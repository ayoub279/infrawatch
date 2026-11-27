# Interview guide

## What did you build?

A single-origin Angular dashboard with a Laravel REST API, a scheduled monitoring worker and a PostgreSQL database. Caddy serves the UI and forwards API requests. It is designed to be understandable and deployable on one VM before adding distributed infrastructure.

## Why sessions rather than JWTs?

The frontend and API share an origin, so server-side sessions avoid storing bearer tokens in localStorage. Laravel handles password hashing, session rotation and request-forgery checks. The tradeoff is maintaining session records and sharing application keys/database access across instances. There is no third-party mobile API requirement here.

## How is authorization enforced?

Routes require an authenticated session. Service mutation and audit routes additionally require the admin middleware. Angular hiding controls is only a usability measure. Both admin and viewer roles may acknowledge and resolve incidents; “viewer” means read-only for monitor configuration in this project's role policy.

## What makes the monitoring worker different from an unsafe URL fetcher?

Only admins configure URLs and deployment config determines the exact hostnames. The parser restricts schemes, ports and URL syntax. The worker resolves and checks every address, then connects to one pinned address. Redirects and inherited proxies are disabled. TLS hostname and trust-chain verification stay enabled. Timeouts, body/header limits and concurrency limit resource use.

An allowlist alone would not prevent an allowed hostname resolving to a private address. Resolving safely without pinning would leave a second-lookup race. Both layers are necessary. This implementation is intentionally conservative and does not support internal targets.

## How do you avoid duplicate incidents?

The result write and incident decision run in one transaction under a service-row lock. A partial unique database index independently enforces one incident with null resolved_at per service. Acknowledgement does not release that slot; resolution does. Tests cover repeated failures, acknowledged incidents and failure after resolution.

## What is the hardest consistency edge case?

A monitor can be edited while its request is in flight. Each result carries the actual target URL; persistence rejects it if the current service URL changed or the service was paused/deleted. Old history remains distinguishable by target_url, and the dashboard's latest status is filtered to the current URL. Incident history still belongs to the service rather than a separate endpoint revision.

## Why not automatically resolve on recovery?

A successful probe is a technical observation. An incident may still need investigation or follow-up. This minimal workflow leaves resolution to a person, records recovery in history and documents that resolving an actively failing service causes a new incident on its next failure.

## How do scheduling and capacity work?

One worker performs a sequential pass, with shared cache and PostgreSQL advisory locks. The scheduler attempts a pass each minute and skips overlap. A pass is not guaranteed to finish in a minute, particularly with many timeout-bound destinations. No throughput, uptime SLA or performance benchmark has been claimed.

At larger scale I would measure demand, add retention, queue individual checks with per-service locking, bound parallel work, and separate worker egress from the application network. I would not add Kubernetes just for a portfolio checkbox.

## What do DNS and TLS fields tell you?

DNS answers show the addresses observed from this resolver at check time. They are not a global DNS propagation result. Response time includes DNS and the network request. TLS expiry is the leaf certificate timestamp if available; null can mean HTTP or unavailable certificate information. Chain and hostname validation errors fail the request. Advance certificate-expiry alerting is not implemented.

## What protects credentials in logs?

Passwords are hashed, new secrets are generated locally, and configuration files are ignored by git and Docker build contexts. Audit writers pass selected fields. Probe failures use fixed categories. Unhandled exception reporting logs the class rather than messages with possible SQL bindings. This loses some debugging detail in exchange for reducing accidental credential disclosure. Backups and database access still need protection.

## How would you deploy and roll back?

Build a release-tagged pair of images, take and verify a backup, start the stack, run migrations before serving traffic and verify health checks. Keep the previous image pair. A code rollback uses those images only if the schema is backward-compatible; otherwise restore the matching backup into a new database and point the matching old release at it. Do not blindly run migrate:rollback.

## What have you verified?

Use docs/VERIFICATION.md and the actual CI run, not an assumed result. Local SQLite tests and a compiled Angular build do not prove PostgreSQL concurrency, Docker networking, public TLS or backup recovery. The repository contains the integration checks to verify those paths on a Docker-capable Linux machine.

## What would you improve next?

First execute the full integration/restore workflow and fix real failures. Then add data retention, separate runtime database privileges, measured capacity limits, secure password reset/MFA, notification delivery with retries and richer incident notes. These are future changes, not features claimed by this version.
