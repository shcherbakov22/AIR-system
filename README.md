# School System Redo

This repository is the implementation workspace for the full rewrite of the legacy school / discipline system.

Current status:

- planning docs are in place
- the monorepo skeleton exists
- the platform Laravel app is implemented and running
- the app is PostgreSQL-first and Russian-localized
- student schedules, runtime sessions, rules, violations, and penalties are built
- import scaffolding and edge-client heartbeat groundwork are present

Current handoff entrypoint:

- `docs/HANDOFF.md`

Primary planning document:

- `docs/REIMPLEMENTATION_OUTLINE.md`

Concrete follow-up planning documents:

- `docs/REPO_BLUEPRINT.md`
- `docs/ENVIRONMENT_LAYOUT.md`
- `docs/PHASE1_BACKLOG.md`
- `docs/adr/README.md`
- `docs/imports/LEGACY_DB_HANDOFF.md`

Current repo areas:

- `apps/platform`
  Main Laravel application target.
- `apps/extension`
  Browser extension target.
- `apps/hardware-bridge`
  Device / hardware bridge target.
- `packages/api-contracts`
  Shared API specs and schemas.
- `packages/shared-ui`
  Shared UI artifacts if needed later.
- `packages/shared-config`
  Shared linting and formatting config.
- `infra`
  Caddy, Docker, systemd, and DB operational files.
- `tools`
  Utility scripts, SQL helpers, and fixtures.

Current local platform workflow:

- ensure the local PostgreSQL cluster exists and is running:
  `powershell -ExecutionPolicy Bypass -File .\tools\scripts\dev\postgres.ps1 ensure`
- run Laravel migrations:
  `C:\Users\user\tools\php-8.5.1\php.exe .\apps\platform\artisan migrate`
- start the app:
  `C:\Users\user\tools\php-8.5.1\php.exe .\apps\platform\artisan serve`
- run tests:
  `C:\Users\user\tools\php-8.5.1\php.exe .\apps\platform\artisan test`
- build frontend assets:
  `npm run build`

Last verified state in this workspace:

- `php artisan test` passed: `105` tests, `1157` assertions
- `npm run build` passed

Reference inputs:

- Legacy extraction: `../school-system-extract`
- Raw site copy: `../site-copy-192.168.11.222-20260306`
- Database dumps: `../db-dumps-192.168.11.222-20260306`
- Legacy DB analysis: `../legacy-db-analysis`
