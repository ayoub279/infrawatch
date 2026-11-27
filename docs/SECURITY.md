# Security boundaries and tradeoffs

This is a constrained monitoring application, not an unrestricted URL fetcher. Its defenses are implemented in source and covered to the extent recorded in VERIFICATION.md; they are not a penetration-test certification.

| Boundary | Implementation |
|---|---|
| Who can configure monitors? | Backend auth + admin middleware, independent of whether Angular hides buttons |
| Which names? | Exact lowercase hostnames from deployment environment; no wildcard subdomain matching or IP literals |
| Which protocols/ports? | HTTP on 80 and HTTPS on 443; no file/FTP, userinfo, query, fragment, whitespace or backslash syntax |
| Which addresses? | Resolve each pass; reject private, loopback, link-local, multicast, documentation, shared-address and transition ranges; conservatively restrict IPv6 to public unicast outside denied blocks |
| DNS race? | Validate every returned address; pin the selected IP with CURLOPT_RESOLVE; retain original hostname for TLS |
| Redirects/proxies? | Redirect following off; explicit empty cURL proxy setting prevents inherited HTTP proxy settings from rerouting a probe |
| Resource use? | One sequential worker; 50-service limit; 3s DNS, 2s HTTP connect, 5s HTTP total; 1 MiB body and 32 KiB headers; container CPU/memory/PID limits |
| Credentials? | Random generated environment secrets, bcrypt-hashed user passwords, hidden CLI password prompt; .env excluded from git/build contexts |
| Logs? | Audit entries have selected fields only; probe diagnostics become fixed error categories; exception reporting logs exception class rather than messages or SQL bindings |
| Browser? | Same-origin session auth, HttpOnly/SameSite cookies, CSRF/origin middleware, login throttle, Caddy CSP/frame/content-type headers |
| Database exposure? | No published PostgreSQL port; internal Compose data network |
| Incident duplicates? | Transaction + row lock + partial unique index for unresolved incidents |

The address policy lives in backend/app/Services/DestinationPolicy.php and should be reviewed when special-use registries or deployment needs change. IPv6 policy deliberately sacrifices some address compatibility for a small, reviewable allowlist model. The current version cannot monitor private infrastructure; changing that requires a separate, carefully scoped policy, not a broad bypass.

The allowlist is a deployment trust decision. A compromised allowed server can still return malicious content, though this application discards bodies and never renders them. Only put non-secret health paths in service URLs. Authenticated probes and query-string tokens are unsupported; never put credentials in a path or service name.

The application does not log request payloads or enable access logs. Database data, backups, process environments and the Docker host still contain sensitive material and must be access-controlled. Do not publish verbose diagnostic dumps, docker compose config output without --quiet, environment files, session tables, or backup contents.

The PostgreSQL bootstrap role can create databases, which enables the isolated restore drill. For a larger deployment, separate migration/backup and runtime database privileges. This MVP has no MFA, SSO, password reset UI, notification service, tamper-proof audit store, tenant isolation, network egress firewall, or independent penetration test.

Dependency and image scanning is configured to fail CI on its stated thresholds. It is not evidence of being vulnerability-free, and its results change as advisories are published.
