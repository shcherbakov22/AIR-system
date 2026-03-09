# Environment Layout

## 1. Goals

The environment should make three things easy:

- local development
- staging validation against imported data
- production operation with clear process boundaries

## 2. Local Development Topology

Use Docker Compose for local development.

### Services

- `caddy`
- `php-fpm`
- `queue-worker`
- `scheduler`
- `postgres`
- `redis`
- `mailpit`
- `minio`
- `node` optional for frontend dev server
- `hardware-bridge-simulator` optional

### Recommended Compose Layout

```text
infra/docker/compose.yaml
infra/docker/php/Dockerfile
infra/docker/node/Dockerfile
infra/caddy/Caddyfile.dev
```

### Local Hostnames

- `school.localhost`
- `minio.localhost`
- `mail.localhost`

Keep the product on one main hostname. External clients should talk to `/api/v1/*` on that same host.

## 3. Production Topology

### Core Processes

- Caddy
- PHP-FPM
- Laravel queue workers
- Laravel scheduler
- PostgreSQL
- Redis
- object storage or mounted storage

### Optional Separate Runtime

- hardware bridge on a separate machine close to the device

That bridge should authenticate back to the main platform over HTTPS.

## 4. Service Responsibilities

### Caddy

- terminate TLS
- serve built frontend assets
- proxy PHP requests to PHP-FPM
- log requests
- compress responses with `zstd` and `gzip`

### PHP-FPM

- handle web and API requests
- no queue jobs in the request container

### Queue Worker

- asynchronous notifications
- consequence job orchestration
- reporting snapshots
- imports

### Scheduler

- periodic schedule generation
- stale session cleanup
- monitoring policy sync
- nightly snapshots and housekeeping

### PostgreSQL

- authoritative application data
- imported legacy mappings

### Redis

- queues
- cache
- rate limiting
- short-lived monitoring state if needed

## 5. Environment File Strategy

Commit only examples, never secrets.

### Files

- `apps/platform/.env.example`
- `apps/platform/.env.testing`
- `apps/extension/.env.example`
- `apps/hardware-bridge/.env.example`

Not committed:

- `.env.local`
- `.env.staging`
- `.env.production`

## 6. Platform Environment Variables

### App

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG`
- `APP_URL`
- `APP_TIMEZONE`
- `APP_LOCALE`

### Database

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### Cache / Queue / Session

- `CACHE_STORE`
- `QUEUE_CONNECTION`
- `SESSION_DRIVER`
- `REDIS_HOST`
- `REDIS_PORT`
- `REDIS_PASSWORD`

### Mail

- `MAIL_MAILER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

### Storage

- `FILESYSTEM_DISK`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `AWS_BUCKET`
- `AWS_ENDPOINT`
- `AWS_USE_PATH_STYLE_ENDPOINT`

### External Clients

- `DEVICE_TOKEN_TTL_MINUTES`
- `EXTENSION_TOKEN_TTL_MINUTES`
- `MONITORING_API_RATE_LIMIT`

### Monitoring / Observability

- `SENTRY_LARAVEL_DSN`
- `LOG_CHANNEL`
- `LOG_LEVEL`

### Feature Flags

- `FEATURE_MONITORING_ENABLED`
- `FEATURE_CONSEQUENCES_ENABLED`
- `FEATURE_GOALS_ENABLED`

## 7. Extension Environment Variables

- `VITE_API_BASE_URL`
- `VITE_EXTENSION_NAME`
- `VITE_EXTENSION_ENV`

The extension should not embed secrets. It should only use runtime-issued tokens.

## 8. Hardware Bridge Environment Variables

- `BRIDGE_API_BASE_URL`
- `BRIDGE_DEVICE_ID`
- `BRIDGE_DEVICE_SECRET`
- `BRIDGE_POLL_INTERVAL_MS`
- `BRIDGE_SERIAL_PORT`
- `BRIDGE_SERIAL_BAUD`
- `BRIDGE_SIMULATOR_MODE`
- `BRIDGE_REQUEST_TIMEOUT_SECONDS`

## 9. Caddy Layout

### Dev Caddyfile Responsibilities

- map `school.localhost` to `apps/platform/public`
- route PHP through `php-fpm:9000`
- enable logs
- enable compression
- pass through websocket headers if needed later

### Prod Caddyfile Responsibilities

- TLS
- logs to file or structured stdout
- secure headers
- asset caching for built files
- request body size limits on ingestion endpoints if needed

## 10. Local Port Plan

- `80/443` -> Caddy
- `5432` -> PostgreSQL
- `6379` -> Redis
- `8025` -> Mailpit UI
- `9000` -> PHP-FPM internal only
- `9001` -> MinIO console
- `9002` -> MinIO API
- `5173` -> Vite dev server optional

## 11. Data And Storage Layout

### Database

- app data
- audit logs
- import mapping tables
- consequence jobs

### Object Storage / File Storage

- exported reports
- uploaded screenshots if retained
- imported reference files
- generated backups if the ops model uses them

Avoid file-based application state for:

- queues
- policy
- violation state
- device locking

## 12. Queue Layout

Suggested queues:

- `default`
- `imports`
- `notifications`
- `reports`
- `consequences`
- `monitoring`

Workers:

- one general worker for `default,notifications,reports`
- one isolated worker for `imports`
- one isolated worker for `consequences,monitoring`

This avoids consequence/device jobs being blocked by imports.

## 13. Scheduled Jobs

Planned scheduled commands:

- generate daily schedules
- expire stale task sessions
- compute dashboard snapshots
- rotate monitoring summaries
- archive old monitoring events
- verify backups

## 14. Secrets Handling

Use:

- local `.env.local` for development
- secret manager or deployment platform secrets for staging/prod

Never store in repo:

- DB passwords
- app keys
- device secrets
- S3 secrets
- extension bootstrap tokens

## 15. Logging Strategy

### Platform

- structured JSON logs in staging/prod
- separate channels for:
  - app
  - imports
  - monitoring ingestion
  - consequence processing

### Bridge

- JSON logs
- device session start/stop
- serial errors
- job acknowledgement / completion

### Extension

- console logs in development only
- optional local debug export

## 16. Deployment Flow

### Platform

1. build assets
2. deploy code
3. run migrations
4. restart PHP-FPM
5. restart queue workers
6. verify health endpoints

### Bridge

1. deploy bridge package
2. restart systemd service
3. verify simulator or device handshake

## 17. Health Endpoints

Platform should expose:

- `/health/live`
- `/health/ready`
- `/health/dependencies`

Checks should cover:

- database
- redis
- queue backlog threshold
- object storage if used

## 18. Environments To Maintain

- `local`
- `test`
- `staging`
- `production`

Do not add more until there is a real operational need.

## 19. Minimum Staging Requirements

- imported subset of production data
- real auth and role boundaries
- real queue workers
- extension pointed at staging
- hardware bridge in simulator mode

That is enough to validate the risky workflows without touching real hardware first.
