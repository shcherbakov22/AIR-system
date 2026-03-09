# Reimplementation Outline

## 1. Objective

Rebuild the legacy school / discipline platform as a modern, maintainable system on:

- PHP 8.4
- Laravel 12
- Caddy 2
- PHP-FPM
- PostgreSQL 16 preferred
- Redis 7 for queues, cache, rate limiting
- Node 22 for frontend builds

If migration risk forces it, MariaDB 11 can be used for the first release, but PostgreSQL is the better long-term target because the legacy schema is loose and the rewrite should become stricter, not looser.

## 2. Working Interpretation Of The Legacy Product

The current system is not a generic public school CMS. It is a combined:

- student task scheduler
- admin supervision dashboard
- discipline and consequence engine
- punishment balance / penalty tracker
- browser activity monitor
- attention / "look away" detector
- pushup / hardware consequence queue
- learning goals / handbooks / reading / math mini-modules

The core business loop is:

1. A student manages a schedule and starts a schedule run.
2. The student starts and stops each schedule task in order, or interrupts with an ad hoc timed task.
3. Admin can review activity, progress, interruptions, and violations.
4. Rules can generate punishment balance or physical consequences.
5. Browser activity and attention signals can automatically trigger consequences.
6. Penalties are cleared or waived by admin; device-completed consequences can provide evidence but should not directly clear balances.

Student workflow note:

- Per `ADR-008`, the student-facing runtime is schedule-first.
- Assignment inboxes and recent-work history are not the core student product.

## 3. Current Data Shape Worth Preserving

From the extracted schema and live counts:

- `dle_users`: 16
- `parents`: 7
- `common_action`: 63
- `available_task`: 3290
- `log_action`: 31725
- `rules`: 64
- `violation`: 24897
- `schedule`: 25342
- `tab_events`: 19274
- `students_goals`: 73
- `user_attention`: 1

This says the important historical data is not the old CMS content. It is:

- user identities
- student configuration and legacy ownership metadata
- task catalog and availability
- task session history
- schedules and schedule execution
- rules and violations
- browser activity events
- penalty / consequence history

## 4. What I Would Keep Conceptually

- Student accounts and admin roles
- Student configuration profiles
- Task catalog
- User-specific task availability
- Schedules and schedule templates
- Start/stop task sessions
- Rule definitions
- Violations and settlement flow
- Admin-controlled punishment balance tracking
- Browser whitelist per task
- Attention monitoring / look-away penalties
- Pushup consequence queue and hardware integration
- Goals / reading / handbook / math modules, but as secondary modules

## 5. What I Would Not Carry Forward As-Is

- DLE CMS coupling
- MyBB/forum coupling
- Mixed auth and domain columns in `dle_users`
- MD5/SHA1 password compatibility beyond a one-time migration path
- MyISAM tables
- zero dates like `0000-00-00 00:00:00`
- "say1/say2/say3" flag columns
- direct SQL embedded in page scripts
- frontend logic hidden inside template JS only
- file-based JSON state as the primary source of truth for policy
- browser monitoring endpoints with broad unauthenticated CORS
- root-level admin tools mixed into production web root

## 6. Recommended Product Architecture

Use a modular monolith, not microservices.

Reason:

- the system is medium-sized, not internet-scale
- domain coupling is high
- a clean monolith will be easier to reason about than services
- hardware and browser-monitoring can still be isolated behind internal modules and queues

Suggested bounded contexts:

1. Identity and access
2. Admins and students
3. Student settings and configuration
4. Task catalog
5. Scheduling
6. Task execution
7. Rules and violations
8. Penalty ledger
9. Monitoring and policy
10. Hardware consequences
11. Goals and learning resources
12. Reporting and audit

## 7. Recommended Technology Stack

### Backend

- Laravel 12
- PHP 8.4
- Eloquent only where it helps; use explicit query objects for reporting-heavy pages
- Laravel Sanctum for API/device/session tokens
- Laravel Queues with Redis
- Laravel Scheduler for periodic jobs

### Frontend

- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS
- Vue Query or a simple composable fetch layer for live pages
- Chart.js or ECharts for reporting

Why not a separate SPA and separate API first:

- faster delivery
- simpler auth
- easier server-side navigation and permissions
- lower operational complexity

### Infra

- Caddy as reverse proxy and static asset server
- PHP-FPM as the primary runtime
- PostgreSQL 16
- Redis 7
- MinIO or S3-compatible storage for screenshots / uploads / exports if needed
- Docker Compose for local dev
- systemd or containers in production

## 8. Proposed Top-Level Modules

### 8.1 Identity And Access

- users
- roles: superadmin, admin, student, observer, device-agent
- password reset
- session auth for web
- token auth for extension/agents/device clients
- audit of login and privileged actions

### 8.2 Students And Student Settings

The legacy `parents` table should not be interpreted as a product-facing parent model.

It looks more like student ownership/configuration metadata, especially for consequence defaults like pushup count and rest time.

New model:

- student_profiles
- student_settings
- student_consequence_profiles
- optional admin_student_assignments only if later needed for permissions

Default assumption for the rewrite:

- admins use the system
- students use the system
- parents do not have accounts or UI flows

### 8.3 Task Catalog

Legacy source: `common_action`, `available_task`, parts of `tasks`, `activities_type`

New model:

- task_templates
- task_categories
- task_assignments
- activity_types
- task_rules

Each task template should have:

- title
- description
- activity type
- expected duration range
- optional scoring rules
- optional allowed site/app policy profile
- active/inactive state

### 8.4 Scheduling

Legacy source: `schedule`, `shed_name_id`, `schedule_daily`, `active_sched`

New model:

- schedule_templates
- schedule_entries
- schedule_runs
- schedule_checkpoints
- schedule_interruptions
- ad_hoc_task_runs

Distinguish clearly between:

- a reusable schedule template
- a student-managed recurring schedule
- a dated generated schedule run
- a specific schedule entry instance
- interruption / pause state for that run
- completion stats for that instance

### 8.5 Task Execution

Legacy source: `log_action`

New model:

- task_sessions
- session_events
- session_reviews

A task session should store:

- student
- task assignment or template
- started_at
- ended_at
- planned duration
- actual duration
- assignment text / instructions
- outcome score
- completion state
- review fields

Do not overload one table with every workflow flag.

### 8.6 Rules And Violations

Legacy source: `rules`, `violation`

New model:

- rule_definitions
- rule_instances
- violations
- violation_events
- violation_resolution

Important distinction:

- rule definition: "Observe the time", "Looked away 3 times"
- violation: a specific incident
- resolution: cleared by admin, waived by admin, or marked ready for admin review after an external consequence

### 8.7 Penalty Ledger

Legacy source: `dle_users.money`, `pay_log`

This is not a real wallet or allowance system. It is an admin-controlled punishment balance.

Do not keep mutable balance as the source of truth.

New model:

- penalty_accounts
- penalty_transactions
- penalty_transaction_types

Penalty balance should be derived or maintained transactionally from the ledger.

Transaction types:

- penalty issued
- admin reduction
- admin waiver
- admin correction
- consequence completion noted

### 8.8 Monitoring And Policy

Legacy source: `Ivan/check_activity.php`, `Ivan/whitelist.json`, `tab_events`, `user_attention`, `portal.php`

New model:

- monitoring_devices
- policy_profiles
- policy_rules
- allowed_sites
- allowed_apps
- monitoring_events
- attention_events
- policy_decisions

Important redesign:

- allowed sites/apps move from JSON blobs into the database
- task-specific policy profiles can be attached to task templates or assignments
- every monitoring decision should be reproducible from saved policy data

### 8.9 Hardware Consequences

Legacy source: `iva/*`, `ss/pushup_demon.php`, queue files, Arduino bridge

New model:

- consequence_jobs
- consequence_job_events
- consequence_devices
- consequence_attempts

Do not use filesystem lock files as the system of record.

Instead:

- create consequence jobs in DB
- workers claim jobs transactionally
- device adapter posts progress and completion events
- UI reads job state from DB

### 8.10 Goals / Learning Resources

Legacy source: `students_goals`, `handbooks`, `readbooks`, `math`, `math_results`, `projects`, `plans`

Keep this as a second-phase module, not the first thing rebuilt.

Submodules:

- goals and reflection
- reading library
- handbooks/resources
- math training results
- projects / long-term work

## 9. Proposed New Database Model

### Core Tables

- organizations
- users
- profiles
- student_profiles
- student_settings
- student_consequence_profiles

### Tasking

- task_templates
- task_categories
- task_assignments
- schedule_templates
- schedule_entries
- schedule_runs
- task_sessions
- task_session_events

### Discipline

- rule_definitions
- rule_assignments
- violations
- violation_resolutions
- penalty_accounts
- penalty_transactions

### Monitoring

- monitoring_devices
- policy_profiles
- policy_profile_sites
- policy_profile_apps
- monitoring_events
- attention_events
- policy_decisions

### Hardware

- consequence_devices
- consequence_jobs
- consequence_job_events

### Learning/Reporting

- goals
- reading_items
- handbook_items
- project_items
- math_attempts
- reports_snapshots

### Audit

- audit_logs
- imports
- import_rows

## 10. Legacy-To-New Mapping

| Legacy | New |
| --- | --- |
| `dle_users` | `users`, `profiles`, `student_profiles`, `penalty_accounts` |
| `parents` | `student_settings`, `student_consequence_profiles` |
| `common_action` | `task_templates` |
| `available_task` | `task_assignments` |
| `schedule` + `shed_name_id` | `schedule_templates`, `schedule_entries` |
| `log_action` | `task_sessions`, `task_session_events` |
| `rules` | `rule_definitions`, `rule_assignments` |
| `violation` | `violations`, `violation_resolutions` |
| `pay_log` | `penalty_transactions` |
| `tab_events` | `monitoring_events` |
| `user_attention` | `attention_events`, `attention_state` |
| `students_goals` | `goals` |
| `handbooks` / `readbooks` / `math_results` | dedicated learning modules |

## 11. Frontend Applications To Build

### 11.1 Admin Web App

Pages:

- login
- dashboard
- students overview
- live status board
- task catalog
- assignments
- schedule builder
- schedule day view
- rules and penalties
- violations queue
- penalty ledger
- monitoring review
- allowed-sites policy editor
- consequence queue / hardware status
- goals / reading / resources
- reports
- settings

### 11.2 Student Web App

Pages:

- login
- schedule builder
- active schedule
- current schedule task
- start/stop task step
- interrupt / pause schedule
- ad hoc timer
- open penalties
- consequences to complete
- goals
- reading/resources

### 11.3 Browser Extension

Rewrite the extension from scratch.

Features:

- authenticate device/user securely
- send active URL events
- send active app if available
- send idle / inactive events
- optionally send attention events if a detector exists
- cache events offline briefly and retry
- receive current policy snapshot

Use Manifest V3.

### 11.4 Device / Hardware Bridge

Separate process, not public web code.

Responsibilities:

- poll or subscribe for pending consequence jobs
- talk to serial/Arduino device
- report acknowledgements, progress, completion
- support a simulator mode for development

## 12. API Design

### Web Session APIs

- standard Laravel session auth
- CSRF-protected form and JSON endpoints

### External/Device APIs

- `/api/v1/auth/device/login`
- `/api/v1/monitoring/events`
- `/api/v1/monitoring/policy`
- `/api/v1/attention/events`
- `/api/v1/consequences/jobs/next`
- `/api/v1/consequences/jobs/{id}/ack`
- `/api/v1/consequences/jobs/{id}/progress`
- `/api/v1/consequences/jobs/{id}/complete`

### Internal Boundaries

- keep controllers thin
- use service classes / actions
- emit domain events for:
  - session started
  - session stopped
  - violation created
  - penalty transaction posted
  - consequence job queued
  - consequence completed

## 13. Security Plan

- Argon2id passwords only
- no legacy hash acceptance after migration window
- RBAC and policy gates everywhere
- per-device tokens for extensions and hardware
- strict CORS only for intended origins
- signed webhook/device requests if needed
- rate limiting on monitoring ingestion
- audit logs on admin actions
- secrets in env or vault, never in code
- no raw admin utilities in public web root

## 14. Operational Plan

### Caddy

Responsibilities:

- TLS termination
- static assets
- compression
- request logs
- reverse proxy to PHP-FPM

### Processes

- `caddy`
- `php-fpm`
- `queue worker`
- `scheduler`
- optional `realtime worker` later
- optional `hardware bridge` on the device host

### Storage

- PostgreSQL backups nightly
- uploaded/media/screenshots in object storage or mounted volume
- Redis persistence only if needed for queues

### Observability

- structured app logs
- Sentry
- health endpoints
- queue metrics
- DB backup verification

## 15. Migration Strategy

Do not attempt a "big copy and pray" migration.

### Phase A: Discovery

- freeze a field map from legacy tables and files
- decide which modules are in v1 versus deferred
- build import specs per table

### Phase B: Schema And Importers

- create strict new schema
- write one importer per domain:
  - users
  - student settings and configuration
  - task templates
  - assignments
  - schedules
  - sessions
  - rules
  - violations
  - penalty balance reconstruction
  - monitoring events

### Phase C: Dual Validation

- import legacy data into staging
- validate counts and samples
- compare selected reports between old and new

### Phase D: Controlled Cutover

- freeze writes on legacy
- run final import delta
- switch web traffic
- keep legacy read-only for reference

## 16. Migration Rules I Would Apply

- convert zero dates to `NULL`
- split overloaded columns into separate entities
- convert mutable penalty balance into a ledger
- preserve original legacy IDs in import mapping tables
- preserve raw imported rows for traceability
- make every imported violation and session traceable back to legacy row ID

## 17. Delivery Phases

### Phase 0: Product Definition

- confirm actors and roles
- confirm which modules are mandatory in release 1
- confirm whether there are multiple admin operators or just one admin context

### Phase 1: Foundations

- auth
- roles
- users
- admin/student model
- task templates
- student-managed schedules

### Phase 2: Scheduling And Sessions

- schedule builder
- schedule execution
- interruption and pause flow
- ad hoc timer
- task start/stop
- live dashboards

### Phase 3: Rules, Violations, Penalty Ledger

- rules
- penalties
- penalty ledger
- admin-only clearance

### Phase 4: Monitoring

- extension auth
- policy profiles
- URL monitoring
- idle events
- attention events

### Phase 5: Hardware Consequences

- consequence queue
- hardware bridge
- completion workflow

### Phase 6: Learning Modules

- goals
- reading
- handbooks/resources
- math/training imports

### Phase 7: Reports And Cleanup

- reporting
- audit dashboards
- remove legacy-only assumptions

## 18. Testing Strategy

- unit tests for domain rules
- feature tests for all core flows
- API contract tests for extension/device APIs
- browser E2E for admin and student flows
- import tests against sanitized legacy snapshots
- simulator tests for hardware consequence worker

## 19. Risks

- legacy behavior is spread across many small scripts
- some business rules are implicit in UI code, not backend code
- the browser monitoring policy currently depends on JSON and ad hoc matching
- pushup/hardware flow is fragile and partly file-based
- some modules may be obsolete but still referenced indirectly

## 20. Open Questions Before Implementation

- Is this effectively a single-admin system, or do multiple admins need scoped access to different students?
- Which modules are mandatory for v1:
  - tasks and schedules
  - violations and penalty ledger
  - monitoring
  - hardware pushups
  - goals / reading / math
- Should the new system keep numeric punishment balances exactly, or normalize them into a simpler admin-controlled penalty unit model?
- Is browser monitoring limited to Chrome, or should it support Edge/Firefox too?
- Will the hardware consequence flow remain Arduino-based, or should it be device-agnostic?

## 21. Recommended First Implementation Milestone

The first shipping milestone should be:

- modern auth
- admin/student accounts
- task catalog
- schedule builder
- task session tracking
- rules and violations
- penalty ledger
- basic admin dashboard

Defer until milestone 2:

- browser extension
- attention detection
- hardware pushups
- goals/resources modules

That gets the system stable and usable before rebuilding the risky edge integrations.

## 22. My Recommended Rewrite Position

If I were leading this rewrite, I would do it as:

- Laravel 12 modular monolith
- Vue 3 + Inertia frontend
- PostgreSQL
- Redis queues
- Caddy + PHP-FPM
- browser extension and hardware bridge as separate clients against a clean API

I would not try to preserve the DLE architecture, template system, or page-script model.
