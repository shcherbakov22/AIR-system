# Handoff

## Purpose

This repo is the active rewrite of the legacy school / discipline system. The working product lives in `apps/platform`.

## Current Product Shape

- mentor + student only
- schedule-first student workflow
- violations are the discipline mechanism
- mentor monitoring is built into the main web app
- legacy screen/camera uploader compatibility exists on the main server
- imports are tactical and data-driven, not schema-driven

## Most Important ADRs

- `docs/adr/ADR-001-ACTOR-MODEL.md`
- `docs/adr/ADR-003-DATABASE-STRATEGY.md`
- `docs/adr/ADR-007-SCHEMA-FIRST-IMPORT-LATER.md`
- `docs/adr/ADR-008-STUDENT-WORKFLOW-IS-SCHEDULE-FIRST.md`

## Main Runtime Areas

- `app/Http/Controllers/Admin`
  Mentor dashboard, students, rules, violations, schedule templates, task templates, capture history, speech queue.
- `app/Http/Controllers/Student`
  Student dashboard, rules, schedules, schedule runs, and task-session stop flow.
- `app/Services/AutomaticObserveTheTimeViolationService.php`
  Automatic `Observe the time` enforcement.
- `app/Services/SpeechAnnouncementService.php`
  Queues mentor monitor speech announcements.
- `resources/js/Pages/Admin/Dashboard.vue`
  Live mentor monitor board.
- `resources/js/Pages/Student/Home.vue`
  Student runtime surface.

## Live Integrations

- legacy-compatible upload endpoints:
  - `/ss/upl1.php`
  - `/ss/uplcam.php`
  - `/ss/uplscr.php`
- modern API upload endpoints:
  - `/api/student-monitor-captures/screen`
  - `/api/student-monitor-captures/camera`

## Current Operational Facts

- production app server: `192.168.11.228`
- legacy source DB frequently referenced during migration work: `192.168.11.66`
- Caddy serves both internal HTTPS and plain HTTP compatibility routes
- daily student capture purge runs from a systemd timer on the app server

## Current Notes

- the repo has been trimmed back to the active Laravel app plus still-relevant docs
- old planning docs and dead sidecar skeletons were intentionally removed once the product direction stabilized
- `task_assignments` remain in the database and models for legacy/historical compatibility, but the unused assignment UI flow has been removed
