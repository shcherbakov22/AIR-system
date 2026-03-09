# Handoff

## Purpose

This repo is the active rewrite of the legacy school / discipline system that was copied from `192.168.11.222`.

The rewrite direction is stable:

- admin + student only
- student workflow is schedule-first
- penalty ledger, not a wallet
- schema-first rewrite, import later
- no parent-facing product
- no Ivan/browser-monitoring rebuild
- no hardware/pushup execution rebuild for now

The most important ADRs for continuity are:

- `docs/adr/ADR-001-ACTOR-MODEL.md`
- `docs/adr/ADR-004-PENALTY-MODEL.md`
- `docs/adr/ADR-007-SCHEMA-FIRST-IMPORT-LATER.md`
- `docs/adr/ADR-008-STUDENT-WORKFLOW-IS-SCHEDULE-FIRST.md`

## Verified State

Last verified in this workspace on `2026-03-09`:

- `php artisan test` passed: `105` tests, `1157` assertions
- `npm run build` passed
- browser flows were verified earlier with Playwright MCP

Local app facts:

- app path: `apps/platform`
- URL: `http://127.0.0.1:8000`
- locale: Russian
- stack: Laravel 12, Inertia, Vue 3, TypeScript, PostgreSQL

Local seeded users:

- admin: `admin / admin12345`
- student: `student-demo / student12345`

Note:

- local demo data includes some extra rows created during browser verification
- some old demo content values in the local DB may still be in English even though the UI shell is Russian

## What Is Implemented

Platform and auth:

- Laravel app bootstrapped and running on PostgreSQL
- admin and student actor model
- login/logout and profile management
- public registration/recovery disabled

Admin features:

- student create/edit flows
- task template create/edit/list
- task assignment create/edit/list
- schedule template create/edit/list
- rule definition create/edit/list
- violation create/list/review/resolve/waive
- penalty ledger index and per-student ledger
- import dashboard scaffolding
- task session review

Student features:

- student-owned schedule create/edit/list
- multi-block ordered schedules
- start a schedule run
- manually start/stop each block in order
- pause a running schedule block
- run an ad hoc own timer
- resume the paused schedule block
- live timer on the student home page
- student penalty page

Runtime/discipline behavior:

- admin-created violations automatically post penalty charges
- penalty balance is derived from ledger transactions
- credits cannot drive the balance below zero
- student settings enforcement exists and can block certain student actions

Sidecar / integration groundwork:

- extension app skeleton exists
- hardware-bridge app skeleton exists
- edge-client heartbeat API exists

Localization:

- Laravel locale set to Russian
- frontend shell and app pages translated to Russian
- backend validation and flash messages translated where already wired

## What Exists But Is Not Product-Core

These features are implemented in the rewrite, but they are not the product center of gravity:

- admin task assignments
- assignment-driven session flows

They still exist in code because they were built before the product direction fully converged, but the intended student product is schedule-first, not assignment-inbox-first.

## What Is Intentionally Not Implemented

Do not reintroduce these unless the user explicitly changes direction:

- parent UI or parent accounts
- wallet/allowance behavior
- Ivan whitelist/browser monitoring
- look-away auto-violation pipeline
- training dashboard logic from Ivan
- pushup hardware execution

The legacy system had those pieces, but current direction is to leave them out.

## Legacy Artifact Locations

These are the reference inputs in this workspace:

- legacy site copy: `C:\Users\user\codex\site-copy-192.168.11.222-20260306`
- focused extraction: `C:\Users\user\codex\school-system-extract`
- DB dumps: `C:\Users\user\codex\db-dumps-192.168.11.222-20260306`
- live DB temporal analysis: `C:\Users\user\codex\legacy-db-analysis`

The most useful files from the DB analysis are:

- `C:\Users\user\codex\legacy-db-analysis\temporal-column-summary.md`
- `C:\Users\user\codex\legacy-db-analysis\temporal-column-matrix.md`
- `C:\Users\user\codex\legacy-db-analysis\temporal-column-maxes.csv`

## Legacy DB Takeaways

High-confidence summary:

- `cw` is the active legacy database
- `cw0` looks like an older archive/snapshot
- recent real activity is concentrated in:
  - `log_action`
  - `violation`
  - `rules`
  - `shed_name_id`
  - optionally `students_goals`

Important drift warning:

- the extracted schema and the live code do not line up perfectly
- `schedule` and especially `results` are overloaded and drifted across time
- do not design the new schema to match the old table shapes

The current must-import set is documented in:

- `docs/imports/LEGACY_DB_HANDOFF.md`

## Current Rewrite Boundaries

Treat these as settled unless the user says otherwise:

- no legacy column names need to survive verbatim
- new schema remains authoritative
- import design happens after schema decisions, not before
- `parents` should not become a real actor model in the rewrite
- `money` from legacy should not become a wallet concept

## Recommended Next Work

The clean next steps for another agent are:

1. Decide whether `students_goals` should survive as a first-class module or remain deferred.
2. Remove or quarantine assignment-first UX if the product is staying schedule-first.
3. Design import staging tables and import jobs for the must-import legacy data only.
4. Import in this order:
   - users
   - task templates
   - schedules
   - task session history
   - rules
   - violations / penalty history
5. Keep browser monitoring and hardware integration out of scope unless explicitly reintroduced.

## Operational Commands

From `apps/platform`:

- test: `C:\Users\user\tools\php-8.5.1\php.exe artisan test`
- build: `npm run build`
- serve: `C:\Users\user\tools\php-8.5.1\php.exe artisan serve`

Repo-local PostgreSQL helper:

- `powershell -ExecutionPolicy Bypass -File .\tools\scripts\dev\postgres.ps1 ensure`

## Last Practical Notes

- The root README and platform README were updated for this handoff because they were stale.
- Another agent should start with this file, then the ADRs, then `docs/imports/LEGACY_DB_HANDOFF.md`.
