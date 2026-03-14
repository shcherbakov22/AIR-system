# School System Redo

This repository contains the active Laravel rewrite of the legacy school / discipline system.

## What Exists Now

- one active app in `apps/platform`
- mentor and student logins
- student-owned schedules and schedule runs
- custom timers, pause/resume, and out-of-order block starts
- mentor rules and violations
- live mentor monitor with speech, captures, and schedule visibility
- legacy-compatible screenshot/camera upload endpoints on `/ss/*.php`

## Main Repo Areas

- `apps/platform`
  The only active application in this repo.
- `docs/HANDOFF.md`
  Current product and architecture handoff.
- `docs/adr`
  ADRs that still match the live app.
- `docs/imports/LEGACY_DB_HANDOFF.md`
  Legacy schema notes that are still useful for migration work.
- `infra`
  Operational files for Caddy and server setup.

## Local Workflow

From `apps/platform`:

- install PHP dependencies: `composer install`
- install frontend dependencies: `npm install`
- run migrations: `php artisan migrate`
- start the app: `php artisan serve`
- run tests: `php artisan test`
- build assets: `npm run build`

## Primary References

- `docs/HANDOFF.md`
- `docs/adr/README.md`
- `docs/imports/LEGACY_DB_HANDOFF.md`

## Legacy Inputs Nearby

- `../school-system-extract`
- `../legacy-db-analysis`
