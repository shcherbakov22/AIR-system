# Legacy DB Handoff

## Scope

This document narrows the legacy database down to the data that actually matters for the current rewrite direction.

Current scope assumptions:

- keep schedules
- keep task execution history
- keep rules
- keep violations / penalties
- optionally keep goals
- ignore parents as a product role
- ignore Ivan/browser monitoring
- ignore hardware/pushup execution
- ignore math modules for now

## Active Legacy DBs

- `cw`: active legacy DB
- `cw0`: older archive/snapshot

Live temporal scan artifacts:

- `C:\Users\user\codex\legacy-db-analysis\temporal-column-summary.md`
- `C:\Users\user\codex\legacy-db-analysis\temporal-column-matrix.md`
- `C:\Users\user\codex\legacy-db-analysis\temporal-column-maxes.csv`

Recent real activity is concentrated in:

- `log_action`
- `violation`
- `rules`
- `shed_name_id`
- optional `students_goals`

## Must Import

No legacy column name has to survive as-is in the new schema.

These are the legacy values that must survive if we want to preserve the real school-system behavior and history.

### Users

- `dle_users.user_id`
- `dle_users.name`

### Task Templates

- `common_action.id`
- `common_action.name`

### Schedule Headers

- `shed_name_id.id`
- `shed_name_id.user_id`
- `shed_name_id.name`
- `shed_name_id.description`
- `shed_name_id.date_in`

### Schedule Entries

- `schedule.id`
- `schedule.shed_id`
- `schedule.user_id`
- `schedule.task_id`
- `schedule.name`
- `schedule.description`
- `schedule.deadline`
- `schedule.duration`
- `schedule.sequence`
- `schedule.necessarily`

### Task Runtime History

- `log_action.id`
- `log_action.user_id`
- `log_action.task_id`
- `log_action.sched_id`
- `log_action.time_start`
- `log_action.time_end`
- `log_action.duration`
- `log_action.assignment`
- `log_action.result`
- `log_action.review`

### Rules

- `rules.id`
- `rules.user_id`
- `rules.rule`
- `rules.payment`
- `rules.date_start`

### Violations

- `violation.id`
- `violation.rule_id`
- `violation.user_id`
- `violation.date_creation`
- `violation.date_paid`
- `violation.money`
- `violation.complaint`
- `violation.log_action_id`

## Optional Import

Only bring this over if the rewrite keeps the goal-notes feature:

- `students_goals.name`
- `students_goals.date`
- `students_goals.subject`
- `students_goals.today`
- `students_goals.tomorrow`
- `students_goals.month`
- `students_goals.updated_at`

## Do Not Design Around These

These legacy columns are not worth preserving as first-class concepts in the new system:

- `dle_users.money`
- `dle_users.api_token`
- `log_action.qw`
- `log_action.all_`
- `log_action.con`
- `log_action.mood`
- `log_action.say1`
- `log_action.say2`
- `log_action.say3`
- `rules.bonus`
- `rules.hm_times`
- `rules.max`
- `rules.indulgence`
- `rules.indul_date`
- `rules.ind_discount`
- `schedule.how_many`
- `schedule.done`
- `schedule.abandon`
- `schedule.late`

These are legacy scoring/noise/state fields, monitoring leftovers, or details that should be re-modeled if ever needed.

## Schema Drift Warning

The legacy schema is not trustworthy as a clean blueprint.

Problems already confirmed:

- `schedule` is overloaded
- `results` is badly drifted between code and schema
- some features appear both in active tables and in `copy_*` mirror tables
- zero dates are used as sentinels
- relationships are implied in code, not enforced in the DB

Because of that:

- do not copy table shapes
- do not preserve column names for their own sake
- import into staging first
- map staged data into the new PostgreSQL schema second

## Suggested Import Order

1. users
2. task templates
3. schedule headers and entries
4. task runtime history
5. rules
6. violations
7. optional goals

## Good Default Decisions For The Next Agent

- treat `cw` as the source of truth unless a specific table is proven to require `cw0`
- ignore `copy_*` tables for normal import work
- preserve IDs in staging even if the final schema uses new IDs
- keep import jobs idempotent
- keep the rewrite schema independent from legacy naming
