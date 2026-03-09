# Phase 1 Backlog

## 1. Phase 1 Objective

Ship a usable modern core platform with:

- auth
- admin/student roles
- student settings
- task catalog
- schedules
- student-managed schedule execution
- task session start/stop
- rules and violations
- penalty ledger
- admin dashboard

Direction update:

- Per `ADR-008`, the student-facing product is schedule-first.
- Assignment inboxes and recent-work views should not drive future student-facing implementation.
- Existing assignment-oriented backlog items should be treated as temporary planning leftovers unless explicitly retained.

Excluded from Phase 1:

- browser extension
- attention detection
- hardware pushup bridge
- reading/resources/goals rebuild

## 2. Ticket Format

Each ticket below includes:

- purpose
- main deliverables
- dependencies
- acceptance criteria

## 3. Epic A: Repo And Tooling Foundation

### P1-001 Initialize Monorepo Skeleton

Purpose:

- create the repo shape described in `REPO_BLUEPRINT.md`

Deliverables:

- top-level folders
- root README
- app READMEs
- editorconfig and gitignore

Depends on:

- none

Acceptance:

- repo folders exist
- no production code beyond scaffolding
- docs point to the correct app paths

### P1-002 Bootstrap Laravel Platform App

Purpose:

- create `apps/platform` with Laravel, Vite, Vue, Inertia, Tailwind

Deliverables:

- Laravel app
- frontend toolchain configured
- base layout page renders

Depends on:

- P1-001

Acceptance:

- app boots locally
- one protected page exists
- lint and tests run

### P1-003 Add Quality Tooling

Purpose:

- enforce baseline quality from day one

Deliverables:

- PHP CS fixer
- Larastan/PHPStan
- ESLint
- Prettier
- Pest
- Vitest

Depends on:

- P1-002

Acceptance:

- CI-style commands exist
- failing lint/test exits non-zero

### P1-004 Add Local Docker And Caddy Stack

Purpose:

- make the environment reproducible

Deliverables:

- Docker Compose
- Caddy dev config
- PHP container
- PostgreSQL and Redis services

Depends on:

- P1-002

Acceptance:

- local app available through Caddy
- DB and Redis reachable from the app

### P1-005 Add Queue Worker And Scheduler Setup

Purpose:

- support async flows from the start

Deliverables:

- Horizon config
- queue worker process config
- scheduler baseline

Depends on:

- P1-004

Acceptance:

- a test job can be queued and processed
- scheduler heartbeat command runs

## 4. Epic B: Identity And Roles

### P1-010 Implement User Model And Authentication

Purpose:

- replace the legacy DLE login model

Deliverables:

- `users` table
- password auth
- session login/logout
- password reset flow scaffold

Depends on:

- P1-002

Acceptance:

- user can sign in and out
- password hashes use Argon2id

### P1-011 Implement Roles And Permissions

Purpose:

- establish admin, student, and superadmin boundaries

Deliverables:

- role seeder
- permission map
- authorization middleware/policies

Depends on:

- P1-010

Acceptance:

- student cannot access admin pages
- admin cannot access superadmin-only pages

### P1-012 Legacy Password Compatibility Window

Purpose:

- allow imported users to log in after migration

Deliverables:

- legacy-hash verification path
- automatic rehash on successful login
- feature flag to disable legacy hash support later

Depends on:

- P1-010

Acceptance:

- imported legacy user can log in once with old password
- stored hash is upgraded immediately

## 5. Epic C: Students And Legacy Student Settings

### P1-020 Implement Student Core Schema

Purpose:

- model students and their operational settings

Deliverables:

- `student_profiles`
- `student_settings`
- `student_consequence_profiles`

Depends on:

- P1-010

Acceptance:

- a student can store operational settings needed by schedules and consequences
- legacy `parents` data has a clear destination in the new model

### P1-021 Build Student Profiles

Purpose:

- separate student profile data from auth

Deliverables:

- student profile model
- timezone/preference fields
- admin-visible summary card

Depends on:

- P1-020

Acceptance:

- admin can view a student profile page

### P1-022 Import Legacy Student Settings From `parents`

Purpose:

- preserve the useful configuration semantics from the legacy `parents` table

Deliverables:

- importer
- legacy ID mapping
- import report

Depends on:

- P1-020

Acceptance:

- imported counts match source rows
- pushup/time defaults land in the correct new fields

## 6. Epic D: Task Catalog And Assignments

### P1-030 Implement Task Template Schema

Purpose:

- replace `common_action` as a clear domain entity

Deliverables:

- `task_templates`
- `task_categories`
- `activity_types`

Depends on:

- P1-002

Acceptance:

- task template can be created, edited, archived

### P1-031 Implement Task Assignments

Purpose:

- replace `available_task`

Deliverables:

- `task_assignments`
- assignment dates
- assign/unassign actions

Depends on:

- P1-030

Acceptance:

- admin can assign task templates to a student
- student sees active assignments only

### P1-032 Build Task Catalog UI

Purpose:

- usable CRUD for task templates

Deliverables:

- task list page
- task detail page
- create/edit forms

Depends on:

- P1-030

Acceptance:

- admin can manage task templates end-to-end

### P1-033 Import Task Templates And Assignments

Purpose:

- bring over `common_action` and `available_task`

Deliverables:

- importers
- duplicate handling rules
- import validation report

Depends on:

- P1-030
- P1-031

Acceptance:

- imported counts match source within agreed transformation rules

## 7. Epic E: Scheduling

### P1-040 Implement Schedule Template Schema

Purpose:

- replace `shed_name_id` and related naming confusion

Deliverables:

- `schedule_templates`
- `schedule_entries`

Depends on:

- P1-030

Acceptance:

- a schedule template can hold ordered entries

### P1-041 Build Schedule Editor UI

Purpose:

- replace the legacy ad hoc schedule builder

Deliverables:

- template list
- drag/drop or explicit ordering
- entry editor

Depends on:

- P1-040

Acceptance:

- admin can build and save a schedule template

### P1-042 Implement Daily Schedule Instantiation

Purpose:

- create concrete daily schedules from templates

Deliverables:

- scheduled command
- generated schedule runs
- date scoping rules

Depends on:

- P1-040

Acceptance:

- next day schedule can be generated automatically

### P1-043 Import Legacy Schedules

Purpose:

- migrate `schedule`, `shed_name_id`, and related structures

Deliverables:

- importer
- mapping of sequence/order fields
- import report

Depends on:

- P1-040

Acceptance:

- imported schedule templates and entries are usable in the new UI

## 8. Epic F: Task Sessions

### P1-050 Implement Task Session Schema

Purpose:

- replace `log_action`

Deliverables:

- `task_sessions`
- `task_session_events`
- session state machine

Depends on:

- P1-031
- P1-042

Acceptance:

- session can be created, started, stopped, canceled

### P1-051 Implement Student Start/Stop Flow

Purpose:

- give students a clean task execution flow

Deliverables:

- student today page
- start task action
- stop task action
- completion result capture

Depends on:

- P1-050

Acceptance:

- student can start and stop a task from the UI

### P1-052 Guard Against Multiple Active Sessions

Purpose:

- preserve current constraint without hidden behavior

Deliverables:

- server-side rule
- useful UI error state

Depends on:

- P1-051

Acceptance:

- student cannot create two simultaneous active sessions unless explicitly allowed

### P1-053 Build Session History Views

Purpose:

- replace the old task-history tables/pages

Deliverables:

- student history page
- admin filtered history page

Depends on:

- P1-050

Acceptance:

- sessions can be filtered by student, task, date range

### P1-054 Import Legacy Task Sessions

Purpose:

- preserve `log_action` history

Deliverables:

- importer
- zero-date conversion rules
- import validation

Depends on:

- P1-050

Acceptance:

- imported session counts match source after documented normalization

## 9. Epic G: Rules, Violations, Penalties

### P1-060 Implement Rule Definitions

Purpose:

- replace legacy `rules`

Deliverables:

- `rule_definitions`
- assignment scope
- active/inactive states

Depends on:

- P1-020

Acceptance:

- admin can define rules for a student or globally

### P1-061 Implement Violations

Purpose:

- replace legacy `violation`

Deliverables:

- `violations`
- `violation_resolutions`
- creation workflows

Depends on:

- P1-060

Acceptance:

- admin can create a violation
- violation can be open, resolved, waived

### P1-062 Implement Penalty Ledger

Purpose:

- replace mutable `money` and `pay_log`

Deliverables:

- `penalty_accounts`
- `penalty_transactions`
- transaction types
- admin-only clearance workflow

Depends on:

- P1-020

Acceptance:

- penalty balance is derived from the ledger or updated transactionally from the ledger
- students cannot clear their own penalties

### P1-063 Connect Violations To Penalty Ledger

Purpose:

- model automatic punishment-balance consequences

Deliverables:

- penalty posting action
- admin clearance action
- audit record

Depends on:

- P1-061
- P1-062

Acceptance:

- issuing or clearing a violation produces the correct penalty transaction

### P1-064 Build Admin Violation And Penalty Pages

Purpose:

- replace legacy violations list and punishment views

Deliverables:

- open violations page
- violation detail page
- penalty ledger page

Depends on:

- P1-061
- P1-062

Acceptance:

- admin can inspect and clear penalties from the UI

### P1-065 Import Rules, Violations, And Penalty Data

Purpose:

- migrate discipline history

Deliverables:

- importers
- legacy ID maps
- penalty reconstruction rules

Depends on:

- P1-060
- P1-061
- P1-062

Acceptance:

- imported violations and balances are reconcilable against source data

## 10. Epic H: Admin Dashboard

### P1-070 Build Admin Home Dashboard

Purpose:

- provide the operational landing page

Deliverables:

- students summary cards
- current active sessions
- open violations
- penalty summary

Depends on:

- P1-021
- P1-053
- P1-064

Acceptance:

- admin can see live student state in one place

### P1-071 Build Student Today Dashboard

Purpose:

- give students a focused landing page

Deliverables:

- today schedule
- active assignment list
- current session status
- open penalties summary

Depends on:

- P1-051
- P1-042

Acceptance:

- student can understand what to do next without navigating deep pages

### P1-072 Build Basic Reporting Widgets

Purpose:

- expose the high-value metrics needed immediately

Deliverables:

- sessions by day
- completed tasks by student
- open violations count

Depends on:

- P1-053
- P1-064

Acceptance:

- admin dashboard shows real historical metrics

## 11. Epic I: Imports And Verification

### P1-080 Create Import Mapping Tables

Purpose:

- make every migration traceable

Deliverables:

- import runs table
- import row mapping tables
- import status tracking

Depends on:

- P1-002

Acceptance:

- every imported entity stores source table and source row reference

### P1-081 Build Reconciliation Commands

Purpose:

- compare new system counts with legacy counts

Deliverables:

- artisan reconciliation commands
- summary report output

Depends on:

- P1-054
- P1-065

Acceptance:

- command reports count mismatches and sample mismatches

### P1-082 Produce Phase 1 Go-Live Validation Checklist

Purpose:

- make cutover decision explicit

Deliverables:

- runbook
- smoke test list
- rollback checklist

Depends on:

- all phase 1 implementation tickets

Acceptance:

- checklist can be executed by someone other than the implementer

## 12. Phase 1 Definition Of Done

Phase 1 is complete when:

- users can authenticate with proper roles
- admins can manage students, task templates, assignments, and schedules
- students can execute tasks in the new UI
- violations and penalty ledger work end-to-end
- imported legacy data is visible and reconciled
- admin dashboard is operational
- local, staging, and deployment runbooks exist

Phase 1 is not complete merely because pages render. It needs imported data, tested workflows, and operational docs.
