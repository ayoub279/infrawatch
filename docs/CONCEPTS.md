# Protocols and networking used by InfraWatch

## HTTP

A check sends a GET request to a configured URL. The response status describes what happened: 2xx is successful, 3xx requests a redirect, 4xx is a client-side rejection and 5xx indicates a server-side failure. InfraWatch treats only a completed 2xx transfer as available. A 404 or a login redirect therefore opens an incident, even if the web server itself is reachable.

The response body is discarded rather than stored. The transfer stops once the configured body or header limit is exceeded, and it has connect and total timeouts. This limits how long a slow or very large response can occupy the single worker. The reported response time measures the whole probe, including DNS and TLS, rather than an isolated HTTP server processing time.

Redirects are deliberately disabled. Following a Location header could send a worker to a second, unvalidated destination such as a cloud metadata service.

## DNS

DNS translates a hostname such as example.com into IPv4 A records and IPv6 AAAA records. CNAME records alias one name to another. The helper follows a bounded number of aliases and uses the container's configured recursive resolver.

DNS resolution happens in a child PHP process. The parent kills it after three seconds, because the operating system's DNS calls do not reliably obey HTTP timeouts. Returned addresses are saved in the check and all must pass the destination policy. No answers, excessive answers, resolver errors and unsafe answers make the check fail.

A DNS-rebinding attack changes an allowed name from a public address to a private address between validation and connection. InfraWatch supplies the validated IP through cURL's CURLOPT_RESOLVE, while retaining the original hostname for HTTP Host and TLS verification. It does not perform another uncontrolled lookup for that HTTP transfer. When multiple safe addresses exist, only the first one is tested; this is a small monitor rather than a complete global availability assessment.

## TLS

HTTPS wraps HTTP in TLS, authenticating the server certificate and encrypting the connection. cURL verifies the certificate chain against the image's CA trust store and verifies that the certificate matches the original hostname. IP pinning does not disable those checks.

The leaf certificate's expiration timestamp is recorded when cURL exposes certificate information. HTTP probes have no certificate, and some failed handshakes yield no expiry value. A null value means unavailable information; it does not mean an indefinitely valid certificate. This version shows expiry in history but has no advance-expiry alert policy.

## Reverse proxy and FastCGI

Caddy is the browser-facing server. It terminates HTTPS in deployment, serves Angular's static files, applies security headers and forwards API requests to PHP-FPM using FastCGI. FastCGI carries request metadata and the script path to PHP; FPM executes Laravel's public/index.php.

Angular and the API use one browser origin, so there is no permissive cross-origin CORS configuration. Laravel maintains a server-side session with an HttpOnly cookie. Angular sends the XSRF cookie value in its standard X-XSRF-TOKEN header for same-origin mutations. Laravel's request-forgery middleware also supports browser origin checks. Login rotates the session ID and logout invalidates it.

Caddy manages public certificates automatically when a domain resolves to the VM and the necessary ports are reachable. Local Compose defaults to HTTP on port 8080; local HTTPS behavior is not implied.

## Docker networks and persistence

Compose service names are discoverable inside their shared networks: the API reaches PostgreSQL as db, and Caddy reaches PHP-FPM as api. These are internal names, unrelated to the public monitoring hostname allowlist.

The data and edge networks are internal. PostgreSQL and PHP-FPM have no published host ports. Only Caddy publishes HTTP/HTTPS ports. The worker has an outbound network because checks need internet access. An internal network is not an egress firewall for a container that also has an outbound interface; SSRF checks remain necessary.

The pgdata volume persists the database when containers are replaced. Caddy volumes persist certificate state. Application storage and generated bootstrap cache use temporary filesystems and are recreated on startup; application data lives in PostgreSQL. An ordinary docker compose down preserves volumes, whereas down --volumes deletes them.

## Scheduling and incident consistency

Laravel's scheduler runs monitor:check each minute. A database cache lock prevents normal overlap; PostgreSQL additionally holds a session advisory lock for the pass. Checks execute sequentially, giving bounded probe concurrency of one in the supported single-worker deployment.

A service-row lock makes check persistence and incident creation atomic. A partial unique index on incidents(service_id) where resolved_at is null also protects against duplicate active incidents at the database level. Acknowledged incidents retain null resolved_at, so they still count as active. Resolution sets the timestamp and frees the unique slot.

Successful checks show recovery without resolving the workflow automatically. This separates the network observation from a person's decision that an incident is closed.
