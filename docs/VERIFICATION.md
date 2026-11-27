# Verification evidence

This file separates source implementation from observed runtime evidence. Updated 8 November 2026. No external deployment or purchased resources.

## Local checks

Final results are recorded below after checks finish. A test/build result applies only to the environment and source revision described; it is not a substitute for Docker/PostgreSQL integration.

| Check | Observed result |
|---|---|
| Workspace inspection | Initially empty application workspace; no repository instructions found |
| Composer manifest/lock validation | composer validate --strict passed using the canonical upstream-URL lock |
| Backend tests | 56 passed, 4 skipped; 112 assertions; SQLite test database |
| Frontend status tests | 4 passed; covers fresh, failed, pending, paused, stale, invalid and future-dated observations |
| Angular production compilation/build | Completed successfully using locked packages from the local cache |
| PHP formatting | Laravel Pint --test passed |
| Node smoke-script syntax | node --check scripts/smoke.mjs passed |
| Browser interaction/screenshots | Not performed; no screenshots or visual-test claims |

The host has PHP 8.3.30 and Node 24.18.0. Network dependency downloads failed and npm's asynchronous directory operations encountered Windows permission errors. Dependencies were read from the existing local cache. Angular packages were extracted from the lockfile's exact tarball URLs, with integrity checks, for the current Windows platform in a separate work directory; the standard npm ci path is configured in Linux CI.

PHP's native is_readable/is_writable checks reported false for files that could actually be opened through this Windows sandbox. Local PHP runs used a scratch-only shim that checks actual file-open operations. This shim is excluded from the source archive and application images. Application code and assertions ran against the installed framework; CI uses native Linux filesystem checks without the shim.

Local backend tests use SQLite. They cover application behavior and the partial unique index, but do not verify PostgreSQL row/advisory locks, PostgreSQL data conversion or concurrency behavior. Transport tests require RUN_TRANSPORT_TESTS=1 and permission to open local sockets.

## Implemented but unverified here

| Integration | Why not verified locally | Verification path |
|---|---|---|
| Docker image builds and Compose startup | Docker executable unavailable | containers CI job |
| PostgreSQL migrations, locks and API integration | No PostgreSQL runtime/PDO PostgreSQL extension on host | application and containers CI jobs |
| Live HTTP transport fixtures | Socket access restricted | RUN_TRANSPORT_TESTS=1 in Linux CI |
| External HTTP/DNS/TLS probe | Network/DNS access restricted | real worker pass in smoke job/operator demo |
| Backup/restore and schema/data comparison | Docker/PostgreSQL unavailable | backup + verify-restore scripts in CI |
| Composer/npm advisory scans | Registry/network access unavailable | audits in application CI job |
| Container vulnerability scans | Docker/Trivy runtime unavailable | three Trivy image scans in containers CI job |
| ShellCheck and shell execution | Git Bash fails to initialize in this Windows sandbox; ShellCheck absent | shell syntax/lint CI step |
| Ubuntu VM, public certificate issuance and rollback | No external deployment authorized or performed | documented operator procedure |

The GitHub Actions workflow has been written but has not been run on GitHub during this task. Configured checks are not passing evidence. Scans may legitimately fail as new advisories appear; do not suppress findings merely to obtain a green badge.

## Not implemented

MFA/SSO, public registration, password-reset UI, user-management UI, notifications, automatic remediation, private-target monitoring, authenticated probes, advance TLS-expiry alerts, distributed probe locations, automated data retention, off-host backup storage, scheduled backups, immutable audit storage, high availability and automated deployment/rollback.

## Final results

Final local rerun: PHPUnit reported 60 total tests, 56 passed and 4 skipped, with 112 assertions. All four skips are the explicitly gated HTTP socket fixtures. Laravel Pint passed, composer validate --strict passed, all four frontend status tests passed and the Angular production build exited successfully. Node smoke-script syntax also passed.

The source was reviewed after these checks for deployment startup paths and backup comparison logic. Shell scripts have LF line endings. Docker configuration, shell execution and restore results remain unverified for the reasons above; no CI success or security-scan result has been invented.
