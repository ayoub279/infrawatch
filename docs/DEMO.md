# Five-minute demo

Before presenting, follow the README startup commands and run a real monitoring pass. Do not manufacture a history or claim a successful deployment if setup has not completed.

1. **Purpose, 30 seconds.** Explain: “InfraWatch checks a small set of deployment-approved web services and turns failures into a manageable incident workflow.”
2. **Login and overview, 45 seconds.** Sign in as admin. Show the real current status, response time, HTTP status and history. Explain pending and stale states. Show DNS addresses and TLS expiry from a real HTTPS result.
3. **Configure a monitor, 45 seconds.** Add an approved host. Try an unapproved hostname or nonstandard port and show backend validation. Keep any failure message visible; do not pretend it was a successful check.
4. **Incident workflow, 90 seconds.** On a domain you control, configure a path that deliberately returns HTTP 503, run a pass, then run another. Show that one active incident remains. Acknowledge it, restore the endpoint and run again. Show the recovery check, then resolve manually. If you do not control a public endpoint, explain this scenario using the automated tests instead of inventing an outage.
5. **Authorization and audit, 45 seconds.** Sign in as viewer. Show that service management is absent and explain that backend tests also assert 403 for direct mutation requests. Both roles can acknowledge and resolve incidents. Return as admin and show the audit trail.
6. **Operations, 45 seconds.** Show docker compose ps, health endpoint, CI workflow and verification status. Explain backup/restore and the single-worker limit. State clearly which integration checks you have actually run.

Optional command during a live local demo:

```bash
docker compose exec -T worker php artisan monitor:check
```

A network failure is still a real recorded result, not proof that the target itself is globally down. Explain that the observation comes from this worker's location.
